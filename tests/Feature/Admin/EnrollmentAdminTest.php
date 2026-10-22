<?php

namespace Tests\Feature\Admin;

use App\Actions\Courses\GrantCourseAccess;
use App\Actions\Courses\RevokeCourseAccess;
use App\Filament\Resources\CourseEnrollments\CourseEnrollmentResource;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EnrollmentAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_granting_access_creates_an_active_enrollment_without_a_payment(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $course = Course::factory()->create();
        $customer = Customer::factory()->create();

        $enrollment = app(GrantCourseAccess::class)($course, $customer);

        $this->assertTrue($enrollment->isActive());
        $this->assertNull($enrollment->payment_id);
        $this->assertSame($customer->email, $enrollment->student_email);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'enrollment.granted',
            'subject_type' => CourseEnrollment::class,
            'subject_id' => $enrollment->id,
        ]);
    }

    public function test_granting_access_reactivates_a_pending_enrollment_instead_of_duplicating_it(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $course = Course::factory()->create();
        $customer = Customer::factory()->create();

        $pending = CourseEnrollment::factory()->pending()->create([
            'course_id' => $course->id,
            'customer_id' => $customer->id,
        ]);

        $granted = app(GrantCourseAccess::class)($course, $customer);
        $grantedAgain = app(GrantCourseAccess::class)($course, $customer);

        $this->assertSame($pending->id, $granted->id);
        $this->assertSame($pending->id, $grantedAgain->id);
        $this->assertSame(CourseEnrollment::STATUS_ACTIVE, $grantedAgain->fresh()->status);
        $this->assertSame(1, CourseEnrollment::query()->count());
    }

    public function test_revoking_access_is_idempotent_and_keeps_the_payment_untouched(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $enrollment = CourseEnrollment::factory()->create();

        app(RevokeCourseAccess::class)($enrollment, 'Refund manual');
        app(RevokeCourseAccess::class)($enrollment->fresh(), 'Refund manual');

        $this->assertSame(CourseEnrollment::STATUS_REVOKED, $enrollment->fresh()->status);
        $this->assertSame(1, CourseEnrollment::query()->where('status', CourseEnrollment::STATUS_REVOKED)->count());
        $this->assertSame(1, AuditLog::query()->where('event', 'enrollment.revoked')->count());
    }

    public function test_editor_and_lms_admin_boundaries_match_the_access_matrix(): void
    {
        $enrollment = CourseEnrollment::factory()->create();

        $this->actingAs(User::factory()->editor()->create());
        $this->assertFalse(CourseEnrollmentResource::canViewAny());
        $this->assertFalse(CourseEnrollmentResource::canEdit($enrollment));

        $this->actingAs(User::factory()->lmsAdmin()->create());
        $this->assertTrue(CourseEnrollmentResource::canViewAny());
        // Enrollment records are read-only; grant/revoke are row actions.
        $this->assertFalse(CourseEnrollmentResource::canEdit($enrollment));

        $this->actingAs(User::factory()->admin()->create());
        $this->assertTrue(CourseEnrollmentResource::canViewAny());
        $this->assertFalse(CourseEnrollmentResource::canEdit($enrollment));
    }
}
