<?php

namespace App\Contracts;

use App\Models\Customer;
use App\Models\Payment;

interface Payable
{
    public function payableAmount(): string;

    public function payableDescription(): string;

    public function payableExpiryProfile(): string;

    /** The customer who may view this payment page, when the payable has one. */
    public function payableCustomer(): ?Customer;

    /** Human-readable title shown on the generic payment page. */
    public function payableTitle(): string;

    /** Where to send the customer after a successful payment. */
    public function payableSuccessUrl(): string;

    /** Where to send the customer after an expired/failed payment. */
    public function payableRetryUrl(): string;

    public function fulfilPayment(Payment $payment): void;

    public function revokePayment(Payment $payment): void;

    /**
     * The payment window closed before settlement. Release anything held for
     * it: an event seat, a pending course enrollment, and so on.
     */
    public function expirePayment(Payment $payment): void;
}
