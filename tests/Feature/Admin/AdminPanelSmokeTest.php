<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Assignments\AssignmentResource;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\CourseEnrollments\CourseEnrollmentResource;
use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\CourseSections\CourseSectionResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\JobVacancies\JobVacancyResource;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\CourseEnrollment;
use App\Models\Customer;
use App\Models\MediaAsset;
use App\Models\Payment;
use App\Models\User;
use App\Support\AuditLogger;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every Filament page has to survive a real request, not just route:list.
 *
 * These tests were added because a resource can be registered correctly and
 * still 500 on render (lazy-loading guard, bad infolist relation, a page class
 * that was never created). They also pin the role boundaries from the access
 * matrix.
 */
class AdminPanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    private function assertIndex(User $user, string $resource): void
    {
        $this->actingAs($user)
            ->get($resource::getUrl('index'))
            ->assertOk();
    }

    private function assertForbiddenIndex(User $user, string $resource): void
    {
        $this->actingAs($user)
            ->get($resource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_superadmin_can_open_every_privileged_resource(): void
    {
        $superadmin = $this->staff(User::ROLE_SUPERADMIN);

        foreach ([
            CourseResource::class,
            CourseSectionResource::class,
            AssignmentResource::class,
            CourseEnrollmentResource::class,
            PaymentResource::class,
            CustomerResource::class,
            JobVacancyResource::class,
            MediaAssetResource::class,
            AuditLogResource::class,
            EventResource::class,
        ] as $resource) {
            $this->assertIndex($superadmin, $resource);
        }
    }

    public function test_admin_can_open_business_resources_but_not_staff_management(): void
    {
        $admin = $this->staff(User::ROLE_ADMIN);

        foreach ([
            CourseResource::class,
            CourseSectionResource::class,
            AssignmentResource::class,
            CourseEnrollmentResource::class,
            PaymentResource::class,
            CustomerResource::class,
            JobVacancyResource::class,
            MediaAssetResource::class,
            AuditLogResource::class,
        ] as $resource) {
            $this->assertIndex($admin, $resource);
        }

        $this->assertForbiddenIndex($admin, UserResource::class);
    }

    public function test_editor_cannot_enter_the_lms_business_or_hiring_areas(): void
    {
        $editor = $this->staff(User::ROLE_EDITOR);

        $this->assertIndex($editor, EventResource::class);
        $this->assertIndex($editor, MediaAssetResource::class);

        foreach ([
            CourseResource::class,
            CourseSectionResource::class,
            AssignmentResource::class,
            CourseEnrollmentResource::class,
            PaymentResource::class,
            CustomerResource::class,
            JobVacancyResource::class,
            AuditLogResource::class,
            UserResource::class,
        ] as $resource) {
            $this->assertForbiddenIndex($editor, $resource);
        }
    }

    public function test_lms_admin_can_only_open_learning_resources(): void
    {
        $lmsAdmin = $this->staff(User::ROLE_LMS_ADMIN);

        foreach ([
            CourseResource::class,
            CourseSectionResource::class,
            AssignmentResource::class,
            CourseEnrollmentResource::class,
            MediaAssetResource::class,
        ] as $resource) {
            $this->assertIndex($lmsAdmin, $resource);
        }

        foreach ([
            PaymentResource::class,
            CustomerResource::class,
            JobVacancyResource::class,
            AuditLogResource::class,
            EventResource::class,
            UserResource::class,
        ] as $resource) {
            $this->assertForbiddenIndex($lmsAdmin, $resource);
        }
    }

    public function test_lms_admin_can_render_enrollment_and_assignment_pages(): void
    {
        $lmsAdmin = $this->staff(User::ROLE_LMS_ADMIN);

        $enrollment = CourseEnrollment::factory()->create();
        $assignment = Assignment::factory()->create();

        $this->actingAs($lmsAdmin)
            ->get(CourseEnrollmentResource::getUrl('view', ['record' => $enrollment]))
            ->assertOk()
            ->assertSee($enrollment->student_name);

        $this->actingAs($lmsAdmin)
            ->get(AssignmentResource::getUrl('edit', ['record' => $assignment]))
            ->assertOk();
    }

    public function test_admin_can_render_customer_payment_media_and_audit_pages(): void
    {
        $admin = $this->staff(User::ROLE_ADMIN);

        $customer = Customer::factory()->create();
        $enrollment = CourseEnrollment::factory()->create(['customer_id' => $customer->id]);
        $payment = Payment::factory()->settled()->create([
            'payable_type' => CourseEnrollment::class,
            'payable_id' => $enrollment->id,
        ]);
        $media = MediaAsset::query()->create([
            'path' => 'images/smoke-test.png',
            'file_name' => 'smoke-test.png',
            'mime_type' => 'image/png',
            'size' => 1234,
            'sha256' => str_repeat('a', 64),
            'storage_path' => 'images/smoke-test.png',
            'content' => '',
        ]);

        app(AuditLogger::class)->log('smoke.created', $customer);
        $audit = AuditLog::query()->where('event', 'smoke.created')->sole();

        $this->actingAs($admin)
            ->get(CustomerResource::getUrl('view', ['record' => $customer]))
            ->assertOk()
            ->assertSee($customer->email);

        $this->actingAs($admin)
            ->get(PaymentResource::getUrl('view', ['record' => $payment]))
            ->assertOk()
            ->assertSee($payment->order_id);

        $this->actingAs($admin)
            ->get(MediaAssetResource::getUrl('view', ['record' => $media]))
            ->assertOk()
            ->assertSee('smoke-test.png');

        $this->actingAs($admin)
            ->get(AuditLogResource::getUrl('view', ['record' => $audit]))
            ->assertOk()
            ->assertSee('smoke.created');
    }

    public function test_default_action_labels_are_indonesian(): void
    {
        $this->assertSame('Lihat', ViewAction::make()->getLabel());
        $this->assertSame('Ubah', EditAction::make()->getLabel());
        $this->assertSame('Hapus', DeleteAction::make()->getLabel());
        $this->assertSame('Tambah', CreateAction::make()->getLabel());
        $this->assertSame('Hapus yang dipilih', DeleteBulkAction::make()->getLabel());
        $this->assertSame('Pulihkan', RestoreAction::make()->getLabel());
    }

    public function test_event_form_does_not_render_legacy_english_inputs(): void
    {
        $editor = $this->staff(User::ROLE_EDITOR);

        $this->actingAs($editor)
            ->get(EventResource::getUrl('create'))
            ->assertOk()
            ->assertDontSee('Kustomisasi Bahasa Inggris')
            ->assertDontSee('Title (English)');
    }
}
