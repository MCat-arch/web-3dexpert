<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manual refund. The entitlement is revoked through the Payable contract so
 * every payable type gets the same treatment (currently CourseEnrollment).
 */
class RefundPayment
{
    public function __invoke(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            $payment->refresh();

            if ($payment->status === Payment::STATUS_REFUNDED) {
                return $payment;
            }

            if (! $payment->isSettled()) {
                throw ValidationException::withMessages([
                    'payment' => 'Hanya pembayaran berstatus settlement yang dapat direfund.',
                ]);
            }

            $oldStatus = $payment->status;

            $payment->loadMissing('payable');

            $payment->forceFill([
                'status' => Payment::STATUS_REFUNDED,
            ])->save();

            $payment->payable?->revokePayment($payment);

            app(AuditLogger::class)->log(
                'payment.refunded',
                $payment,
                ['status' => $oldStatus],
                [
                    'status' => Payment::STATUS_REFUNDED,
                    'order_id' => $payment->order_id,
                    'gross_amount' => (string) $payment->gross_amount,
                ],
                null,
                'Refund diproses untuk pesanan '.$payment->order_id,
            );

            return $payment;
        });
    }
}
