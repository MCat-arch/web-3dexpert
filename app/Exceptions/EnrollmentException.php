<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Enrollment refusals that are expected business outcomes, not faults.
 *
 * Each carries a translation key so the controller can turn it into a flash
 * message without a match() on exception classes.
 */
class EnrollmentException extends RuntimeException
{
    public function __construct(public readonly string $translationKey)
    {
        parent::__construct($translationKey);
    }

    public static function full(): self
    {
        return new self('events.error_full');
    }

    public static function closed(): self
    {
        return new self('events.error_closed');
    }

    public static function alreadyRegistered(): self
    {
        return new self('events.error_already_registered');
    }

    public static function notVerified(): self
    {
        return new self('events.error_not_verified');
    }

    public static function paidUnavailable(): self
    {
        return new self('events.error_paid_unavailable');
    }

    public function userMessage(): string
    {
        return __($this->translationKey);
    }
}
