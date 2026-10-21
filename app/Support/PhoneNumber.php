<?php

namespace App\Support;

final class PhoneNumber
{
    /**
     * Canonical form: starts with 08 and has 10 to 15 digits in total.
     */
    private const CANONICAL_PATTERN = '/^08[0-9]{8,13}$/';

    /**
     * Rewrites a valid Indonesian mobile number to its canonical 08 form.
     * Blank input becomes null. Invalid input is returned trimmed so that
     * validation rejects it with the original text.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return self::canonical($value) ?? trim($value);
    }

    public static function isValid(string $value): bool
    {
        return self::canonical($value) !== null;
    }

    private static function canonical(string $value): ?string
    {
        $compact = preg_replace('/[\s.()-]/', '', trim($value)) ?? '';

        if (str_starts_with($compact, '+62')) {
            $compact = '0'.substr($compact, 3);
        } elseif (str_starts_with($compact, '62')) {
            $compact = '0'.substr($compact, 2);
        }

        return preg_match(self::CANONICAL_PATTERN, $compact) === 1 ? $compact : null;
    }
}
