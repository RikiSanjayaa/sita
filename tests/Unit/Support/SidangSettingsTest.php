<?php

use App\Models\ProgramStudi;
use App\Support\SidangSettings;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

it('reads the examiner minimum from program settings', function (array $settings, int $minimum): void {
    $program = new ProgramStudi(['slug' => 'bukan-ilkom', 'sidang_settings' => $settings]);

    expect(SidangSettings::minimumPaperExaminers($program))->toBe($minimum);
})->with([
    'missing' => [[], 0],
    'empty' => [['minimum_paper_examiners' => null], 0],
    'legacy' => [['minimum_paper_examiners' => 0], 0],
    'single' => [['minimum_paper_examiners' => '1'], 1],
    'two' => [['minimum_paper_examiners' => 2], 2],
    'three' => [['minimum_paper_examiners' => 3], 3],
]);

it('rejects invalid examiner minimums', function (mixed $minimum): void {
    expect(fn () => SidangSettings::validate([...SidangSettings::DEFAULTS, 'minimum_paper_examiners' => $minimum]))
        ->toThrow(ValidationException::class);
})->with([-1, 1.5, 'dua']);
