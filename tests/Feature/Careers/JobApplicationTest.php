<?php

namespace Tests\Feature\Careers;

use App\Jobs\PurgeOrphanedApplicationDocuments;
use App\Models\Customer;
use App\Models\JobApplication;
use App\Models\JobApplicationDocument;
use App\Models\JobVacancy;
use App\Notifications\Careers\ApplicationReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JobApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('applications');
        Notification::fake();
    }

    private function openVacancy(): JobVacancy
    {
        return JobVacancy::factory()->create(['is_published' => true, 'closes_at' => null]);
    }

    private function cv(string $name = 'cv.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 150, 'application/pdf');
    }

    private function portfolio(string $name = 'portfolio.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 250, 'application/pdf');
    }

    private function apply(Customer $customer, JobVacancy $vacancy, array $payload = [])
    {
        auth('customer')->login($customer);

        return $this->post('/karir/'.$vacancy->slug.'/lamar', $payload + [
            'cv' => $this->cv(),
            'portfolio' => $this->portfolio(),
        ]);
    }

    public function test_the_vacancy_list_renders_in_both_locales(): void
    {
        $this->openVacancy();

        $this->get('/karir')->assertOk()->assertSee('lang="id"', false);
        $this->get('/en/karir')->assertOk()->assertSee('lang="en"', false);
    }

    public function test_the_list_shows_only_open_vacancies(): void
    {
        $open = $this->openVacancy();
        $unpublished = JobVacancy::factory()->create(['is_published' => false]);
        $closed = JobVacancy::factory()->create(['is_published' => true, 'closes_at' => now()->subDay()]);

        $response = $this->get('/karir');

        $response->assertSee($open->title);
        $response->assertDontSee($unpublished->title);
        $response->assertDontSee($closed->title);
    }

    public function test_an_unpublished_vacancy_detail_page_is_not_found(): void
    {
        $vacancy = JobVacancy::factory()->create(['is_published' => false]);

        $this->get('/karir/'.$vacancy->slug)->assertNotFound();
    }

    public function test_requirement_line_breaks_are_preserved_on_the_detail_page(): void
    {
        $vacancy = JobVacancy::factory()->create([
            'is_published' => true,
            'requirement' => [
                'id' => "Syarat pertama\nSyarat kedua",
                'en' => "First requirement\nSecond requirement",
            ],
        ]);

        $this->get('/karir/'.$vacancy->slug)
            ->assertOk()
            ->assertSee('Syarat pertama<br', false)
            ->assertSee('Syarat kedua');
    }

    public function test_a_verified_customer_can_apply(): void
    {
        $vacancy = $this->openVacancy();
        $customer = Customer::factory()->create(['name' => 'Budi Santoso']);

        $this->apply($customer, $vacancy, ['cover_letter' => 'Saya tertarik.'])
            ->assertRedirect('/akun/lamaran');

        $application = JobApplication::sole();

        $this->assertSame(JobApplication::STATUS_SUBMITTED, $application->status);
        $this->assertSame('Budi Santoso', $application->applicant_name);
        $this->assertSame('Saya tertarik.', $application->cover_letter);
        $this->assertSame(2, $application->documents()->count());
    }

    public function test_a_portfolio_file_is_saved_as_a_portfolio_document(): void
    {
        $vacancy = $this->openVacancy();
        $customer = Customer::factory()->create();

        $this->apply($customer, $vacancy, [
            'portfolio' => $this->portfolio('portofolio-budi.pdf'),
        ])->assertRedirect('/akun/lamaran');

        $portfolio = JobApplicationDocument::where('type', JobApplicationDocument::TYPE_PORTFOLIO)->sole();

        $this->assertSame('portofolio-budi.pdf', $portfolio->original_filename);
        Storage::disk('applications')->assertExists($portfolio->path);
    }

    public function test_a_non_pdf_portfolio_is_rejected(): void
    {
        $vacancy = $this->openVacancy();

        $this->apply(Customer::factory()->create(), $vacancy, [
            'portfolio' => UploadedFile::fake()->image('portofolio.jpg'),
        ])->assertSessionHasErrors('portfolio');

        $this->assertSame(0, JobApplication::count());
        $this->assertSame(0, JobApplicationDocument::count());
    }

    public function test_both_files_are_required(): void
    {
        $vacancy = $this->openVacancy();

        auth('customer')->login(Customer::factory()->create());

        $this->post('/karir/'.$vacancy->slug.'/lamar', ['cover_letter' => 'Halo'])
            ->assertSessionHasErrors(['cv', 'portfolio']);

        $this->assertSame(0, JobApplication::count());
    }

    public function test_each_upload_boundary_is_enforced(): void
    {
        $vacancy = $this->openVacancy();
        $customer = Customer::factory()->create();

        // Exactly at the limit is allowed.
        $this->apply($customer, $vacancy, [
            'cv' => UploadedFile::fake()->create('cv.pdf', 10240, 'application/pdf'),
            'portfolio' => UploadedFile::fake()->create('portfolio.pdf', 30720, 'application/pdf'),
        ])->assertRedirect('/akun/lamaran');

        $this->assertSame(2, JobApplicationDocument::count());

        // One kilobyte over is not.
        $customer = Customer::factory()->create();

        $this->apply($customer, $vacancy, [
            'cv' => UploadedFile::fake()->create('cv.pdf', 10241, 'application/pdf'),
            'portfolio' => UploadedFile::fake()->create('portfolio.pdf', 30721, 'application/pdf'),
        ])->assertSessionHasErrors(['cv', 'portfolio']);
    }

    public function test_the_upload_fields_render_in_both_locales(): void
    {
        $vacancy = $this->openVacancy();
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')
            ->get('/karir/'.$vacancy->slug.'/lamar')
            ->assertOk()
            ->assertSee('name="cv"', false)
            ->assertSee('name="portfolio"', false)
            ->assertSee(__('careers.cv'))
            ->assertSee(__('careers.portfolio'));

        $this->actingAs($customer, 'customer')
            ->get('/en/karir/'.$vacancy->slug.'/lamar')
            ->assertOk()
            ->assertSee(__('careers.cv', [], 'en'))
            ->assertSee(__('careers.portfolio', [], 'en'));
    }

    public function test_each_file_is_recorded_with_its_document_type(): void
    {
        $this->apply(Customer::factory()->create(), $this->openVacancy(), [
            'cv' => $this->cv('resume.pdf'),
            'portfolio' => $this->portfolio('portfolio.pdf'),
        ]);

        $types = JobApplicationDocument::orderBy('id')->pluck('type')->all();

        $this->assertSame(
            [JobApplicationDocument::TYPE_CV, JobApplicationDocument::TYPE_PORTFOLIO],
            $types,
        );
    }

    public function test_the_file_is_written_to_the_private_disk_with_its_metadata(): void
    {
        $this->apply(Customer::factory()->create(), $this->openVacancy());

        $document = JobApplicationDocument::where('type', JobApplicationDocument::TYPE_CV)->sole();

        Storage::disk('applications')->assertExists($document->path);
        $this->assertSame('applications', $document->disk);
        $this->assertSame('cv.pdf', $document->original_filename);
        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertGreaterThan(0, $document->size_bytes);
        $this->assertNotNull($document->checksum);
    }

    public function test_the_received_notification_is_queued_and_sent(): void
    {
        $customer = Customer::factory()->create();

        $this->apply($customer, $this->openVacancy());

        Notification::assertSentTo($customer, ApplicationReceived::class);

        $this->assertInstanceOf(
            ShouldQueue::class,
            new ApplicationReceived(JobApplication::sole()),
        );
    }

    public function test_a_guest_cannot_apply(): void
    {
        $vacancy = $this->openVacancy();

        $this->post('/karir/'.$vacancy->slug.'/lamar', [
            'cv' => $this->cv(),
            'portfolio' => $this->portfolio(),
        ])->assertRedirect('/masuk');

        $this->assertSame(0, JobApplication::count());
    }

    public function test_an_unverified_customer_cannot_apply(): void
    {
        $vacancy = $this->openVacancy();

        $this->apply(Customer::factory()->unverified()->create(), $vacancy)
            ->assertRedirect('/verifikasi-email');

        $this->assertSame(0, JobApplication::count());
    }

    public function test_a_duplicate_application_is_rejected(): void
    {
        $vacancy = $this->openVacancy();
        $customer = Customer::factory()->create();

        $this->apply($customer, $vacancy);
        $this->apply($customer, $vacancy)->assertSessionHas('error', __('careers.error_already_applied'));

        $this->assertSame(1, JobApplication::count());
    }

    public function test_applying_to_a_closed_vacancy_is_rejected(): void
    {
        $closed = JobVacancy::factory()->create(['is_published' => true, 'closes_at' => now()->subDay()]);

        auth('customer')->login(Customer::factory()->create());

        // The form itself 404s, and the POST is refused even if replayed.
        $this->get('/karir/'.$closed->slug.'/lamar')->assertNotFound();

        $this->post('/karir/'.$closed->slug.'/lamar', [
            'cv' => $this->cv(),
            'portfolio' => $this->portfolio(),
        ])->assertSessionHas('error', __('careers.error_closed'));

        $this->assertSame(0, JobApplication::count());
    }

    public function test_a_withdrawn_application_can_be_resubmitted_and_reuses_the_row(): void
    {
        $vacancy = $this->openVacancy();
        $customer = Customer::factory()->create();

        $this->apply($customer, $vacancy);
        $first = JobApplication::sole();

        $this->delete('/lamaran/'.$first->getKey())->assertRedirect();
        $this->assertTrue($first->fresh()->isWithdrawn());

        $this->apply($customer, $vacancy)->assertRedirect('/akun/lamaran');

        // Reused, not inserted alongside: the unique index has no portable
        // partial form in MySQL.
        $this->assertSame(1, JobApplication::count());
        $this->assertSame($first->getKey(), JobApplication::sole()->getKey());
        $this->assertSame(JobApplication::STATUS_SUBMITTED, JobApplication::sole()->status);
    }

    public function test_resubmitting_replaces_the_previous_documents(): void
    {
        $vacancy = $this->openVacancy();
        $customer = Customer::factory()->create();

        $this->apply($customer, $vacancy, [
            'cv' => $this->cv('old-cv.pdf'),
            'portfolio' => $this->portfolio('old-portfolio.pdf'),
        ]);
        $oldPaths = JobApplicationDocument::pluck('path')->all();

        $this->delete('/lamaran/'.JobApplication::sole()->getKey());
        $this->apply($customer, $vacancy, [
            'cv' => $this->cv('new-cv.pdf'),
            'portfolio' => $this->portfolio('new-portfolio.pdf'),
        ]);

        // The old files are removed rather than left behind as orphans, and
        // only the new pair remains on the record.
        foreach ($oldPaths as $path) {
            Storage::disk('applications')->assertMissing($path);
        }

        $this->assertSame(2, JobApplicationDocument::count());
        $this->assertEqualsCanonicalizing(
            ['new-cv.pdf', 'new-portfolio.pdf'],
            JobApplicationDocument::pluck('original_filename')->all(),
        );
    }

    public function test_internal_notes_never_reach_the_applicant(): void
    {
        $vacancy = $this->openVacancy();
        $customer = Customer::factory()->create();

        $this->apply($customer, $vacancy);

        JobApplication::sole()->forceFill([
            'internal_notes' => 'Kandidat terlalu mahal, jangan lanjut.',
        ])->save();

        $this->get('/akun/lamaran')
            ->assertOk()
            ->assertDontSee('terlalu mahal');

        $this->get('/karir/'.$vacancy->slug)->assertDontSee('terlalu mahal');
    }

    public function test_a_customer_cannot_withdraw_someone_elses_application(): void
    {
        $owner = Customer::factory()->create();
        $attacker = Customer::factory()->create();

        $application = JobApplication::factory()
            ->for($this->openVacancy(), 'vacancy')->for($owner)->create();

        auth('customer')->login($attacker);

        $this->delete('/lamaran/'.$application->getKey())->assertForbidden();

        $this->assertTrue($application->fresh()->isActive());
    }

    public function test_withdrawing_requires_a_delete_not_a_get(): void
    {
        $owner = Customer::factory()->create();
        $application = JobApplication::factory()
            ->for($this->openVacancy(), 'vacancy')->for($owner)->create();

        auth('customer')->login($owner);

        $this->get('/lamaran/'.$application->getKey())->assertStatus(405);
    }

    public function test_my_applications_shows_only_the_signed_in_customers_rows(): void
    {
        $mine = Customer::factory()->create();
        $theirs = Customer::factory()->create();

        $myVacancy = JobVacancy::factory()->create([
            'is_published' => true,
            'title' => ['id' => 'Posisi Saya', 'en' => 'My Position'],
        ]);
        $theirVacancy = JobVacancy::factory()->create([
            'is_published' => true,
            'title' => ['id' => 'Posisi Lain', 'en' => 'Other Position'],
        ]);

        JobApplication::factory()->for($myVacancy, 'vacancy')->for($mine)->create();
        JobApplication::factory()->for($theirVacancy, 'vacancy')->for($theirs)->create();

        auth('customer')->login($mine);

        $this->get('/akun/lamaran')
            ->assertOk()
            ->assertSee('Posisi Saya')
            ->assertDontSee('Posisi Lain');
    }

    public function test_an_orphaned_file_is_swept_only_once_it_is_old_enough(): void
    {
        // M1 layer two. The age floor is the safety mechanism: deleting a file
        // younger than the longest plausible request would race a legitimate
        // upload and destroy a CV mid-submission.
        Storage::disk('applications')->put('applications/99/orphan.pdf', 'stray');

        (new PurgeOrphanedApplicationDocuments)->handle();

        Storage::disk('applications')->assertExists('applications/99/orphan.pdf');

        $this->travel(PurgeOrphanedApplicationDocuments::MIN_AGE_HOURS + 1)->hours();

        (new PurgeOrphanedApplicationDocuments)->handle();

        Storage::disk('applications')->assertMissing('applications/99/orphan.pdf');
    }

    public function test_the_sweep_never_touches_a_file_a_row_points_at(): void
    {
        $this->apply(Customer::factory()->create(), $this->openVacancy());
        $path = JobApplicationDocument::where('type', JobApplicationDocument::TYPE_CV)->sole()->path;

        $this->travel(PurgeOrphanedApplicationDocuments::MIN_AGE_HOURS + 1)->hours();

        (new PurgeOrphanedApplicationDocuments)->handle();

        Storage::disk('applications')->assertExists($path);
    }
}
