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
