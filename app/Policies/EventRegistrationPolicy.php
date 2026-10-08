<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\EventRegistration;

/**
 * Customers are authorised by OWNERSHIP, not by role -- Customer has no role
 * column by design (D1).
 */
class EventRegistrationPolicy
{
    public function view(Customer $customer, EventRegistration $registration): bool
    {
        return $registration->customer_id === $customer->getKey();
    }

    public function cancel(Customer $customer, EventRegistration $registration): bool
    {
        return $registration->customer_id === $customer->getKey()
            && ! $registration->isCancelled()
            // Cancelling after the event has started is meaningless, and it
            // would return a seat that was already consumed.
            && $registration->event->starts_at->isFuture();
    }
}
