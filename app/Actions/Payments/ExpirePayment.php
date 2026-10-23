<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Ends an unpaid payment window and lets its payable release whatever it was
 * holding (an event seat, a pending course enrollment).
 *
 * Safe to call from three places: the scheduled sweep, the payment page (lazy
 * check), and a gateway `expire` notification. The row lock and status check
 * make every duplicate a no-op.
 */
class ExpirePayment
{
    /**
     * @param  string  $status  One of expired, cancelled, or failed.
     */
    public function __invoke(
        Payment $payment,
        bool $force = false,
        string $status = Payment::STATUS_EXPIRED,
    ): Payment {
        if (! in_array($status, [Payment::STATUS_EXPIRED, Payment::STATUS_CANCELLED, Payment::STATUS_FAILED], true)) {
            $status = Payment::STATUS_EXPIRED;
        }

        return DB::transaction(function () use ($payment, $force, $status) {
            $locked = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isPending()) {
                return $locked;
            }

            if (! $force && ! $locked->isDueForExpiry()) {
                return $locked;
            }

            $oldStatus = $locked->status;

            $locked->forceFill([
                'status' => $status,
            ])->save();

            $locked->loadMissing('payable');
            $locked->payable?->expirePayment($locked);

            app(AuditLogger::class)->log(
                'payment.'.$status,
                $locked,
                ['status' => $oldStatus],
                [
                    'status' => $status,
                    'order_id' => $locked->order_id,
                    'gross_amount' => (string) $locked->gross_amount,
                ],
                null,
                'Pembayaran '.$locked->order_id.' ditutup dengan status '.$status.'.',
            );

            return $locked;
        });
    }
}
