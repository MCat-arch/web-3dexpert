<?php

namespace App\Actions\Courses;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Customer;
use App\Models\Payment;
use App\Notifications\Courses\CourseEnrollmentActivated;
use App\Notifications\Courses\CourseRegistrationReceived;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StartCourseRegistration
{
    /**
     * Start registration/checkout for a course.
     *
     * @return array{enrollment: CourseEnrollment, payment: ?Payment}
     */
    public function __invoke(
        Course $course,
        Customer $customer,
        string $paymentMethod = 'qris',
        ?string $studentName = null,
        ?string $studentPhone = null
    ): array {
        if (! $course->is_published) {
            throw new InvalidArgumentException(__('courses.not_published'));
        }

        // Entitlement pre-check: prevent buying a course already owned
        $existing = CourseEnrollment::query()
            ->where('course_id', $course->id)
            ->where('customer_id', $customer->id)
            ->first();

        if ($existing && $existing->isActive()) {
            throw new InvalidArgumentException(__('courses.already_enrolled'));
        }

        return DB::transaction(function () use ($course, $customer, $paymentMethod, $studentName, $studentPhone, $existing) {
            $enrollment = $existing ?? new CourseEnrollment;
            $enrollment->course_id = $course->id;
            $enrollment->customer_id = $customer->id;
            $enrollment->student_name = $studentName ?: $customer->name;
            $enrollment->student_email = $customer->email;
            $enrollment->student_phone = $studentPhone ?: $customer->phone;

            if ($course->isFree()) {
                $enrollment->status = CourseEnrollment::STATUS_ACTIVE;
                $enrollment->activated_at = now();
                $enrollment->save();

                DB::afterCommit(fn () => $customer->notify(
                    new CourseEnrollmentActivated($enrollment->fresh(['course']))
                ));

                return [
                    'enrollment' => $enrollment,
                    'payment' => null,
                ];
            }

            // Create Pending Payment record
            $orderId = 'CRS-'.date('Ymd').'-'.strtoupper(Str::random(6));

            $payment = Payment::create([
                'payable_type' => CourseEnrollment::class,
                'payable_id' => 0, // temporary, updated right after
                'order_id' => $orderId,
                'gross_amount' => $course->price,
                'status' => Payment::STATUS_PENDING,
                'payment_type' => $paymentMethod,
                'expires_at' => now()->addHours(24),
            ]);

            try {
                $enrollment->payment_id = $payment->id;
                $enrollment->status = CourseEnrollment::STATUS_PENDING;
                $enrollment->save();
            } catch (UniqueConstraintViolationException $e) {
                // In case of double-click race, reload existing
                $enrollment = CourseEnrollment::where('course_id', $course->id)
                    ->where('customer_id', $customer->id)
                    ->firstOrFail();

                $enrollment->payment_id = $payment->id;
                $enrollment->save();
            }

            // Bind polymorphic relationship back
            $payment->update([
                'payable_id' => $enrollment->id,
            ]);

            DB::afterCommit(fn () => $customer->notify(
                new CourseRegistrationReceived($enrollment->fresh(['course', 'payment']))
            ));

            return [
                'enrollment' => $enrollment,
                'payment' => $payment,
            ];
        });
    }
}
