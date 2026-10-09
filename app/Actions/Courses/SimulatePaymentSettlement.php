<?php

namespace App\Actions\Courses;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SimulatePaymentSettlement
{
    /**
     * Simulates successful payment settlement for development / testing
     * without requiring external Midtrans webhooks or gateway credentials.
     */
    public function __invoke(Payment $payment): Payment
    {
        if ($payment->isSettled()) {
            return $payment;
        }

        if (! $payment->isPending()) {
            throw new InvalidArgumentException(__('courses.payment_cannot_settle'));
        }

        return DB::transaction(function () use ($payment) {
            $payment->status = Payment::STATUS_SETTLEMENT;
            $payment->paid_at = now();
            $payment->raw_response = [
                'simulation' => true,
                'simulated_at' => now()->toIso8601String(),
                'status_message' => 'Simulated settlement approved',
            ];
            $payment->save();

            // Call polymorphic Payable contract method to grant active entitlement
            if ($payment->payable) {
                $payment->payable->fulfilPayment($payment);
            }

            return $payment;
        });
    }
}
