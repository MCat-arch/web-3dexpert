<?php

namespace Tests\Feature\Careers;

use App\Filament\Resources\JobApplications\JobApplicationResource;
use App\Models\Customer;
use App\Models\JobApplication;
use App\Models\JobApplicationDocument;
use App\Models\JobVacancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Smoke tests for the staff side of P6. Without these, the first evidence of a
 * misused Filament API would be a 500 in the admin panel -- and the hiring
 * pipeline is unusable if staff cannot open an application.
 */
class CareerAdminTest extends TestCase
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

    private function application(): JobApplication
    {
        $application = JobApplication::factory()
            ->for(JobVacancy::factory()->create(['is_published' => true]), 'vacancy')
            ->for(Customer::factory()->create())
            ->create();

        JobApplicationDocument::factory()->for($application, 'application')->create();

        return $application;
    }

    public function test_staff_can_open_the_application_list(): void
    {
        $this->application();

        $this->actingAs($this->staff(), 'web')
            ->get(JobApplicationResource::getUrl('index'))
            ->assertOk();
    }

    public function test_staff_can_open_an_application(): void
    {
        $application = $this->application();

        $this->actingAs($this->staff(), 'web')
            ->get(JobApplicationResource::getUrl('view', ['record' => $application]))
            ->assertOk();
    }

    public function test_staff_can_open_an_application_whose_account_was_deleted(): void
    {
        // The case the whole design exists for -- the infolist reads the
        // snapshot, so it must render with no customer relation at all.
        $application = $this->application();
        $application->customer->forceDelete();

        $this->actingAs($this->staff(), 'web')
            ->get(JobApplicationResource::getUrl('view', ['record' => $application->fresh()]))
            ->assertOk()
            ->assertSee($application->applicant_name);
    }

    public function test_applications_cannot_be_created_by_staff(): void
    {
        // They come from the public flow, which snapshots identity and writes
        // documents. A hand-made row would have neither.
        $this->assertFalse(JobApplicationResource::canCreate());
    }

    public function test_a_customer_cannot_reach_the_application_admin(): void
    {
        $response = $this->actingAs(Customer::factory()->create(), 'customer')
            ->get(JobApplicationResource::getUrl('index'));

        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function test_a_guest_cannot_reach_the_application_admin(): void
    {
        $this->get(JobApplicationResource::getUrl('index'))->assertRedirect();
    }
}
