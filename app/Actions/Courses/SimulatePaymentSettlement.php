<?php

namespace App\Actions\Courses;

use App\Actions\Payments\SettlePayment;
use App\Models\Payment;
use InvalidArgumentException;

/**
 * Local/testing convenience only. It is never rendered as a public button;
 * production payments settle through a signed Midtrans notification.
 */
class SimulatePaymentSettlement
{
    public function __invoke(Payment $payment): Payment
    {
        if ($payment->isSettled()) {
            return $payment;
        }

        if (! $payment->isPending()) {
            throw new InvalidArgumentException(__('courses.payment_cannot_settle'));
        }

        return app(SettlePayment::class)($payment, [
            'simulation' => true,
            'simulated_at' => now()->toIso8601String(),
            'transaction_status' => 'settlement',
            'status_message' => 'Simulated settlement approved',
        ]);
    }
}
