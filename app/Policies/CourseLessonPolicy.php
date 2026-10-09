<?php

namespace App\Policies;

use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\Customer;

class CourseLessonPolicy
{
    /**
     * Determine whether the customer can view the lesson content.
     * Free preview is accessible to everyone (including guests).
     * Otherwise, requires an active enrollment in the course.
     */
    public function view(?Customer $customer, CourseLesson $lesson): bool
    {
        if ($lesson->is_free_preview) {
            return true;
        }

        if ($customer === null) {
            return false;
        }

        return CourseEnrollment::query()
            ->where('course_id', $lesson->section->course_id)
            ->where('customer_id', $customer->id)
            ->active()
            ->exists();
    }
}
