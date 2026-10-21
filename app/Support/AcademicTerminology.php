<?php

namespace App\Support;

use App\Enums\DegreeLevel;
use App\Models\ThesisProject;
use App\Models\User;

/**
 * Single source for user-facing academic labels. See docs/GLOSSARY.md.
 */
final class AcademicTerminology
{
    private const PROPOSAL_DOCUMENT = 'Naskah Proposal';

    /**
     * Labels for `thesis_projects.phase`, identical for every degree level.
     *
     * @var array<string, string>
     */
    private const PHASES = [
        'title_review' => 'Review Judul',
        'sempro' => 'Tahap Proposal',
        'research' => 'Riset',
        'sidang' => 'Tahap Sidang',
        'completed' => 'Selesai',
    ];

    /**
     * Labels for a single student of the given degree level and curriculum.
     *
     * @return array<string, string>
     */
    public static function forDegreeLevel(DegreeLevel|string|null $degreeLevel, bool $isObeCurriculum = false): array
    {
        $level = $degreeLevel instanceof DegreeLevel
            ? $degreeLevel
            : DegreeLevel::tryFrom(strtolower(trim((string) $degreeLevel)));

        [$proposalExam, $proposalExamShort] = self::proposalExamLabels($isObeCurriculum);

        return match ($level) {
            DegreeLevel::D3 => self::labels(
                degreeLevel: 'D3',
                finalWork: 'Tugas Akhir',
                proposalExam: $proposalExam,
                proposalExamShort: $proposalExamShort,
                finalExam: 'Sidang Tugas Akhir',
                finalExamShort: 'Sidang',
            ),
            DegreeLevel::S2 => self::labels(
                degreeLevel: 'S2',
                finalWork: 'Tesis',
                proposalExam: $proposalExam,
                proposalExamShort: $proposalExamShort,
                finalExam: 'Sidang Tesis',
                finalExamShort: 'Sidang',
            ),
            default => self::labels(
                degreeLevel: $level?->label() ?? 'S1',
                finalWork: 'Skripsi',
                proposalExam: $proposalExam,
                proposalExamShort: $proposalExamShort,
                finalExam: 'Sidang Skripsi',
                finalExamShort: 'Sidang',
            ),
        };
    }

    /**
     * Cross-degree labels for lists, menus, and reports that mix students of
     * several degree levels (admin, kaprodi, dosen overviews, public pages).
     *
     * @return array<string, string>
     */
    public static function neutral(): array
    {
        return self::labels(
            degreeLevel: '',
            finalWork: 'Tugas Akhir',
            proposalExam: 'Sempro / Sinopsis',
            proposalExamShort: 'Sempro / Sinopsis',
            finalExam: 'Sidang Akhir',
            finalExamShort: 'Sidang Akhir',
        );
    }

    /**
     * Labels for one student. Everything that changes a student's labels
     * (degree level, curriculum) is read here so callers never pass it in.
     *
     * @return array<string, string>
     */
    public static function forStudent(?User $student): array
    {
        $student?->loadMissing('mahasiswaProfile');

        return self::forDegreeLevel(
            $student?->mahasiswaProfile?->degree_level,
            (bool) $student?->mahasiswaProfile?->is_obe_curriculum,
        );
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
     * Label for a `thesis_projects.phase` value; unknown values are returned as-is.
     */
    public static function phaseLabel(?string $phase): string
    {
        return self::PHASES[$phase] ?? (string) $phase;
    }

    /**
     * @return array<string, string>
     */
    public static function phaseLabels(): array
    {
        return self::PHASES;
    }

    /**
     * Label for a `thesis_defenses.type` value (`sempro` or `sidang`).
     *
     * @param  array<string, string>  $terms  result of forStudent(), forProject(), or neutral()
     */
    public static function defenseLabel(array $terms, ?string $type, bool $short = false): string
    {
        if ($type === 'sidang') {
            return $short ? $terms['finalExamShort'] : $terms['finalExam'];
        }

        return $short ? $terms['proposalExamShort'] : $terms['proposalExam'];
    }

    /**
     * Stage-1 exam labels for a single student: [full, short].
     *
     * @return array{0: string, 1: string}
     */
    private static function proposalExamLabels(bool $isObeCurriculum): array
    {
        return $isObeCurriculum
            ? ['Ujian Sinopsis', 'Sinopsis']
            : ['Seminar Proposal', 'Sempro'];
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
        string $finalExamShort,
    ): array {
        return [
            'degreeLevel' => $degreeLevel,
            'finalWork' => $finalWork,
            'finalWorkLower' => mb_strtolower($finalWork),
            'proposalDocument' => self::PROPOSAL_DOCUMENT,
            'proposalExam' => $proposalExam,
            'proposalExamShort' => $proposalExamShort,
            'finalExam' => $finalExam,
            'finalExamShort' => $finalExamShort,
            'titleReviewPhase' => self::PHASES['title_review'],
            'proposalPhase' => self::PHASES['sempro'],
            'researchPhase' => self::PHASES['research'],
            'finalExamPhase' => self::PHASES['sidang'],
            'completedPhase' => self::PHASES['completed'],
        ];
    }
}
