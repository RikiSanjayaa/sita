<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class CsatPeriod
{
    public const THIS_MONTH = 'this_month';

    public const LAST_MONTH = 'last_month';

    public const THIS_YEAR = 'this_year';

    public const LAST_YEAR = 'last_year';

    public static function options(): array
    {
        return [
            self::THIS_MONTH => 'Bulan ini',
            self::LAST_MONTH => 'Bulan lalu',
            self::THIS_YEAR => 'Tahun ini',
            self::LAST_YEAR => 'Tahun lalu',
        ];
    }

    public static function sanitize(?string $value): string
    {
        return array_key_exists($value, self::options())
            ? $value
            : self::THIS_MONTH;
    }

    public static function label(string $preset): string
    {
        return self::options()[self::sanitize($preset)];
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable, granularity: string}
     */
    public static function range(string $preset): array
    {
        $now = now()->toImmutable();

        return match (self::sanitize($preset)) {
            self::LAST_MONTH => [
                'start' => $now->subMonthNoOverflow()->startOfMonth()->startOfDay(),
                'end' => $now->subMonthNoOverflow()->endOfMonth()->endOfDay(),
                'granularity' => 'day',
            ],
            self::THIS_YEAR => [
                'start' => $now->startOfYear()->startOfDay(),
                'end' => $now->endOfDay(),
                'granularity' => 'month',
            ],
            self::LAST_YEAR => [
                'start' => $now->subYear()->startOfYear()->startOfDay(),
                'end' => $now->subYear()->endOfYear()->endOfDay(),
                'granularity' => 'month',
            ],
            default => [
                'start' => $now->startOfMonth()->startOfDay(),
                'end' => $now->endOfDay(),
                'granularity' => 'day',
            ],
        };
    }
}
