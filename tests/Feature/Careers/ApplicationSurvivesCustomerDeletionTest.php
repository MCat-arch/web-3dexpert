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
 * The user's stated requirement, verbatim:
 *
 *   "i need the admin to be able to access it anytime even if the user logout
 *    or already deleted"
 *
 * That is a LIFECYCLE constraint, not a storage one, and it is satisfied by
 * making the document belong to the application rather than to the account:
 * customer_id is nullOnDelete (never cascade), and applicant identity is
 * snapshotted at submission.
 *
 * These tests are the acceptance criteria for that sentence.
 */
class ApplicationSurvivesCustomerDeletionTest extends TestCase
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

    private function submitApplication(Customer $customer): JobApplication
    {
        $vacancy = JobVacancy::factory()->create(['is_published' => true, 'closes_at' => null]);

        auth('customer')->login($customer);

        $this->post('/karir/'.$vacancy->slug.'/lamar', [
            'cover_letter' => 'Saya tertarik dengan posisi ini.',
            'documents' => [UploadedFile::fake()->create('cv.pdf', 200, 'application/pdf')],
        ])->assertRedirect();

        auth('customer')->logout();

        return JobApplication::sole();
    }

    public function test_the_application_remains_readable_after_a_soft_delete(): void
    {
        $customer = Customer::factory()->create(['name' => 'Budi Santoso']);
        $application = $this->submitApplication($customer);

        // Staff "deleting" a customer is a SOFT delete, so the row stays and
        // the foreign key never fires -- customer_id is still set. The
        // application is readable either way; which of the two mechanisms
        // keeps it readable depends on how the account was removed.
        $customer->delete();

        $fresh = JobApplication::find($application->getKey());

        $this->assertNotNull($fresh, 'Deleting the account must not delete the application.');
        $this->assertSame($customer->getKey(), $fresh->customer_id);
        $this->assertSame('Budi Santoso', $fresh->applicant_name);
    }

    public function test_a_hard_delete_nulls_the_link_rather_than_cascading(): void
    {
        $customer = Customer::factory()->create();
        $application = $this->submitApplication($customer);

        // This is the case nullOnDelete exists for. Cascade here would
        // destroy the hiring record along with the account.
        $customer->forceDelete();

        $fresh = JobApplication::find($application->getKey());

        $this->assertNotNull($fresh, 'A hard delete must not cascade to the application.');
        $this->assertNull($fresh->customer_id);
    }

    public function test_the_snapshotted_identity_survives_deletion(): void
    {
        // Without the snapshot, a nulled customer_id would leave an
        // application nobody can identify.
        $customer = Customer::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.test',
            'phone' => '081234567890',
        ]);

        $application = $this->submitApplication($customer);

        $customer->forceDelete();

        $fresh = JobApplication::find($application->getKey());

        $this->assertSame('Budi Santoso', $fresh->applicant_name);
        $this->assertSame('budi@example.test', $fresh->applicant_email);
        $this->assertSame('081234567890', $fresh->applicant_phone);
        $this->assertTrue($fresh->applicantAccountDeleted());
    }

    public function test_the_documents_survive_deletion_and_remain_downloadable_by_staff(): void
    {
        $customer = Customer::factory()->create();
        $application = $this->submitApplication($customer);

        $document = $application->documents()->sole();
        $path = $document->path;

        $customer->forceDelete();

        // The row survives...
        $this->assertNotNull(JobApplicationDocument::find($document->getKey()));
        // ...and so does the file.
        Storage::disk('applications')->assertExists($path);

        // And staff can still fetch it.
        $this->actingAs($this->staff(), 'web')
            ->get('/dokumen-lamaran/'.$document->getKey())
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_the_cover_letter_survives_deletion(): void
    {
        $customer = Customer::factory()->create();
        $application = $this->submitApplication($customer);

        $customer->forceDelete();

        $this->assertSame(
            'Saya tertarik dengan posisi ini.',
            JobApplication::find($application->getKey())->cover_letter,
        );
    }

    public function test_deleting_the_vacancy_does_remove_its_applications(): void
    {
        // Deliberately the opposite rule: a vacancy is the parent of its
        // applications, so cascade is correct there. Asserted so the
        // difference between the two foreign keys stays intentional.
        $customer = Customer::factory()->create();
        $application = $this->submitApplication($customer);

        $application->vacancy->forceDelete();

        $this->assertNull(JobApplication::find($application->getKey()));
    }
}
