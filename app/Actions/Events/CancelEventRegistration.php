<?php

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Cancels a registration and returns its seat to inventory.
 *
 * The ONLY place a seat is released on cancellation. O6's expired-hold job and
 * P8's refund path both call this rather than decrementing themselves -- every
 * duplicate decrement site is a chance for seats_taken to drift, and drift is
 * silent and one-directional (the event sells out early and nobody knows why).
 */
class CancelEventRegistration
{
    public function __invoke(EventRegistration $registration): EventRegistration
    {
        // Idempotent: cancelling twice must not release two seats.
        if ($registration->isCancelled()) {
            return $registration;
        }

        return DB::transaction(function () use ($registration) {
            $registration->forceFill([
                'status' => EventRegistration::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ])->save();

            Event::query()
                ->whereKey($registration->event_id)
                ->where('seats_taken', '>', 0)
                ->decrement('seats_taken');

            /*
             * A customer who cancels while checkout is still open must not be
             * able to scan the old QR and settle into a seat that has already
             * been released. Mark only PENDING attempts; settled payments stay
             * visible to staff for refund.
             */
            Payment::query()
                ->where('payable_type', EventRegistration::class)
                ->where('payable_id', $registration->getKey())
                ->pending()
                ->update([
                    'status' => Payment::STATUS_CANCELLED,
                    'updated_at' => now(),
                ]);

            return $registration;
        });
    }
}
