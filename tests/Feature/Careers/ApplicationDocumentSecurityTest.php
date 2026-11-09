<?php

namespace Tests\Feature\Careers;

use App\Models\Customer;
use App\Models\JobApplication;
use App\Models\JobApplicationDocument;
use App\Models\JobVacancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The legacy site took uniqid().'_'.$_FILES['gambar_utama']['name'] straight
 * into a web-served directory with no extension or MIME check, so an uploaded
 * .php executed (finding 3.1-5). These tests assert the replacement cannot.
 *
 * Applicant CVs are also the densest PII in this application, so "who can read
 * one" is asserted as carefully as "what can be uploaded".
 */
class ApplicationDocumentSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('applications');
        Notification::fake();
    }

    private function staff(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    }

    private function editor(): User
    {
        return User::factory()->create(['role' => User::ROLE_EDITOR, 'is_active' => true]);
    }

    private function openVacancy(): JobVacancy
    {
        return JobVacancy::factory()->create(['is_published' => true, 'closes_at' => null]);
    }

    private function pdf(string $name = 'cv.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 100, 'application/pdf');
    }

    private function apply(
        Customer $customer,
        JobVacancy $vacancy,
        ?UploadedFile $cv = null,
        ?UploadedFile $portfolio = null,
    ) {
        auth('customer')->login($customer);

        return $this->post('/karir/'.$vacancy->slug.'/lamar', [
            'cv' => $cv ?? $this->pdf(),
            'portfolio' => $portfolio ?? $this->pdf('portfolio.pdf'),
        ]);
    }

    /**
     * The legacy RCE, attempted against the replacement.
     *
     * Uses a REAL UploadedFile, not UploadedFile::fake(). That distinction is
     * the whole test:
     *
     *   Illuminate\Http\Testing\File::getMimeType() returns
     *   MimeType::from($this->name) -- it derives the type from the FILENAME.
     *   So a fake called 'cv.pdf' reports application/pdf no matter what bytes
     *   it holds, and `mimetypes:application/pdf` passes it.
     *
     *   A real upload reports text/x-php for these bytes, because Symfony
     *   sniffs the content with finfo.
     *
     * Written with a fake, this test passes while asserting nothing -- which is
     * exactly how an upload-validation suite ends up giving false assurance.
     */
    public function test_a_php_file_renamed_to_pdf_is_rejected(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'rce').'.pdf';
        file_put_contents($tmp, '<?php echo "pwned"; ?>');

        try {
            // mimes:pdf alone would pass this -- the extension says pdf.
            // mimetypes:application/pdf is what catches it.
            $file = new UploadedFile($tmp, 'cv.pdf', null, null, true);

            $this->apply(Customer::factory()->create(), $this->openVacancy(), cv: $file)
                ->assertSessionHasErrors('cv');

            $this->assertSame(0, JobApplication::count());
            $this->assertSame(0, JobApplicationDocument::count());
        } finally {
            @unlink($tmp);
        }
    }

    public function test_a_php_file_renamed_to_pdf_is_rejected_as_a_portfolio(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'rce').'.pdf';
        file_put_contents($tmp, '<?php echo "pwned"; ?>');

        try {
            $file = new UploadedFile($tmp, 'portfolio.pdf', null, null, true);

            $this->apply(Customer::factory()->create(), $this->openVacancy(), portfolio: $file)
                ->assertSessionHasErrors('portfolio');

            $this->assertSame(0, JobApplicationDocument::count());
        } finally {
            @unlink($tmp);
        }
    }

    public function test_the_fake_upload_helper_cannot_detect_disguised_content(): void
    {
        // Pins the reason the test above uses a real file. If Laravel ever
        // makes fakes sniff content, this fails and the note above can go.
        $fake = UploadedFile::fake()->createWithContent('cv.pdf', '<?php echo "pwned";');

        $this->assertSame(
            'application/pdf',
            $fake->getMimeType(),
            'UploadedFile::fake() reports MIME from the filename, so it cannot '.
            'be used to test content-sniffing validation.',
        );
    }

    public function test_a_real_pdf_with_a_php_extension_is_rejected(): void
    {
        // The mirror case: mimetypes alone would pass this, mimes catches it.
        // Both checks exist because either alone leaves a hole.
        $file = UploadedFile::fake()->createWithContent('cv.php', '%PDF-1.4 fake');

        $this->apply(Customer::factory()->create(), $this->openVacancy(), cv: $file)
            ->assertSessionHasErrors('cv');

        $this->assertSame(0, JobApplicationDocument::count());
    }

    public function test_an_image_is_rejected_as_a_portfolio(): void
    {
        $this->apply(
            Customer::factory()->create(),
            $this->openVacancy(),
            portfolio: UploadedFile::fake()->image('portfolio.jpg'),
        )->assertSessionHasErrors('portfolio');
    }

    public function test_an_oversized_cv_is_rejected(): void
    {
        $tooBig = UploadedFile::fake()->create('cv.pdf', 10241, 'application/pdf');

        $this->apply(Customer::factory()->create(), $this->openVacancy(), cv: $tooBig)
            ->assertSessionHasErrors('cv');

        $this->assertSame(0, JobApplicationDocument::count());
    }

    public function test_an_oversized_portfolio_is_rejected(): void
    {
        $tooBig = UploadedFile::fake()->create('portfolio.pdf', 30721, 'application/pdf');

        $this->apply(Customer::factory()->create(), $this->openVacancy(), portfolio: $tooBig)
            ->assertSessionHasErrors('portfolio');

        $this->assertSame(0, JobApplicationDocument::count());
    }

    public function test_an_application_with_no_document_is_rejected(): void
    {
        auth('customer')->login(Customer::factory()->create());

        $this->post('/karir/'.$this->openVacancy()->slug.'/lamar', ['cover_letter' => 'Hi'])
            ->assertSessionHasErrors(['cv', 'portfolio']);
    }

    public function test_the_stored_filename_is_generated_not_user_supplied(): void
    {
        $this->apply(
            Customer::factory()->create(),
            $this->openVacancy(),
            cv: UploadedFile::fake()->create('../../evil name.pdf', 100, 'application/pdf'),
        );

        $document = JobApplicationDocument::where('type', JobApplicationDocument::TYPE_CV)->sole();

        // A user-supplied name on disk is how traversal and extension
        // confusion get in. The original is kept only as a column.
        $this->assertStringNotContainsString('evil name', $document->path);
        $this->assertStringNotContainsString('..', $document->path);
        $this->assertStringEndsWith('.pdf', $document->path);
        $this->assertMatchesRegularExpression('#^applications/\d+/[0-9a-f-]{36}\.pdf$#', $document->path);
    }

    public function test_documents_are_stored_outside_the_public_directory(): void
    {
        $this->apply(Customer::factory()->create(), $this->openVacancy());

        $root = config('filesystems.disks.applications.root');

        // The web server must not be able to reach these at all. This is the
        // single difference that turns the legacy RCE into a non-event.
        $this->assertStringNotContainsString(
            DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR,
            $root.DIRECTORY_SEPARATOR,
        );
        $this->assertStringContainsString('private', $root);
    }

    public function test_a_guest_cannot_view_a_document(): void
    {
        $document = JobApplicationDocument::factory()
            ->for(JobApplication::factory()->for($this->openVacancy(), 'vacancy')->create(), 'application')
            ->create();

        $this->get('/dokumen-lamaran/'.$document->getKey())->assertRedirect();
    }

    public function test_a_customer_cannot_view_a_document_even_their_own(): void
    {
        // Deliberate: they already hold the file they uploaded, and every
        // extra party who can fetch a CV is another way for it to leak.
        $owner = Customer::factory()->create();
        $application = JobApplication::factory()
            ->for($this->openVacancy(), 'vacancy')->for($owner)->create();
        $document = JobApplicationDocument::factory()->for($application, 'application')->create();

        auth('customer')->login($owner);

        $response = $this->get('/dokumen-lamaran/'.$document->getKey());

        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function test_another_customer_cannot_view_a_document(): void
    {
        $owner = Customer::factory()->create();
        $attacker = Customer::factory()->create();
        $application = JobApplication::factory()
            ->for($this->openVacancy(), 'vacancy')->for($owner)->create();
        $document = JobApplicationDocument::factory()->for($application, 'application')->create();

        auth('customer')->login($attacker);

        $this->assertNotSame(
            200,
            $this->get('/dokumen-lamaran/'.$document->getKey())->getStatusCode(),
        );
    }

    public function test_staff_can_view_and_it_arrives_inline(): void
    {
        $this->apply(
            Customer::factory()->create(),
            $this->openVacancy(),
            cv: UploadedFile::fake()->create('cv-budi.pdf', 100, 'application/pdf'),
        );

        auth('customer')->logout();

        $document = JobApplicationDocument::where('type', JobApplicationDocument::TYPE_CV)->sole();

        $response = $this->actingAs($this->staff(), 'web')
            ->get('/dokumen-lamaran/'.$document->getKey())
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        // Inline, so clicking the link in the staff panel shows the PDF in
        // the browser instead of downloading it.
        $disposition = (string) $response->headers->get('Content-Disposition');

        $this->assertStringStartsWith('inline', $disposition);
        $this->assertStringContainsString('cv-budi.pdf', $disposition);
    }

    public function test_an_editor_cannot_view_and_a_deactivated_staff_member_cannot(): void
    {
        $document = JobApplicationDocument::factory()
            ->for(JobApplication::factory()->for($this->openVacancy(), 'vacancy')->create(), 'application')
            ->create();

        // Hiring is admin+ only after the RBAC split, so an editor is denied
        // before the missing file is ever touched.
        $this->assertSame(
            403,
            $this->actingAs($this->editor(), 'web')
                ->get('/dokumen-lamaran/'.$document->getKey())->getStatusCode(),
        );

        $inactive = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => false]);

        $this->assertSame(
            403,
            $this->actingAs($inactive, 'web')
                ->get('/dokumen-lamaran/'.$document->getKey())->getStatusCode(),
        );
    }

    public function test_a_missing_file_is_a_404_not_a_server_error(): void
    {
        $document = JobApplicationDocument::factory()
            ->for(JobApplication::factory()->for($this->openVacancy(), 'vacancy')->create(), 'application')
            ->create();

        $this->actingAs($this->staff(), 'web')
            ->get('/dokumen-lamaran/'.$document->getKey())
            ->assertNotFound();
    }
}
