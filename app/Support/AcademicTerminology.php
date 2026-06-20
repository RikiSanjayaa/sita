<?php

namespace App\Support;

use App\Enums\DegreeLevel;
use App\Models\ThesisProject;
use App\Models\User;

final class AcademicTerminology
{
    /**
     * @return array<string, string>
     */
    public static function forDegreeLevel(DegreeLevel|string|null $degreeLevel): array
    {
        $level = $degreeLevel instanceof DegreeLevel
            ? $degreeLevel
            : DegreeLevel::tryFrom(strtolower(trim((string) $degreeLevel)));

        return match ($level) {
            DegreeLevel::D3 => self::labels(
                degreeLevel: 'D3',
                finalWork: 'Tugas Akhir',
                proposalExam: 'Seminar Proposal Tugas Akhir',
                proposalExamShort: 'Sempro Tugas Akhir',
                finalExam: 'Sidang Tugas Akhir',
            ),
            DegreeLevel::S2 => self::labels(
                degreeLevel: 'S2',
                finalWork: 'Tesis',
                proposalExam: 'Seminar Proposal Tesis',
                proposalExamShort: 'Seminar Proposal Tesis',
                finalExam: 'Ujian Tesis',
            ),
            default => self::labels(
                degreeLevel: $level?->label() ?? 'S1',
                finalWork: 'Skripsi',
                proposalExam: 'Seminar Proposal Skripsi',
                proposalExamShort: 'Sempro',
                finalExam: 'Sidang Skripsi',
            ),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function neutral(): array
    {
        return self::labels(
            degreeLevel: '',
            finalWork: 'Tugas Akhir',
            proposalExam: 'Ujian Proposal',
            proposalExamShort: 'Proposal',
            finalExam: 'Ujian Akhir',
        );
    }

    /**
     * @return array<string, string>
     */
    public static function forStudent(?User $student): array
    {
        $student?->loadMissing('mahasiswaProfile');

        return self::forDegreeLevel($student?->mahasiswaProfile?->degree_level);
    }

    /**
     * @return array<string, string>
     */
    public static function forProject(ThesisProject $project): array
    {
        $project->loadMissing('student.mahasiswaProfile');

        return self::forStudent($project->student);
    }

    /**
     * @return array<string, string>
     */
    private static function labels(
        string $degreeLevel,
        string $finalWork,
        string $proposalExam,
        string $proposalExamShort,
        string $finalExam,
    ): array {
        return [
            'degreeLevel' => $degreeLevel,
            'finalWork' => $finalWork,
            'finalWorkLower' => mb_strtolower($finalWork),
            'proposalExam' => $proposalExam,
            'proposalExamShort' => $proposalExamShort,
            'finalExam' => $finalExam,
        ];
    }
}
