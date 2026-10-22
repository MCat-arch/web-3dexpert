<?php

namespace Tests\Feature\Courses;

use App\Actions\Payments\RefundPayment;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\CourseSection;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RefundRevokesEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_refund_revokes_enrollment_and_closes_the_lesson_gate(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $course = Course::factory()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lesson = CourseLesson::factory()->create(['course_section_id' => $section->id]);
        $lesson->setRelation('section', $section);

        $customer = Customer::factory()->create();
        $enrollment = CourseEnrollment::factory()->create([
            'course_id' => $course->id,
            'customer_id' => $customer->id,
            'status' => CourseEnrollment::STATUS_ACTIVE,
        ]);
        $payment = Payment::factory()->settled()->create([
            'payable_type' => CourseEnrollment::class,
            'payable_id' => $enrollment->id,
        ]);

        $this->assertTrue(Gate::forUser($customer)->allows('view', $lesson));

        $refunded = app(RefundPayment::class)($payment);

        $this->assertSame(Payment::STATUS_REFUNDED, $refunded->status);
        $this->assertSame(CourseEnrollment::STATUS_REVOKED, $enrollment->fresh()->status);
        $this->assertFalse(Gate::forUser($customer)->allows('view', $lesson));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'payment.refunded',
            'subject_id' => $payment->id,
        ]);
    }

    public function test_a_second_refund_is_a_no_op(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $enrollment = CourseEnrollment::factory()->create();
        $payment = Payment::factory()->settled()->create([
            'payable_type' => CourseEnrollment::class,
            'payable_id' => $enrollment->id,
        ]);

        app(RefundPayment::class)($payment);
        app(RefundPayment::class)($payment->fresh());

        $this->assertSame(Payment::STATUS_REFUNDED, $payment->fresh()->status);
        $this->assertSame(
            1,
            AuditLog::query()->where('event', 'payment.refunded')->count(),
        );
    }
}
