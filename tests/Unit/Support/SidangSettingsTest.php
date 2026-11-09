<?php

use App\Models\ProgramStudi;
use App\Support\SidangSettings;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

it('clamps the effective examiner minimum while retaining stricter program settings', function (array $settings, int $minimum): void {
    $program = new ProgramStudi(['slug' => 'bukan-ilkom', 'sidang_settings' => $settings]);

    expect(SidangSettings::minimumPaperExaminers($program))->toBe($minimum)
        ->and(SidangSettings::forProgram($program)['minimum_paper_examiners'])->toBe($minimum);
})->with([
    'missing' => [[], 2],
    'empty' => [['minimum_paper_examiners' => null], 2],
    'legacy' => [['minimum_paper_examiners' => 0], 2],
    'single' => [['minimum_paper_examiners' => '1'], 2],
    'two' => [['minimum_paper_examiners' => 2], 2],
    'three' => [['minimum_paper_examiners' => 3], 3],
]);

it('rejects invalid examiner minimums', function (mixed $minimum): void {
    expect(fn () => SidangSettings::validate([...SidangSettings::DEFAULTS, 'minimum_paper_examiners' => $minimum]))
        ->toThrow(ValidationException::class);
})->with([-1, 0, 1, 1.5, 'dua']);

it('accepts baseline and stricter examiner minimums', function (?int $minimum): void {
    SidangSettings::validate([...SidangSettings::DEFAULTS, 'minimum_paper_examiners' => $minimum]);

    expect(SidangSettings::DEFAULTS['minimum_paper_examiners'])->toBe(2);
})->with([null, 2, 3]);

it('uses the baseline without a program and warns only unverified programs', function (): void {
    $ilkom = new ProgramStudi(['slug' => 'ilkom']);
    $other = new ProgramStudi(['slug' => 'manajemen']);

    expect(SidangSettings::minimumPaperExaminers(null))->toBe(2)
        ->and(SidangSettings::forProgram(null)['minimum_paper_examiners'])->toBe(2)
        ->and(SidangSettings::programWarning($ilkom))->toBeNull()
        ->and(SidangSettings::programWarning($other))->toBeString()->not->toBeEmpty()
        ->and(SidangSettings::programWarning(null))->toBe(SidangSettings::programWarning($other));
});
