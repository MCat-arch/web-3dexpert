<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Support\LessonPayload;
use Illuminate\Contracts\View\View;

class CourseDetailController extends Controller
{
    public function __invoke(Course $course): View
    {
        if (! $course->is_published) {
            abort(404);
        }

        $course->loadMissing(['coverMedia', 'lessons', 'sections.lessons.attachmentMedia']);

        $customer = auth('customer')->user();
        $isEnrolled = false;
        $enrollment = null;

        if ($customer) {
            $enrollment = CourseEnrollment::where('course_id', $course->id)
                ->where('customer_id', $customer->id)
                ->first();
            $isEnrolled = $enrollment && $enrollment->isActive();
        }

        $sectionsPayload = $course->sections->map(function ($section) use ($isEnrolled) {
            return [
                'id' => $section->id,
                'title' => $section->title,
                'lessons' => $section->lessons->map(function ($lesson) use ($isEnrolled) {
                    return LessonPayload::for($lesson, $isEnrolled || $lesson->is_free_preview);
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
            'previewLesson' => $previewLesson,
        ]);
    }
}
