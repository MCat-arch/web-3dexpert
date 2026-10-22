<?php

namespace Tests\Feature\Admin;

use App\Actions\Courses\GrantCourseAccess;
use App\Actions\Courses\RevokeCourseAccess;
use App\Filament\Resources\CourseEnrollments\CourseEnrollmentResource;
use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LmsAdminRoleTest extends TestCase
{
    use RefreshDatabase;

    private function lmsAdmin(): User
    {
        return User::factory()->lmsAdmin()->create();
    }

    public function test_lms_admin_can_enter_the_admin_panel(): void
    {
        $this->actingAs($this->lmsAdmin())
            ->get('/admin')
            ->assertOk();
    }

    public function test_lms_admin_can_open_learning_resources_but_not_finance_or_customers(): void
    {
        $lmsAdmin = $this->lmsAdmin();
        $this->actingAs($lmsAdmin);

        $this->assertTrue(CourseResource::canViewAny());
        $this->assertTrue(CourseEnrollmentResource::canViewAny());
        $this->assertFalse(PaymentResource::canViewAny());
        $this->assertFalse(CustomerResource::canViewAny());

        $this->assertTrue($lmsAdmin->canAccessArea(User::AREA_CURRICULUM));
        $this->assertTrue($lmsAdmin->canAccessArea(User::AREA_ENROLLMENTS));
        $this->assertTrue($lmsAdmin->canAccessArea(User::AREA_ASSIGNMENTS));
        $this->assertFalse($lmsAdmin->canAccessArea(User::AREA_PAYMENTS));
        $this->assertFalse($lmsAdmin->canAccessArea(User::AREA_CUSTOMERS));
        $this->assertFalse($lmsAdmin->canAccessArea(User::AREA_HIRING));
        $this->assertFalse($lmsAdmin->canAccessArea(User::AREA_USERS));
        $this->assertFalse($lmsAdmin->canAccessArea(User::AREA_AUDIT));
    }

    public function test_lms_admin_can_grant_and_revoke_enrollment_access(): void
    {
        $lmsAdmin = $this->lmsAdmin();
        $this->actingAs($lmsAdmin);

        $course = Course::factory()->create();
        $customer = Customer::factory()->create();

        $enrollment = app(GrantCourseAccess::class)($course, $customer, 'Hadiah dari tim LMS');

        $this->assertSame(CourseEnrollment::STATUS_ACTIVE, $enrollment->status);
        $this->assertNull($enrollment->payment_id);
        $this->assertDatabaseHas('course_enrollments', [
            'id' => $enrollment->id,
            'status' => CourseEnrollment::STATUS_ACTIVE,
            'payment_id' => null,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'enrollment.granted',
            'subject_id' => $enrollment->id,
        ]);

        app(RevokeCourseAccess::class)($enrollment, 'Permintaan siswa');

        $this->assertSame(CourseEnrollment::STATUS_REVOKED, $enrollment->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'enrollment.revoked',
            'subject_id' => $enrollment->id,
        ]);
    }

    public function test_lms_admin_can_author_courses_but_cannot_delete_them_or_change_price(): void
    {
        $lmsAdmin = $this->lmsAdmin();
        $this->actingAs($lmsAdmin);

        $course = Course::factory()->create(['price' => 500000]);

        $this->assertTrue(CourseResource::canEdit($course));
        $this->assertFalse(CourseResource::canDelete($course));
        $this->assertFalse(CourseResource::canDeleteAny());

        $this->assertFalse($lmsAdmin->canAccessArea(User::AREA_PAYMENTS));
    }
}
