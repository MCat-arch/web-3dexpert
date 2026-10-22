<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;
use App\Support\CourseProgress;
use Illuminate\Contracts\View\View;

class MyCoursesController extends Controller
{
    public function __invoke(): View
    {
        $enrollments = CourseEnrollment::query()
            ->where('customer_id', auth('customer')->id())
            ->with(['course' => fn ($course) => $course->withCount('lessons'), 'payment'])
            ->withCount('completions')
            ->withMax('completions', 'completed_at')
            ->latest('enrolled_at')
            ->get();

        $progress = $enrollments->mapWithKeys(fn (CourseEnrollment $enrollment) => [
            $enrollment->getKey() => CourseProgress::for($enrollment),
        ]);

        return view('customer.courses', compact('enrollments', 'progress'));
    }
}
