<?php

use App\Support\AcademicTerminology;

it('resolves academic terminology for every supported degree level', function (string $level, string $finalWork, string $proposalExam, string $finalExam): void {
    $terminology = AcademicTerminology::forDegreeLevel($level);

    expect($terminology)
        ->finalWork->toBe($finalWork)
        ->proposalExam->toBe($proposalExam)
        ->finalExam->toBe($finalExam);
})->with([
    'D3' => ['d3', 'Tugas Akhir', 'Seminar Proposal Tugas Akhir', 'Sidang Tugas Akhir'],
    'S1' => ['s1', 'Skripsi', 'Seminar Proposal Skripsi', 'Sidang Skripsi'],
    'S2' => ['s2', 'Tesis', 'Seminar Proposal Tesis', 'Ujian Tesis'],
]);

it('uses S1 terminology as the compatibility fallback', function (): void {
    expect(AcademicTerminology::forDegreeLevel(null))
        ->finalWork->toBe('Skripsi')
        ->proposalExamShort->toBe('Sempro')
        ->finalExam->toBe('Sidang Skripsi');
});
