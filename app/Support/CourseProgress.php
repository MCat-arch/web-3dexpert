<?php

namespace App\Support;

use App\Models\CourseEnrollment;
use Illuminate\Support\Carbon;

/**
 * Single source of truth for course progress math.
 *
 * Total lessons come from the course, completed lessons from the enrollment.
 * Zero-lesson courses are 0%, never a division by zero.
 */
class CourseProgress
{
    /**
     * @return array{completed: int, total: int, percent: int, last_completed_at: ?Carbon}
     */
    public static function for(CourseEnrollment $enrollment): array
    {
        $course = $enrollment->relationLoaded('course')
            ? $enrollment->course
            : $enrollment->course()->withCount('lessons')->first();

        $total = 0;

        if ($course) {
            if (array_key_exists('lessons_count', $course->getAttributes())) {
                $total = (int) $course->getAttribute('lessons_count');
            } else {
                $total = (int) $course->lessons()->count();
            }
        }

        $completed = array_key_exists('completions_count', $enrollment->getAttributes())
            ? (int) $enrollment->getAttribute('completions_count')
            : (int) $enrollment->completions()->count();
        $percent = $total > 0 ? (int) min(100, round(($completed / $total) * 100)) : 0;

        $last = array_key_exists('completions_max_completed_at', $enrollment->getAttributes())
            ? $enrollment->getAttribute('completions_max_completed_at')
            : $enrollment->completions()->max('completed_at');

        return [
            'completed' => $completed,
            'total' => $total,
            'percent' => $percent,
            'last_completed_at' => $last ? Carbon::parse($last) : null,
        ];
    }
}
