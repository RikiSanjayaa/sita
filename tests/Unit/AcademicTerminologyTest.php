<?php

use App\Enums\DegreeLevel;
use App\Support\AcademicTerminology;

it('labels a single student by degree level', function (DegreeLevel|string|null $level, string $degreeLevel, string $finalWork, string $finalExam): void {
    expect(AcademicTerminology::forDegreeLevel($level))
        ->degreeLevel->toBe($degreeLevel)
        ->finalWork->toBe($finalWork)
        ->finalWorkLower->toBe(mb_strtolower($finalWork))
        ->proposalExam->toBe('Seminar Proposal')
        ->proposalExamShort->toBe('Sempro')
        ->proposalDocument->toBe('Naskah Proposal')
        ->proposalPhase->toBe('Tahap Proposal')
        ->finalExam->toBe($finalExam)
        ->finalExamShort->toBe('Sidang');
})->with([
    'D3' => ['d3', 'D3', 'Tugas Akhir', 'Sidang Tugas Akhir'],
    'S1' => ['s1', 'S1', 'Skripsi', 'Sidang Skripsi'],
    'S2' => ['s2', 'S2', 'Tesis', 'Sidang Tesis'],
    'enum D3' => [DegreeLevel::D3, 'D3', 'Tugas Akhir', 'Sidang Tugas Akhir'],
    'padded upper-case S2' => [' S2 ', 'S2', 'Tesis', 'Sidang Tesis'],
    'unknown level falls back to S1' => ['s9', 'S1', 'Skripsi', 'Sidang Skripsi'],
    'missing level falls back to S1' => [null, 'S1', 'Skripsi', 'Sidang Skripsi'],
]);

it('labels the stage-1 exam by curriculum', function (bool $obe, string $proposalExam, string $proposalExamShort): void {
    expect(AcademicTerminology::forDegreeLevel('s1', $obe))
        ->proposalExam->toBe($proposalExam)
        ->proposalExamShort->toBe($proposalExamShort)
        ->finalExam->toBe('Sidang Skripsi');
})->with([
    'OBE' => [true, 'Ujian Sinopsis', 'Sinopsis'],
    'non-OBE' => [false, 'Seminar Proposal', 'Sempro'],
]);

it('uses cross-degree labels for mixed lists', function (): void {
    expect(AcademicTerminology::neutral())
        ->degreeLevel->toBe('')
        ->finalWork->toBe('Tugas Akhir')
        ->proposalExam->toBe('Sempro / Sinopsis')
        ->proposalExamShort->toBe('Sempro / Sinopsis')
        ->proposalDocument->toBe('Naskah Proposal')
        ->proposalPhase->toBe('Tahap Proposal')
        ->finalExam->toBe('Sidang Akhir')
        ->finalExamShort->toBe('Sidang Akhir');
});

it('returns the same keys for every audience', function (): void {
    $keys = array_keys(AcademicTerminology::neutral());

    foreach (['d3', 's1', 's2'] as $level) {
        expect(array_keys(AcademicTerminology::forDegreeLevel($level)))->toBe($keys);
    }
});

it('labels every thesis phase', function (string $phase, string $label, string $key): void {
    expect(AcademicTerminology::phaseLabel($phase))->toBe($label)
        ->and(AcademicTerminology::phaseLabels()[$phase])->toBe($label)
        ->and(AcademicTerminology::neutral()[$key])->toBe($label);
})->with([
    ['title_review', 'Review Judul', 'titleReviewPhase'],
    ['sempro', 'Tahap Proposal', 'proposalPhase'],
    ['research', 'Riset', 'researchPhase'],
    ['sidang', 'Tahap Sidang', 'finalExamPhase'],
    ['completed', 'Selesai', 'completedPhase'],
]);

it('labels a defense type with the audience terminology', function (string $type, bool $short, string $student, string $crossDegree): void {
    expect(AcademicTerminology::defenseLabel(AcademicTerminology::forDegreeLevel('s2'), $type, $short))->toBe($student)
        ->and(AcademicTerminology::defenseLabel(AcademicTerminology::neutral(), $type, $short))->toBe($crossDegree);
})->with([
    'sempro' => ['sempro', false, 'Seminar Proposal', 'Sempro / Sinopsis'],
    'sempro short' => ['sempro', true, 'Sempro', 'Sempro / Sinopsis'],
    'sidang' => ['sidang', false, 'Sidang Tesis', 'Sidang Akhir'],
    'sidang short' => ['sidang', true, 'Sidang', 'Sidang Akhir'],
]);
