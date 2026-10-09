<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
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

        $course->loadMissing(['sections.lessons']);

        $customer = auth('customer')->user();
        $isEnrolled = false;
        if ($customer) {
            $isEnrolled = CourseEnrollment::where('course_id', $course->id)
                ->where('customer_id', $customer->id)
                ->active()
                ->exists();
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

        $sectionsPayload = $course->sections->map(function ($section) use ($isEnrolled) {
            return [
                'id' => $section->id,
                'title' => $section->title,
                'lessons' => $section->lessons->map(function ($l) use ($isEnrolled) {
                    return LessonPayload::for($l, $isEnrolled || $l->is_free_preview);
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
        ]);
    }
}
