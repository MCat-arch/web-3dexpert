<?php

namespace App\Actions\Courses;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Customer;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Staff/Grant access path. Never creates a Payment.
 *
 * The unique (course_id, customer_id) index makes this safe to call twice; a
 * pending or revoked row is reactivated rather than duplicated.
 */
class GrantCourseAccess
{
    public function __invoke(Course $course, Customer $customer, ?string $reason = null): CourseEnrollment
    {
        return DB::transaction(function () use ($course, $customer, $reason) {
            $enrollment = CourseEnrollment::query()->firstOrCreate(
                [
                    'course_id' => $course->getKey(),
                    'customer_id' => $customer->getKey(),
                ],
                [
                    'student_name' => $customer->name,
                    'student_email' => $customer->email,
                    'student_phone' => $customer->phone,
                    'status' => CourseEnrollment::STATUS_ACTIVE,
                    'enrolled_at' => now(),
                    'activated_at' => now(),
                ],
            );

            if (! $enrollment->wasRecentlyCreated) {
                $enrollment->forceFill([
                    'student_name' => $customer->name,
                    'student_email' => $customer->email,
                    'student_phone' => $customer->phone,
                    'status' => CourseEnrollment::STATUS_ACTIVE,
                    'activated_at' => $enrollment->activated_at ?? now(),
                ])->save();
            }

            app(AuditLogger::class)->log(
                'enrollment.granted',
                $enrollment,
                [],
                [
                    'course_id' => $course->getKey(),
                    'customer_id' => $customer->getKey(),
                    'status' => CourseEnrollment::STATUS_ACTIVE,
                ],
                null,
                filled($reason) ? 'Akses diberikan: '.$reason : 'Akses diberikan.',
            );

            return $enrollment;
        });
    }
}
