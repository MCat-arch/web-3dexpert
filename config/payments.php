<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment window
    |--------------------------------------------------------------------------
    |
    | How long a pending payment may hold its entitlement. Event registrations
    | hold a seat for this long; course enrollments stay pending. The scheduled
    | `payments:expire` command, the payment page, and Midtrans expire
    | notifications all enforce the same value.
    |
    */
    'expiry_minutes' => (int) env('PAYMENT_EXPIRY_MINUTES', 15),
];
