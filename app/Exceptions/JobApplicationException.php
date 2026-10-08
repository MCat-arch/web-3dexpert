<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Application refusals that are expected business outcomes, not faults.
 *
 * Same shape as EnrollmentException: each carries a translation key so the
 * controller flashes a message without a match() on exception classes.
 */
class JobApplicationException extends RuntimeException
{
    public function __construct(public readonly string $translationKey)
    {
        parent::__construct($translationKey);
    }

    public static function vacancyClosed(): self
    {
        return new self('careers.error_closed');
    }

    public static function alreadyApplied(): self
    {
        return new self('careers.error_already_applied');
    }

    public static function notVerified(): self
    {
        return new self('careers.error_not_verified');
    }

    public function userMessage(): string
    {
        return __($this->translationKey);
    }
}
