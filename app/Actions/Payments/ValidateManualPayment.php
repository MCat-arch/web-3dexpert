<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\User;
use App\Support\AuditLogger;
use InvalidArgumentException;

/**
 * Staff validation of a manual bank transfer.
 *
 * Settlement stays in SettlePayment: this action only records who validated
 * the receipt and when. That keeps one entitlement-granting path for gateway
 * webhooks, the local simulator, and manual transfers, so the course/event
 * side cannot be granted twice.
 */
class ValidateManualPayment
{
    public function __invoke(Payment $payment, ?User $validator = null): Payment
    {
        if (! $payment->hasProof()) {
            throw new InvalidArgumentException(__('payments.proof_cannot_validate'));
        }

        $settled = app(SettlePayment::class)($payment, [
            'payment_type' => Payment::TYPE_BANK_TRANSFER,
            'validated_manually' => true,
            'validated_at' => now()->toIso8601String(),
            'validated_by' => $validator?->getKey(),
        ]);

        // A second admin clicking Validate after the payment settled must not
        // overwrite the first validator's record.
        if ($settled->validated_at !== null) {
            return $settled;
        }

        $settled->forceFill([
            'payment_type' => Payment::TYPE_BANK_TRANSFER,
            'validated_at' => now(),
            'validated_by' => $validator?->getKey(),
        ])->save();

        app(AuditLogger::class)->log(
            'payment.validated_manual',
            $settled,
            [],
            [
                'order_id' => $settled->order_id,
                'gross_amount' => (string) $settled->gross_amount,
                'validated_by' => $validator?->getKey(),
            ],
            $validator,
            'Pembayaran transfer manual divalidasi untuk pesanan '.$settled->order_id.'.',
        );

        return $settled;
    }
}
