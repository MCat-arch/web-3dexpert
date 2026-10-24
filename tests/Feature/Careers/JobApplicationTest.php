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

    private function apply(Customer $customer, JobVacancy $vacancy, array $payload = [])
    {
        auth('customer')->login($customer);

        return $this->post('/karir/'.$vacancy->slug.'/lamar', $payload + [
            'documents' => [$this->cv()],
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
        $this->assertSame(1, $application->documents()->count());
    }

    public function test_a_portfolio_link_is_saved_with_the_application(): void
    {
        $vacancy = $this->openVacancy();
        $customer = Customer::factory()->create();

        $this->apply($customer, $vacancy, [
            'cover_letter' => 'Saya tertarik.',
            'portfolio_url' => 'https://www.behance.net/budi',
        ])->assertRedirect('/akun/lamaran');

        $this->assertSame(
            'https://www.behance.net/budi',
            JobApplication::sole()->portfolio_url,
        );
    }

    public function test_a_non_http_portfolio_link_is_rejected(): void
    {
        $vacancy = $this->openVacancy();

        $this->apply(Customer::factory()->create(), $vacancy, [
            'portfolio_url' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('portfolio_url');

        $this->assertSame(0, JobApplication::count());
    }

    public function test_the_portfolio_field_renders_in_both_locales(): void
    {
        $vacancy = $this->openVacancy();
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')
            ->get('/karir/'.$vacancy->slug.'/lamar')
            ->assertOk()
            ->assertSee('name="portfolio_url"', false)
            ->assertSee(__('careers.portfolio_url'));

        $this->actingAs($customer, 'customer')
            ->get('/en/karir/'.$vacancy->slug.'/lamar')
            ->assertOk()
            ->assertSee(__('careers.portfolio_url', [], 'en'));
    }

    public function test_the_first_document_is_recorded_as_the_cv(): void
    {
        $this->apply(Customer::factory()->create(), $this->openVacancy(), [
            'documents' => [$this->cv('resume.pdf'), $this->cv('portfolio.pdf')],
        ]);

        $types = JobApplicationDocument::orderBy('id')->pluck('type')->all();

        $this->assertSame(
            [JobApplicationDocument::TYPE_CV, JobApplicationDocument::TYPE_OTHER],
            $types,
        );
    }

    public function test_the_file_is_written_to_the_private_disk_with_its_metadata(): void
    {
        $this->apply(Customer::factory()->create(), $this->openVacancy());

        $document = JobApplicationDocument::sole();

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

        $this->post('/karir/'.$vacancy->slug.'/lamar', ['documents' => [$this->cv()]])
            ->assertRedirect('/masuk');

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

        $this->post('/karir/'.$closed->slug.'/lamar', ['documents' => [$this->cv()]])
            ->assertSessionHas('error', __('careers.error_closed'));

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

        $this->apply($customer, $vacancy, ['documents' => [$this->cv('old.pdf')]]);
        $oldPath = JobApplicationDocument::sole()->path;

        $this->delete('/lamaran/'.JobApplication::sole()->getKey());
        $this->apply($customer, $vacancy, ['documents' => [$this->cv('new.pdf')]]);

        // The old file is removed rather than left behind as an orphan, and
        // only the new document remains on the record.
        Storage::disk('applications')->assertMissing($oldPath);
        $this->assertSame(1, JobApplicationDocument::count());
        $this->assertSame('new.pdf', JobApplicationDocument::sole()->original_filename);
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
        $path = JobApplicationDocument::sole()->path;

        $this->travel(PurgeOrphanedApplicationDocuments::MIN_AGE_HOURS + 1)->hours();

        (new PurgeOrphanedApplicationDocuments)->handle();

        Storage::disk('applications')->assertExists($path);
    }
}
