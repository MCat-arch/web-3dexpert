<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The single settlement path for gateway webhooks and the local simulator.
 *
 * Locking the row makes duplicate Midtrans notifications harmless: the first
 * one settles, every later one sees STATUS_SETTLEMENT and returns without
 * granting access twice.
 */
class SettlePayment
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __invoke(Payment $payment, array $raw = []): Payment
    {
        return DB::transaction(function () use ($payment, $raw) {
            $locked = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isSettled()) {
                return $locked;
            }

            if (! $locked->isPending()) {
                throw new InvalidArgumentException(__('courses.payment_cannot_settle'));
            }

            $oldStatus = $locked->status;

            $locked->forceFill([
                'status' => Payment::STATUS_SETTLEMENT,
                'paid_at' => now(),
                'payment_type' => $raw['payment_type'] ?? $locked->payment_type,
                'midtrans_transaction_id' => $raw['transaction_id'] ?? $locked->midtrans_transaction_id,
                'raw_response' => array_merge($locked->raw_response ?? [], $raw),
            ])->save();

            $locked->loadMissing('payable');
            $locked->payable?->fulfilPayment($locked);

            app(AuditLogger::class)->log(
                'payment.settled',
                $locked,
                ['status' => $oldStatus],
                [
                    'status' => Payment::STATUS_SETTLEMENT,
                    'order_id' => $locked->order_id,
                    'gross_amount' => (string) $locked->gross_amount,
                ],
                null,
                'Pembayaran diterima untuk pesanan '.$locked->order_id,
            );

            return $locked;
        });
    }
}
