<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\User;
use App\Support\AuditLogger;
use InvalidArgumentException;

/**
 * Staff rejection of an uploaded receipt.
 *
 * The order is closed through ExpirePayment (status: cancelled), which is the
 * existing single path that releases the held event seat or revokes the
 * pending course enrollment. The reason is appended to the proof note so the
 * customer (and later staff) can see why it was rejected.
 */
class RejectPaymentProof
{
    public function __invoke(Payment $payment, string $reason, ?User $actor = null): Payment
    {
        if (! $payment->hasProof()) {
            throw new InvalidArgumentException(__('payments.proof_cannot_reject'));
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException(__('payments.proof_reject_reason_required'));
        }

        $closed = app(ExpirePayment::class)(
            $payment,
            force: true,
            status: Payment::STATUS_CANCELLED,
        );

        $note = trim((string) $closed->payment_proof_note);
        $note = ($note !== '' ? $note."\n" : '').'Ditolak admin: '.$reason;

        $closed->forceFill(['payment_proof_note' => $note])->save();

        app(AuditLogger::class)->log(
            'payment.proof_rejected',
            $closed,
            ['status' => Payment::STATUS_PENDING],
            [
                'order_id' => $closed->order_id,
                'status' => Payment::STATUS_CANCELLED,
                'reason' => $reason,
                'rejected_by' => $actor?->getKey(),
            ],
            $actor,
            'Bukti pembayaran ditolak untuk pesanan '.$closed->order_id.'.',
        );

        return $closed;
    }
}
