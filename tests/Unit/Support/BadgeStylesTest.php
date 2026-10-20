<?php

use App\Support\Filament\BadgeStyles;

it('resolves the same raw key differently per domain', function (string $domain, string $status, string $tone): void {
    expect(BadgeStyles::statusTone($domain, $status))->toBe($tone);
})->with([
    'completed project state is neutral' => ['state', 'completed', 'neutral'],
    'completed project phase is success' => ['phase', 'completed', 'success'],
    'completed defense is success' => ['defense', 'completed', 'success'],
    'completed schedule is neutral' => ['schedule', 'completed', 'neutral'],
    'cancelled schedule is neutral' => ['schedule', 'cancelled', 'neutral'],
    'cancelled phase is danger' => ['phase', 'cancelled', 'danger'],
    'pending schedule awaits action' => ['schedule', 'pending', 'warning'],
    'pending result awaits a decision' => ['result', 'pending', 'warning'],
    'open revision awaits action' => ['revision', 'open', 'warning'],
    'submitted revision is informational' => ['revision', 'submitted', 'info'],
    'active state is success' => ['state', 'active', 'success'],
    'inactive flag is neutral' => ['active', 'inactive', 'neutral'],
    'pass with revision is a warning, not success' => ['result', 'pass_with_revision', 'warning'],
    'fail result is danger' => ['result', 'fail', 'danger'],
    'awaiting finalization is a warning' => ['defense', 'awaiting_finalization', 'warning'],
    'sidang phase is primary' => ['phase', 'sidang', 'primary'],
]);

it('treats unknown, null and empty statuses as neutral', function (?string $status): void {
    foreach (array_keys(BadgeStyles::DOMAIN_TONES) as $domain) {
        expect(BadgeStyles::statusTone($domain, $status))->toBe('neutral')
            ->and(BadgeStyles::domainStatusColor($domain, $status))->toBe('gray');
    }
})->with([null, '', 'unknown_status', 'Completed', 'ACTIVE', 'Selesai']);

it('does not leak a known key from another domain', function (): void {
    expect(BadgeStyles::statusTone('result', 'scheduled'))->toBe('neutral')
        ->and(BadgeStyles::statusTone('phase', 'active'))->toBe('neutral')
        ->and(BadgeStyles::statusTone('revision', 'completed'))->toBe('neutral')
        ->and(BadgeStyles::statusTone('not_a_domain', 'completed'))->toBe('neutral');
});

it('maps neutral to the Filament gray color and keeps semantic tones otherwise', function (): void {
    expect(BadgeStyles::domainStatusColor('state', 'completed'))->toBe('gray')
        ->and(BadgeStyles::domainStatusColor('phase', 'sidang'))->toBe('primary')
        ->and(BadgeStyles::domainStatusColor('result', 'fail'))->toBe('danger');
});

it('keeps legacy phase and state colors aligned with the domain map', function (): void {
    foreach (BadgeStyles::DOMAIN_TONES['phase'] as $phase => $tone) {
        expect(BadgeStyles::phaseColor($phase))->toBe($tone === 'neutral' ? 'gray' : $tone);
    }

    foreach (BadgeStyles::DOMAIN_TONES['state'] as $state => $tone) {
        expect(BadgeStyles::stateColor($state))->toBe($tone === 'neutral' ? 'gray' : $tone);
    }

    expect(BadgeStyles::activeStateColor(true))->toBe('success')
        ->and(BadgeStyles::activeStateColor(false))->toBe('gray');
});
