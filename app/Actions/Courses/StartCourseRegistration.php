<?php

namespace App\Actions\Courses;

use App\Actions\Payments\ExpirePayment;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Customer;
use App\Models\Payment;
use App\Notifications\Courses\CourseEnrollmentActivated;
use App\Notifications\Courses\CourseRegistrationReceived;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
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

        // Entitlement pre-check: prevent buying a course already owned.
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

            /*
             * Reuse an unexpired pending payment. Otherwise a student who
             * refreshes checkout would orphan the first order and create a
             * second QR for the same course.
             */
            if ($existing && $existing->isPending()) {
                $existing->loadMissing('payment');
                $pending = $existing->payment;

                if ($pending && $pending->isDueForExpiry()) {
                    app(ExpirePayment::class)($pending);
                    $pending = null;
                }

                if ($pending && $pending->isPending()) {
                    $enrollment->save();

                    return [
                        'enrollment' => $enrollment,
                        'payment' => $pending,
                    ];
                }
            }

            $enrollment->status = CourseEnrollment::STATUS_PENDING;
            $enrollment->activated_at = null;

            try {
                $enrollment->save();
            } catch (UniqueConstraintViolationException) {
                // A double-click raced us. Re-read the winner and attach the
                // new payment to that row rather than failing with a 500.
                $enrollment = CourseEnrollment::where('course_id', $course->id)
                    ->where('customer_id', $customer->id)
                    ->firstOrFail();
            }

            $payment = Payment::create([
                'payable_type' => CourseEnrollment::class,
                'payable_id' => $enrollment->getKey(),
                'order_id' => Payment::generateOrderId('CRS'),
                'gross_amount' => $course->price,
                'status' => Payment::STATUS_PENDING,
                'payment_type' => $paymentMethod,
                'expires_at' => now()->addMinutes((int) config('payments.expiry_minutes', 15)),
            ]);

            $enrollment->payment_id = $payment->getKey();
            $enrollment->save();

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
