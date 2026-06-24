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

    public static function translatedDateRange(
        ?CarbonInterface $start,
        ?CarbonInterface $end,
        string $locale = 'id',
        bool $withLabel = true,
    ): string {
        if ($start === null) {
            return '-';
        }

        $startAt = CarbonImmutable::instance($start)->setTimezone(self::TIMEZONE)->locale($locale);
        $endAt = $end instanceof CarbonInterface
            ? CarbonImmutable::instance($end)->setTimezone(self::TIMEZONE)->locale($locale)
            : null;

        if (! $endAt instanceof CarbonImmutable || $startAt->isSameDay($endAt)) {
            return self::translated($startAt, 'd F Y, H:i', $locale, $withLabel);
        }

        $formatted = $startAt->translatedFormat('d F Y')
            .' - '
            .$endAt->translatedFormat('d F Y')
            .', '
            .$startAt->format('H:i');

        if (! $withLabel) {
            return $formatted;
        }

        return $formatted.' WITA';
    }
}
