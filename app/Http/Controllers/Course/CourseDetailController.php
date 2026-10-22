<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Support\CourseProgress;
use App\Support\LessonPayload;
use Illuminate\Contracts\View\View;

class CourseDetailController extends Controller
{
    public function __invoke(Course $course): View
    {
        if (! $course->is_published) {
            abort(404);
        }

        $course->loadMissing(['coverMedia', 'lessons', 'sections.lessons.attachmentMedia'])->loadCount('lessons');

        $customer = auth('customer')->user();
        $isEnrolled = false;
        $enrollment = null;
        $progress = null;
        $completedLessonIds = [];

        if ($customer) {
            $enrollment = CourseEnrollment::query()
                ->where('course_id', $course->getKey())
                ->where('customer_id', $customer->getKey())
                ->first();

            $isEnrolled = $enrollment?->isActive() ?? false;

            if ($enrollment) {
                $enrollment->loadMissing('completions');
                $completedLessonIds = $enrollment->completions
                    ->pluck('course_lesson_id')
                    ->all();

                $enrollment->setRelation('course', $course);
                $progress = CourseProgress::for($enrollment);
            }
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

        // Find the first free preview lesson (or first lesson if enrolled)
        $previewLesson = null;
        foreach ($sectionsPayload as $sec) {
            foreach ($sec['lessons'] as $les) {
                if ($les['is_free_preview'] || $isEnrolled) {
                    $previewLesson = $les;
                    break 2;
                }
            }
        }

        return view('courses.show', [
            'course' => $course,
            'sectionsPayload' => $sectionsPayload,
            'isEnrolled' => $isEnrolled,
            'enrollment' => $enrollment,
            'progress' => $progress,
            'previewLesson' => $previewLesson,
        ]);
    }
}
