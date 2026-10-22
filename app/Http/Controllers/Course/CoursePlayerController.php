<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Support\CourseProgress;
use App\Support\LessonPayload;
use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CoursePlayerController extends Controller
{
    public function show(Course $course, ?CourseLesson $lesson = null): View|RedirectResponse
    {
        if (! $course->is_published) {
            abort(404);
        }

        $course->loadMissing(['sections.lessons.attachmentMedia'])->loadCount('lessons');

        $customer = auth('customer')->user();
        $enrollment = null;
        $isEnrolled = false;
        $completedLessonIds = [];
        $progress = null;
        $assignments = collect();

        if ($customer) {
            $enrollment = CourseEnrollment::query()
                ->where('course_id', $course->getKey())
                ->where('customer_id', $customer->getKey())
                ->first();

            $isEnrolled = $enrollment?->isActive() ?? false;

            if ($enrollment) {
                $completedLessonIds = $enrollment->completions()
                    ->pluck('course_lesson_id')
                    ->all();

                $enrollment->setRelation('course', $course);
                $progress = CourseProgress::for($enrollment);
            }
        }

        // Find selected lesson or fall back to first lesson
        $currentLesson = $lesson;
        if (! $currentLesson) {
            $firstSection = $course->sections->first();
            $currentLesson = $firstSection ? $firstSection->lessons->first() : null;
        }

        if (! $currentLesson) {
            return redirect(Locale::route('courses.show', ['course' => $course->slug]))
                ->with('error', __('courses.no_lessons'));
        }

        // Gate check
        $canView = Gate::forUser($customer)->allows('view', $currentLesson);
        if (! $canView) {
            return redirect(Locale::route('courses.checkout.show', ['course' => $course->slug]))
                ->with('error', __('courses.locked_lesson_prompt'));
        }

        if ($isEnrolled) {
            $assignments = Assignment::query()
                ->where('course_lesson_id', $currentLesson->getKey())
                ->published()
                ->with([
                    'submissions' => fn ($query) => $query->where('customer_id', $customer->getKey()),
                ])
                ->orderBy('due_at')
                ->get();
        }

        $sectionsPayload = $course->sections->map(function ($section) use ($isEnrolled, $completedLessonIds) {
            return [
                'id' => $section->id,
                'title' => $section->title,
                'lessons' => $section->lessons->map(function ($lesson) use ($isEnrolled, $completedLessonIds) {
                    return LessonPayload::for($lesson, $isEnrolled || $lesson->is_free_preview) + [
                        'completed' => in_array($lesson->getKey(), $completedLessonIds, true),
                    ];
                }),
            ];
        });

        $lessonPayload = LessonPayload::for($currentLesson, true);

        return view('courses.player', [
            'course' => $course,
            'currentLesson' => $currentLesson,
            'lessonPayload' => $lessonPayload,
            'sectionsPayload' => $sectionsPayload,
            'isEnrolled' => $isEnrolled,
            'customer' => $customer,
            'enrollment' => $enrollment,
            'progress' => $progress,
            'completedLessonIds' => $completedLessonIds,
            'assignments' => $assignments,
        ]);
    }
}
