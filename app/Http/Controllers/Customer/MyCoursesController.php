<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;
use Illuminate\Contracts\View\View;

class MyCoursesController extends Controller
{
    public function __invoke(): View
    {
        $enrollments = CourseEnrollment::query()
            ->where('customer_id', auth('customer')->id())
            ->with(['course.coverMedia', 'payment'])
            ->latest('enrolled_at')
            ->get();

        return view('customer.courses', compact('enrollments'));
    }
}
