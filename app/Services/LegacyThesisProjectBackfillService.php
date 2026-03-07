<?php

namespace App\Services;

use App\Enums\SemproExaminerDecision;
use App\Enums\SemproStatus;
use App\Enums\ThesisSubmissionStatus;
use App\Models\MentorshipAssignment;
use App\Models\Sempro;
use App\Models\SemproExaminer;
use App\Models\SemproRevision;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisRevision;
use App\Models\ThesisSubmission;
use App\Models\ThesisSupervisorAssignment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LegacyThesisProjectBackfillService
{
    /**
     * @return array<string, int>
     */
    public function backfill(?int $studentUserId = null): array
    {
        $totals = [
            'projects_processed' => 0,
            'titles_processed' => 0,
            'defenses_processed' => 0,
            'examiners_processed' => 0,
            'revisions_processed' => 0,
            'documents_processed' => 0,
            'supervisor_assignments_processed' => 0,
        ];

        $submissions = ThesisSubmission::query()
            ->with([
                'student.mahasiswaProfile',
                'sempros.examiners',
                'sempros.revisions',
            ])
            ->when(
                $studentUserId !== null,
                static fn($query) => $query->where('student_user_id', $studentUserId),
            )
            ->orderBy('id')
            ->get();

        foreach ($submissions as $submission) {
            DB::transaction(function () use ($submission, &$totals): void {
                $project = $this->upsertProject($submission);
                $title = $this->upsertInitialTitle($project, $submission);

                $totals['projects_processed']++;
                $totals['titles_processed']++;
                $totals['documents_processed'] += $this->upsertProposalDocument($project, $title, $submission);

                $defenseCounts = $this->upsertSemproAttempts($project, $title, $submission->sempros);
                $totals['defenses_processed'] += $defenseCounts['defenses'];
                $totals['examiners_processed'] += $defenseCounts['examiners'];
                $totals['revisions_processed'] += $defenseCounts['revisions'];

                $totals['supervisor_assignments_processed'] += $this->upsertSupervisorAssignments(
                    $project,
                    $submission->student_user_id,
                );
            });
        }

        return $totals;
    }

    private function upsertProject(ThesisSubmission $submission): ThesisProject
    {
        $programStudiId = $submission->program_studi_id
            ?? $submission->student?->mahasiswaProfile?->program_studi_id;

        if ($programStudiId === null) {
            throw new RuntimeException(sprintf(
                'Cannot backfill thesis submission [%d] because program_studi_id is missing.',
                $submission->getKey(),
            ));
        }

        return ThesisProject::query()->updateOrCreate(
            ['legacy_thesis_submission_id' => $submission->getKey()],
            [
                'student_user_id' => $submission->student_user_id,
                'program_studi_id' => $programStudiId,
                'phase' => $this->mapProjectPhase($submission->status),
                'state' => $submission->is_active ? 'active' : 'on_hold',
                'started_at' => $submission->submitted_at ?? $submission->created_at,
                'completed_at' => null,
                'cancelled_at' => null,
                'created_by' => null,
                'closed_by' => null,
                'notes' => null,
            ],
        );
    }

    private function upsertInitialTitle(ThesisProject $project, ThesisSubmission $submission): ThesisProjectTitle
    {
        return ThesisProjectTitle::query()->updateOrCreate(
            [
                'project_id' => $project->getKey(),
                'version_no' => 1,
            ],
            [
                'title_id' => $submission->title_id,
                'title_en' => $submission->title_en,
                'proposal_summary' => $submission->proposal_summary,
                'status' => $this->mapTitleStatus($submission->status),
                'submitted_by_user_id' => $submission->student_user_id,
                'submitted_at' => $submission->submitted_at,
                'decided_by_user_id' => $submission->approved_by,
                'decided_at' => $submission->approved_at,
                'decision_notes' => null,
            ],
        );
    }

    private function upsertProposalDocument(
        ThesisProject $project,
        ThesisProjectTitle $title,
        ThesisSubmission $submission,
    ): int {
        if (! is_string($submission->proposal_file_path) || trim($submission->proposal_file_path) === '') {
            return 0;
        }

        ThesisDocument::query()->updateOrCreate(
            [
                'project_id' => $project->getKey(),
                'title_version_id' => $title->getKey(),
                'kind' => 'proposal',
            ],
            [
                'defense_id' => null,
                'revision_id' => null,
                'uploaded_by_user_id' => $submission->student_user_id,
                'status' => 'active',
                'version_no' => $title->version_no,
                'title' => 'Proposal Skripsi',
                'notes' => 'Dokumen proposal dari pengajuan judul & proposal legacy.',
                'storage_disk' => 'public',
                'storage_path' => $submission->proposal_file_path,
                'file_name' => basename($submission->proposal_file_path),
                'mime_type' => 'application/pdf',
                'file_size_kb' => null,
                'uploaded_at' => $submission->submitted_at ?? $submission->created_at,
            ],
        );

        return 1;
    }

    /**
     * @param  Collection<int, Sempro>  $sempros
     * @return array{defenses: int, examiners: int, revisions: int}
     */
    private function upsertSemproAttempts(ThesisProject $project, ThesisProjectTitle $title, Collection $sempros): array
    {
        $counts = [
            'defenses' => 0,
            'examiners' => 0,
            'revisions' => 0,
        ];

        foreach ($sempros->sortBy('id')->values() as $index => $sempro) {
            $defense = ThesisDefense::query()->updateOrCreate(
                ['legacy_sempro_id' => $sempro->getKey()],
                [
                    'project_id' => $project->getKey(),
                    'title_version_id' => $title->getKey(),
                    'type' => 'sempro',
                    'attempt_no' => $index + 1,
                    'status' => $this->mapDefenseStatus($sempro->status),
                    'result' => $this->mapDefenseResult($sempro->status),
                    'scheduled_for' => $sempro->scheduled_for,
                    'location' => $sempro->location,
                    'mode' => $sempro->mode,
                    'created_by' => $sempro->created_by,
                    'decided_by' => $sempro->approved_by,
                    'decision_at' => $this->resolveDefenseDecisionAt($sempro),
                    'notes' => null,
                ],
            );

            $counts['defenses']++;
            $counts['examiners'] += $this->upsertSemproExaminers($defense, $sempro->examiners);
            $counts['revisions'] += $this->upsertSemproRevisions($project, $defense, $sempro->revisions);
        }

        return $counts;
    }

    /**
     * @param  Collection<int, SemproExaminer>  $examiners
     */
    private function upsertSemproExaminers(ThesisDefense $defense, Collection $examiners): int
    {
        $count = 0;

        foreach ($examiners->sortBy('examiner_order')->values() as $examiner) {
            ThesisDefenseExaminer::query()->updateOrCreate(
                ['legacy_sempro_examiner_id' => $examiner->getKey()],
                [
                    'defense_id' => $defense->getKey(),
                    'lecturer_user_id' => $examiner->examiner_user_id,
                    'role' => 'examiner',
                    'order_no' => $examiner->examiner_order,
                    'decision' => $this->mapExaminerDecision($examiner->decision),
                    'score' => $examiner->score,
                    'notes' => $examiner->decision_notes,
                    'decided_at' => $examiner->decided_at,
                    'assigned_by' => $examiner->assigned_by,
                ],
            );

            $count++;
        }

        return $count;
    }

    /**
     * @param  Collection<int, SemproRevision>  $revisions
     */
    private function upsertSemproRevisions(ThesisProject $project, ThesisDefense $defense, Collection $revisions): int
    {
        $count = 0;

        foreach ($revisions->sortBy('id')->values() as $revision) {
            ThesisRevision::query()->updateOrCreate(
                ['legacy_sempro_revision_id' => $revision->getKey()],
                [
                    'project_id' => $project->getKey(),
                    'defense_id' => $defense->getKey(),
                    'requested_by_user_id' => $revision->requested_by_user_id,
                    'status' => $revision->status,
                    'notes' => $revision->notes,
                    'due_at' => $revision->due_at,
                    'submitted_at' => null,
                    'resolved_at' => $revision->resolved_at,
                    'resolved_by_user_id' => $revision->resolved_by_user_id,
                    'resolution_notes' => $revision->resolution_notes,
                ],
            );

            $count++;
        }

        return $count;
    }

    private function upsertSupervisorAssignments(ThesisProject $project, int $studentUserId): int
    {
        $count = 0;

        $assignments = MentorshipAssignment::query()
            ->where('student_user_id', $studentUserId)
            ->orderBy('id')
            ->get();

        foreach ($assignments as $assignment) {
            ThesisSupervisorAssignment::query()->updateOrCreate(
                ['legacy_mentorship_assignment_id' => $assignment->getKey()],
                [
                    'project_id' => $project->getKey(),
                    'lecturer_user_id' => $assignment->lecturer_user_id,
                    'role' => $assignment->advisor_type,
                    'status' => $assignment->status,
                    'assigned_by' => $assignment->assigned_by,
                    'started_at' => $assignment->started_at,
                    'ended_at' => $assignment->ended_at,
                    'notes' => $assignment->notes,
                ],
            );

            $count++;
        }

        return $count;
    }

    private function mapProjectPhase(string $status): string
    {
        return match ($status) {
            ThesisSubmissionStatus::MenungguPersetujuan->value => 'title_review',
            ThesisSubmissionStatus::SemproDijadwalkan->value,
            ThesisSubmissionStatus::RevisiSempro->value => 'sempro',
            ThesisSubmissionStatus::SemproSelesai->value,
            ThesisSubmissionStatus::PembimbingDitetapkan->value => 'research',
            default => 'title_review',
        };
    }

    private function mapTitleStatus(string $status): string
    {
        return match ($status) {
            ThesisSubmissionStatus::MenungguPersetujuan->value => 'submitted',
            ThesisSubmissionStatus::SemproDijadwalkan->value,
            ThesisSubmissionStatus::RevisiSempro->value,
            ThesisSubmissionStatus::SemproSelesai->value,
            ThesisSubmissionStatus::PembimbingDitetapkan->value => 'approved',
            default => 'draft',
        };
    }

    private function mapDefenseStatus(string $status): string
    {
        return match ($status) {
            SemproStatus::Draft->value => 'draft',
            SemproStatus::Scheduled->value => 'scheduled',
            SemproStatus::RevisionOpen->value,
            SemproStatus::Approved->value => 'completed',
            default => 'draft',
        };
    }

    private function mapDefenseResult(string $status): string
    {
        return match ($status) {
            SemproStatus::RevisionOpen->value => 'pass_with_revision',
            SemproStatus::Approved->value => 'pass',
            SemproStatus::Draft->value,
            SemproStatus::Scheduled->value => 'pending',
            default => 'pending',
        };
    }

    private function mapExaminerDecision(string $decision): string
    {
        return match ($decision) {
            SemproExaminerDecision::Approved->value => 'pass',
            SemproExaminerDecision::NeedsRevision->value => 'pass_with_revision',
            SemproExaminerDecision::Pending->value => 'pending',
            default => 'pending',
        };
    }

    private function resolveDefenseDecisionAt(Sempro $sempro): ?CarbonInterface
    {
        if ($sempro->approved_at instanceof CarbonInterface) {
            return $sempro->approved_at;
        }

        if ($sempro->status === SemproStatus::Draft->value || $sempro->status === SemproStatus::Scheduled->value) {
            return null;
        }

        /** @var CarbonInterface|null $latestExaminerDecision */
        $latestExaminerDecision = $sempro->examiners
            ->pluck('decided_at')
            ->filter()
            ->sort()
            ->last();

        return $latestExaminerDecision ?? $sempro->updated_at;
    }
}
