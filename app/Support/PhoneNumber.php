<?php

namespace App\Support;

/**
 * Normalises Indonesian mobile numbers to a single comparable form.
 *
 * Extracted because two features now depend on the SAME normalisation, and
 * they only work together if it is byte-identical:
 *
 *   - P1 normalises a customer's phone at registration.
 *   - P7 compares a typed phone number against projects.customer_phone as the
 *     second factor of the progress lookup.
 *
 * If those two disagreed by a space or a leading +62, a real customer would be
 * told their own project code is invalid -- the failure would look like a data
 * problem rather than a formatting one.
 *
 * +62 812-3456-7890, 62 812 3456 7890, 0812-3456-7890 and 081234567890 all
 * normalise to 081234567890.
 */
class PhoneNumber
{
    public static function normalise(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);

        if (blank($digits)) {
            return null;
        }

        // +62 / 62 / 0 are the same leading national prefix. Collapse to 0.
        return preg_replace('/^(62|0)/', '0', $digits);
    }

    /**
     * Whether two numbers refer to the same line, ignoring formatting.
     *
     * hash_equals because this comparison is part of an authorisation-like
     * check: the phone number is the second factor in the P7 lookup, so a
     * timing difference would leak which half of the pair was wrong.
     */
    public static function matches(?string $a, ?string $b): bool
    {
        $a = self::normalise($a);
        $b = self::normalise($b);

        if ($a === null || $b === null) {
            return false;
        }

        return hash_equals($a, $b);
    }
}
