<?php

use App\Support\WitaDateTime;
use Carbon\CarbonImmutable;

it('formats datetime values in wita', function (): void {
    $value = CarbonImmutable::parse('2026-03-20 05:14:38', 'UTC');

    expect(WitaDateTime::format($value))->toBe('20 Mar 2026 13:14 WITA');
});

it('returns dash when datetime is missing', function (): void {
    expect(WitaDateTime::format(null))->toBe('-');
});

it('formats single-day and multi-day translated date ranges', function (): void {
    $start = CarbonImmutable::parse('2026-07-01 09:00:00', 'Asia/Makassar');
    $end = CarbonImmutable::parse('2026-07-05 09:00:00', 'Asia/Makassar');

    expect(WitaDateTime::translatedDateRange($start, $start))->toBe('1 Juli 2026, 09.00')
        ->and(WitaDateTime::translatedDateRange($start, $end))->toBe('1–5 Juli 2026, 09.00');
});
