<?php

namespace App\Http\Controllers\Payment;

use App\Actions\Courses\SimulatePaymentSettlement;
use App\Actions\Payments\EnsurePaymentCharge;
use App\Actions\Payments\ExpirePayment;
use App\Contracts\Payable;
use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;
use App\Models\EventRegistration;
use App\Models\Payment;
use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function show(string $order_id): View
    {
        $payment = Payment::query()->where('order_id', $order_id)->firstOrFail();

        $payment = $this->expireIfDue($payment);
        $payable = $this->payable($payment);

        $this->authorizeViewer($payable);

        if ($payment->isPending()) {
            app(EnsurePaymentCharge::class)($payment);
            $payment->refresh();
            $payable = $this->payable($payment);
        }

        $customer = $payable->payableCustomer();

        return view('payments.show', [
            'payment' => $payment,
            'payable' => $payable,
            'title' => $payable->payableTitle(),
            'description' => $payable->payableDescription(),
            'kindLabel' => $payable instanceof EventRegistration
                ? __('payments.kind_event')
                : __('payments.kind_course'),
            'reference' => $payable instanceof EventRegistration ? $payable->ticket_code : null,
            'event' => $payable instanceof EventRegistration ? $payable->event : null,
            'customer' => $customer,
            'successUrl' => $payable->payableSuccessUrl(),
            'retryUrl' => $payable->payableRetryUrl(),
            'qrUrl' => $payment->qrUrl(),
            'chargeError' => $payment->chargeError(),
            'expiryMinutes' => (int) config('payments.expiry_minutes', 15),
        ]);
    }

    /**
     * Local/testing-only shortcut used by the automated suite. There is no
     * public button and production requests always 404, even if the route is
     * cached from a non-production deploy.
     */
    public function simulate(string $order_id, SimulatePaymentSettlement $settle): RedirectResponse
    {
        abort_unless(
            app()->environment(['local', 'testing']) && ! config('midtrans.is_production'),
            404,
        );

        $payment = Payment::query()->where('order_id', $order_id)->firstOrFail();
        $payable = $this->payable($payment);
        $this->authorizeViewer($payable);

        $settle($payment);

        return redirect()
            ->to(Locale::route('payments.show', ['order_id' => $payment->order_id]))
            ->with('status', __('courses.payment_success_message'));
    }

    private function expireIfDue(Payment $payment): Payment
    {
        if (! $payment->isDueForExpiry()) {
            return $payment;
        }

        app(ExpirePayment::class)($payment);

        return $payment->refresh();
    }

    private function payable(Payment $payment): Payable
    {
        $payment->loadMissing('payable');
        $payable = $payment->payable;

        if (! $payable instanceof Payable) {
            abort(404);
        }

        // Eager-load the relations used by the page and by the Payable methods
        // (lazy loading is disabled outside production).
        if ($payable instanceof EventRegistration) {
            $payable->loadMissing(['event', 'customer']);
        } elseif ($payable instanceof CourseEnrollment) {
            $payable->loadMissing(['course', 'customer']);
        }

        return $payable;
    }

    private function authorizeViewer(Payable $payable): void
    {
        $viewer = auth('customer')->user();
        $owner = $payable->payableCustomer();

        // A missing owner means the payable is orphaned; never treat that as
        // public. The route already requires an authenticated customer, but
        // keep the check correct if the route middleware changes later.
        if ($viewer === null || $owner === null || $viewer->getKey() !== $owner->getKey()) {
            abort(403);
        }
    }
}
