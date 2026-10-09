<?php

namespace App\Contracts;

use App\Models\Payment;

interface Payable
{
    public function payableAmount(): string;

    public function payableDescription(): string;

    public function payableExpiryProfile(): string;

    public function fulfilPayment(Payment $payment): void;

    public function revokePayment(Payment $payment): void;
}
