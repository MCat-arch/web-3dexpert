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

    /*
    |--------------------------------------------------------------------------
    | Manual transfer window
    |--------------------------------------------------------------------------
    |
    | While the Midtrans server key is empty, pending payments are switched to
    | bank transfer. Customers upload a proof of payment and staff validate it,
    | which needs a wider window than the 15-minute gateway QR. Kept bounded so
    | a held event seat is still released when nobody pays.
    |
    */

    'manual_expiry_hours' => (int) env('PAYMENT_MANUAL_EXPIRY_HOURS', 24),
];
