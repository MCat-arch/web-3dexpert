<?php

namespace App\Actions\Events;

use App\Exceptions\EnrollmentException;
use App\Models\Customer;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Notifications\Events\RegistrationConfirmed;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Claims a seat and creates the registration.
 *
 * Capacity is a CONCURRENCY problem, not a validation problem. This is the
 * whole reason the action exists, so the reasoning is written down rather than
 * left to be rediscovered:
 *
 *   if ($event->registrations()->count() < $event->capacity) { ... }
 *
 * oversells. Two requests both read 49 of 50 and both insert. At the stated
 * 500-concurrent-user target that is not hypothetical -- it is the expected
 * outcome.
 *
 * The fix is a single conditional UPDATE whose affected-row count tells you
 * whether you won. The database arbitrates, no lock is held across PHP
 * execution, and 500 contenders drain in about a second because each statement
 * is ~1ms.
 *
 * lockForUpdate() plus application logic would also be correct, but it
 * serialises every attempt on a popular event behind PHP execution time rather
 * than behind a single statement. Prefer this; if you switch, record why.
 */
class EnrollInEvent
{
    public function __invoke(Event $event, Customer $customer): EventRegistration
    {
        // A20: verification gates enrollment. If that is relaxed for free
        // events to take the mail provider off the launch critical path, this
        // is the line that changes.
        if (! $customer->canEnroll()) {
            throw EnrollmentException::notVerified();
        }

        if (! $event->registrationIsOpen()) {
            throw EnrollmentException::closed();
        }

        // P5a ships the free path only. Never leave a paid event with an
        // unpaid pending registration while checkout is deferred to P5b.
        if (! $event->isFree()) {
            throw EnrollmentException::paidUnavailable();
        }

        $existing = EventRegistration::where('event_id', $event->getKey())
            ->where('customer_id', $customer->getKey())
            ->first();

        if ($existing && $existing->occupiesSeat()) {
            throw EnrollmentException::alreadyRegistered();
        }

        return DB::transaction(function () use ($event, $customer, $existing) {
            $this->claimSeat($event);

            try {
                $registration = $this->record($event, $customer, $existing);
            } catch (UniqueConstraintViolationException $e) {
                /*
                 * The pre-check above and this insert are not atomic: two
                 * concurrent requests from the SAME customer both pass it, and
                 * the loser lands here. Without this catch a double-click
                 * produces a 500.
                 *
                 * Releasing the seat matters as much as the clean response --
                 * this request already incremented seats_taken, so returning
                 * without releasing would permanently consume two seats for
                 * one attendee.
                 */
                $this->releaseSeat($event);

                throw EnrollmentException::alreadyRegistered();
            }

            // Free events confirm immediately. P5b routes paid events through
            // the payment flow and leaves them pending.
            if ($event->isFree()) {
                $registration->forceFill([
                    'status' => EventRegistration::STATUS_CONFIRMED,
                    'confirmed_at' => now(),
                ])->save();
            }

            /*
             * Dispatched after commit, never inside the transaction: a queued
             * job must not be able to read a row that a rollback is about to
             * remove, and holding the event row while waiting on a mail
             * provider is how lock-wait timeouts happen under load.
             */
            if ($registration->status === EventRegistration::STATUS_CONFIRMED) {
                DB::afterCommit(fn () => $customer->notify(
                    new RegistrationConfirmed($registration->fresh(['event']))
                ));
            }

            return $registration;
        });
    }

    /**
     * One statement. $claimed is the affected-row count: 1 means the seat is
     * yours, 0 means it went between your read and your write.
     */
    private function claimSeat(Event $event): void
    {
        $claimed = Event::query()
            ->whereKey($event->getKey())
            ->where(fn ($query) => $query
                ->whereNull('capacity')
                ->orWhereColumn('seats_taken', '<', 'capacity'))
            ->increment('seats_taken');

        if ($claimed === 0) {
            throw EnrollmentException::full();
        }
    }

    private function releaseSeat(Event $event): void
    {
        // Floored at zero: a decrement below zero would be worse than the
        // drift it is trying to correct, because seatsLeft() would then
        // over-report availability.
        Event::query()
            ->whereKey($event->getKey())
            ->where('seats_taken', '>', 0)
            ->decrement('seats_taken');
    }

    private function record(Event $event, Customer $customer, ?EventRegistration $existing): EventRegistration
    {
        $attributes = [
            'attendee_name' => $customer->name,
            'attendee_email' => $customer->email,
            'attendee_phone' => $customer->phone,
            'status' => EventRegistration::STATUS_PENDING,
            'registered_at' => now(),
            'cancelled_at' => null,
            'confirmed_at' => null,
        ];

        // A cancelled row is reused rather than inserted alongside: the
        // (event_id, customer_id) unique index has no portable partial form in
        // MySQL, so re-registration updates the existing row.
        if ($existing) {
            $existing->forceFill($attributes)->save();

            return $existing;
        }

        return EventRegistration::create($attributes + [
            'event_id' => $event->getKey(),
            'customer_id' => $customer->getKey(),
            'ticket_code' => EventRegistration::generateTicketCode(),
        ]);
    }
}
