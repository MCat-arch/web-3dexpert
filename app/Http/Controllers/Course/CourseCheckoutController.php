<?php

namespace App\Http\Controllers\Course;

use App\Actions\Courses\StartCourseRegistration;
use App\Http\Controllers\Controller;
use App\Http\Requests\Course\CourseCheckoutRequest;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CourseCheckoutController extends Controller
{
    public function show(Course $course): View|RedirectResponse
    {
        if (! $course->is_published) {
            abort(404);
        }

        $customer = auth('customer')->user();
        $existing = CourseEnrollment::where('course_id', $course->id)
            ->where('customer_id', $customer->id)
            ->first();

        if ($existing && $existing->isActive()) {
            return redirect(Locale::route('courses.show', ['course' => $course->slug]))
                ->with('status', __('courses.already_enrolled'));
        }

        return view('courses.checkout', [
            'course' => $course,
            'customer' => $customer,
            'existingEnrollment' => $existing,
        ]);
    }

    public function store(CourseCheckoutRequest $request, Course $course, StartCourseRegistration $action): RedirectResponse
    {
        $result = $action(
            $course,
            auth('customer')->user(),
            $request->input('payment_method', 'qris'),
            $request->input('student_name'),
            $request->input('student_phone')
        );

        if ($course->isFree() || ! $result['payment']) {
            return redirect(Locale::route('courses.player.show', ['course' => $course->slug]))
                ->with('status', __('courses.enrolled_successfully'));
        }

        return redirect(Locale::route('courses.payment.show', ['order_id' => $result['payment']->order_id]));
    }
}
