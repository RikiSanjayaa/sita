<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class WitaDateTime
{
    public const TIMEZONE = 'Asia/Makassar';

    public static function format(?CarbonInterface $value, string $format = 'd M Y H:i', bool $withLabel = true): string
    {
        if ($value === null) {
            return '-';
        }

        $formatted = CarbonImmutable::instance($value)
            ->setTimezone(self::TIMEZONE)
            ->format($format);

        if (! $withLabel) {
            return $formatted;
        }

        return $formatted.' WITA';
    }

    public static function translated(?CarbonInterface $value, string $format, string $locale = 'id', bool $withLabel = true): string
    {
        if ($value === null) {
            return '-';
        }

        $formatted = CarbonImmutable::instance($value)
            ->setTimezone(self::TIMEZONE)
            ->locale($locale)
            ->translatedFormat($format);

        if (! $withLabel) {
            return $formatted;
        }

        return $formatted.' WITA';
    }
}
