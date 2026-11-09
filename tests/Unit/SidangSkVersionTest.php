<?php

use App\Models\ThesisDefense;
use App\Services\SidangPaperService;

it('keeps a No SK confirmation valid only for the stored number it displayed', function (?string $shown, ?string $stored, bool $matches): void {
    $service = new SidangPaperService;
    $defense = new ThesisDefense;
    $defense->sidang_sk = $shown;
    $version = $service->skVersion($defense);

    $defense->sidang_sk = $stored;

    expect($version)->toMatch('/^[a-f0-9]{64}$/')
        ->and(hash_equals($version, $service->skVersion($defense)))->toBe($matches);
})->with([
    'same number' => ['002/ADUM', '002/ADUM', true],
    'replaced number' => ['002/ADUM', '003/ADUM', false],
    'first assignment' => [null, '002/ADUM', false],
    'legacy whitespace is significant' => [' 002/ADUM ', '002/ADUM', false],
    'blank legacy number displays as empty' => [null, '', true],
]);
