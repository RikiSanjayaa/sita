<?php

use App\Support\WitaDateTime;
use Carbon\CarbonImmutable;

/*
 * Every date a user reads is Indonesian ("24 Okt 2026", never "24 Oct 2026").
 * Format through App\Support\WitaDateTime in PHP; in TypeScript pass an 'id-ID' locale.
 */

it('formats dates with Indonesian month names in WITA', function (): void {
    $value = CarbonImmutable::parse('2026-10-24 14:30:00', 'UTC');

    expect(WitaDateTime::format($value, 'd M Y', false))->toBe('24 Okt 2026')
        ->and(WitaDateTime::format($value))->toBe('24 Okt 2026 22:30 WITA')
        ->and(WitaDateTime::format($value, 'j F Y', false))->toBe('24 Oktober 2026')
        ->and(WitaDateTime::format($value, 'M Y', false))->toBe('Okt 2026')
        ->and(WitaDateTime::format(null))->toBe('-')
        ->and(WitaDateTime::formatOrNull(null))->toBeNull()
        ->and(WitaDateTime::formatOrNull($value, 'd M Y'))->toBe('24 Okt 2026');
});

it('keeps English month formats out of PHP code', function (): void {
    $root = dirname(__DIR__, 2);
    $offenders = [];
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root.'/app', FilesystemIterator::SKIP_DOTS),
    );

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        foreach (file($file->getPathname()) as $index => $line) {
            // Carbon's format() always prints English names for M, F, D, l and S.
            if (preg_match('/->format\(\s*([\'"])(?:\\\\.|(?!\1)[^\\\\])*?(?<!\\\\)[MFDlS]/', $line)) {
                $offenders[] = substr($file->getPathname(), strlen($root) + 1).':'.($index + 1);
            }
        }
    }

    expect($offenders)->toBe([], 'Use App\Support\WitaDateTime::format() for dates users read.');
});

it('keeps non-Indonesian date locales out of the frontend', function (): void {
    $root = dirname(__DIR__, 2);
    $offenders = [];
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root.'/resources/js', FilesystemIterator::SKIP_DOTS),
    );

    foreach ($files as $file) {
        if (! preg_match('/\.tsx?$/', $file->getFilename())) {
            continue;
        }

        foreach (file($file->getPathname()) as $index => $line) {
            if (preg_match('/(toLocale(Date|Time)?String|DateTimeFormat)\(\s*(undefined|[\'"]en)|toLocale(Date|Time)?String\(\s*\)/', $line)) {
                $offenders[] = substr($file->getPathname(), strlen($root) + 1).':'.($index + 1);
            }
        }
    }

    expect($offenders)->toBe([], "Pass 'id-ID' to toLocale*String and Intl.DateTimeFormat.");
});
