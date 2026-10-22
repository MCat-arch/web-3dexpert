<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\LessonCompletion;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;

class LessonCompletionController extends Controller
{
    public function store(Course $course, CourseLesson $lesson): RedirectResponse
    {
        if (! $course->is_published) {
            abort(404);
        }

        $lesson->loadMissing('section');

        if ((int) $lesson->section?->course_id !== (int) $course->getKey()) {
            abort(404);
        }

        $customer = auth('customer')->user();

        $enrollment = CourseEnrollment::query()
            ->where('course_id', $course->getKey())
            ->where('customer_id', $customer->getKey())
            ->active()
            ->first();

        abort_unless($enrollment, 403);

        $completion = LessonCompletion::query()->firstOrCreate(
            [
                'course_enrollment_id' => $enrollment->getKey(),
                'course_lesson_id' => $lesson->getKey(),
            ],
            ['completed_at' => now()],
        );

        if ($completion->wasRecentlyCreated) {
            app(AuditLogger::class)->log(
                'lesson.completed',
                $lesson,
                [],
                ['course_enrollment_id' => $enrollment->getKey()],
            );
        }

        $enrollment->markCompletedIfFinished();

        return back()->with('status', __('courses.lesson_completed'));
    }
}
