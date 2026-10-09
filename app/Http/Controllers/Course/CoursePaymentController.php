<?php

namespace App\Http\Controllers\Course;

use App\Actions\Courses\SimulatePaymentSettlement;
use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;
use App\Models\Payment;
use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CoursePaymentController extends Controller
{
    public function show(string $order_id): View
    {
        $payment = Payment::where('order_id', $order_id)
            ->with(['payable.course'])
            ->firstOrFail();

        $enrollment = $payment->payable;

        if ($enrollment instanceof CourseEnrollment && auth('customer')->check()) {
            if ($enrollment->customer_id && $enrollment->customer_id !== auth('customer')->id()) {
                abort(403);
            }
        }

        $course = $enrollment instanceof CourseEnrollment ? $enrollment->course : null;

        return view('courses.payment', [
            'payment' => $payment,
            'enrollment' => $enrollment,
            'course' => $course,
        ]);
    }

    public function simulate(string $order_id, SimulatePaymentSettlement $action): RedirectResponse
    {
        $payment = Payment::where('order_id', $order_id)->firstOrFail();

        $enrollment = $payment->payable;
        if ($enrollment instanceof CourseEnrollment && auth('customer')->check()) {
            if ($enrollment->customer_id && $enrollment->customer_id !== auth('customer')->id()) {
                abort(403);
            }
        }

        $action($payment);

        if ($enrollment instanceof CourseEnrollment && $enrollment->course) {
            return redirect(Locale::route('courses.player.show', ['course' => $enrollment->course->slug]))
                ->with('status', __('courses.payment_success_message'));
        }

        return redirect()->back()->with('status', __('courses.payment_success_message'));
    }
}
