<?php

namespace App\Services;

use App\Enums\SemproExaminerDecision;
use App\Enums\SemproStatus;
use App\Enums\ThesisSubmissionStatus;
use App\Models\Sempro;
use App\Models\SemproRevision;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisRevision;
use App\Models\ThesisSubmission;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ThesisProjectAdminService
{
    /**
     * @param  array<int, int>  $examinerUserIds
     */
    public function scheduleSempro(
        ThesisProject $project,
        int $scheduledBy,
        string $scheduledFor,
        string $location,
        string $mode,
        array $examinerUserIds,
    ): ThesisProject {
        $submission = $this->resolveLegacySubmission($project);

        DB::transaction(function () use ($submission, $scheduledBy, $scheduledFor, $location, $mode, $examinerUserIds): void {
            $sempro = app(SemproWorkflowService::class)->ensureSemproForSubmission($submission, $scheduledBy);

            $sempro->forceFill([
                'scheduled_for' => $scheduledFor,
                'location' => $location,
                'mode' => $mode,
                'created_by' => $scheduledBy,
            ])->save();

            $workflow = app(SemproWorkflowService::class);
            $workflow->assignExaminers($sempro->fresh(), $examinerUserIds, $scheduledBy);
            $workflow->scheduleSempro($sempro->fresh());
        });

        app(LegacyThesisProjectBackfillService::class)->backfill($project->student_user_id);

        $freshProject = $this->freshProject($project);

        $this->recordEvent(
            $freshProject,
            actorUserId: $scheduledBy,
            eventType: 'sempro_scheduled',
            label: 'Sempro dijadwalkan',
            description: sprintf('Sempro dijadwalkan di %s.', $location),
            occurredAt: $scheduledFor,
        );

        return $this->freshProject($project);
    }

    public function finalizeSempro(
        ThesisProject $project,
        int $decidedBy,
        string $result,
        string $notes,
        ?string $revisionDueAt = null,
    ): ThesisProject {
        $submission = $this->resolveLegacySubmission($project);
        $sempro = Sempro::query()
            ->where('thesis_submission_id', $submission->getKey())
            ->latest('id')
            ->with(['examiners', 'revisions'])
            ->first();

        if (! $sempro instanceof Sempro) {
            throw new RuntimeException('Sempro belum tersedia untuk proyek ini.');
        }

        DB::transaction(function () use ($submission, $sempro, $decidedBy, $result, $notes, $revisionDueAt): void {
            if ($result === 'pass') {
                $sempro->examiners->each(function ($examiner) use ($decidedBy, $notes): void {
                    $examiner->forceFill([
                        'decision' => SemproExaminerDecision::Approved->value,
                        'decision_notes' => $notes,
                        'decided_at' => now(),
                        'assigned_by' => $decidedBy,
                    ])->save();
                });

                app(SemproWorkflowService::class)->approveSempro($sempro->fresh(), $decidedBy);

                $sempro->revisions()
                    ->whereIn('status', ['open', 'submitted'])
                    ->update([
                        'status' => 'resolved',
                        'resolved_at' => now(),
                        'resolved_by_user_id' => $decidedBy,
                        'resolution_notes' => $notes,
                    ]);

                return;
            }

            $sempro->forceFill([
                'status' => SemproStatus::RevisionOpen->value,
                'revision_due_at' => $revisionDueAt,
            ])->save();

            $submission->forceFill([
                'status' => ThesisSubmissionStatus::RevisiSempro->value,
            ])->save();

            $sempro->examiners->each(function ($examiner) use ($decidedBy, $notes): void {
                $examiner->forceFill([
                    'decision' => SemproExaminerDecision::NeedsRevision->value,
                    'decision_notes' => $notes,
                    'decided_at' => now(),
                    'assigned_by' => $decidedBy,
                ])->save();
            });

            SemproRevision::query()->updateOrCreate(
                [
                    'sempro_id' => $sempro->getKey(),
                    'notes' => $notes,
                ],
                [
                    'status' => 'open',
                    'due_at' => $revisionDueAt,
                    'requested_by_user_id' => $decidedBy,
                    'resolved_at' => null,
                    'resolved_by_user_id' => null,
                    'resolution_notes' => null,
                ],
            );
        });

        app(LegacyThesisProjectBackfillService::class)->backfill($project->student_user_id);

        $freshProject = $this->freshProject($project);

        $this->recordEvent(
            $freshProject,
            actorUserId: $decidedBy,
            eventType: $result === 'pass' ? 'sempro_completed' : 'revision_opened',
            label: $result === 'pass' ? 'Sempro selesai' : 'Revisi sempro dibuka',
            description: $notes,
            occurredAt: now()->toDateTimeString(),
        );

        return $this->freshProject($project);
    }

    public function assignSupervisors(
        ThesisProject $project,
        int $assignedBy,
        int $primaryLecturerUserId,
        ?int $secondaryLecturerUserId,
        ?string $notes,
    ): ThesisProject {
        $submission = $this->resolveLegacySubmission($project);

        if (in_array($submission->status, [
            ThesisSubmissionStatus::SemproSelesai->value,
            ThesisSubmissionStatus::PembimbingDitetapkan->value,
        ], true)) {
            $submission->forceFill([
                'status' => ThesisSubmissionStatus::PembimbingDitetapkan->value,
            ])->save();
        }

        app(MentorshipAssignmentService::class)->syncStudentAdvisors(
            studentUserId: $project->student_user_id,
            assignedBy: $assignedBy,
            primaryLecturerUserId: $primaryLecturerUserId,
            secondaryLecturerUserId: $secondaryLecturerUserId,
            notes: $notes,
        );

        $freshProject = $this->freshProject($project);

        $this->recordEvent(
            $freshProject,
            actorUserId: $assignedBy,
            eventType: 'supervisor_assigned',
            label: 'Pembimbing diperbarui',
            description: $notes,
            occurredAt: now()->toDateTimeString(),
        );

        return $this->freshProject($project);
    }

    /**
     * @param  array<string, int>  $examinerAssignments
     */
    public function scheduleSidang(
        ThesisProject $project,
        int $createdBy,
        string $scheduledFor,
        string $location,
        string $mode,
        array $examinerAssignments,
        ?string $notes = null,
    ): ThesisDefense {
        return DB::transaction(function () use ($project, $createdBy, $scheduledFor, $location, $mode, $examinerAssignments, $notes): ThesisDefense {
            $attemptNo = (int) $project->sidangDefenses()->max('attempt_no');
            $openDefense = $project->sidangDefenses()
                ->whereIn('status', ['draft', 'scheduled'])
                ->latest('attempt_no')
                ->first();

            $defense = $openDefense instanceof ThesisDefense
                ? $openDefense
                : ThesisDefense::query()->create([
                    'project_id' => $project->getKey(),
                    'title_version_id' => $this->resolveCurrentTitleVersion($project)?->getKey(),
                    'type' => 'sidang',
                    'attempt_no' => $attemptNo + 1,
                    'status' => 'draft',
                    'result' => 'pending',
                    'mode' => 'offline',
                    'created_by' => $createdBy,
                ]);

            $defense->forceFill([
                'title_version_id' => $this->resolveCurrentTitleVersion($project)?->getKey(),
                'status' => 'scheduled',
                'result' => 'pending',
                'scheduled_for' => $scheduledFor,
                'location' => $location,
                'mode' => $mode,
                'created_by' => $createdBy,
                'notes' => $notes,
            ])->save();

            $defense->examiners()->delete();

            foreach ([
                'chair_user_id' => ['role' => 'chair', 'order' => 1],
                'secretary_user_id' => ['role' => 'secretary', 'order' => 2],
                'examiner_user_id' => ['role' => 'examiner', 'order' => 3],
            ] as $field => $config) {
                ThesisDefenseExaminer::query()->create([
                    'defense_id' => $defense->getKey(),
                    'lecturer_user_id' => $examinerAssignments[$field],
                    'role' => $config['role'],
                    'order_no' => $config['order'],
                    'decision' => 'pending',
                    'assigned_by' => $createdBy,
                ]);
            }

            $project->forceFill([
                'phase' => 'sidang',
                'state' => 'active',
                'closed_by' => null,
                'completed_at' => null,
            ])->save();

            $this->recordEvent(
                $project->fresh(),
                actorUserId: $createdBy,
                eventType: 'sidang_scheduled',
                label: 'Sidang dijadwalkan',
                description: $notes,
                occurredAt: $scheduledFor,
            );

            return $defense->fresh(['examiners.lecturer']);
        });
    }

    public function completeSidang(
        ThesisProject $project,
        int $decidedBy,
        string $result,
        string $notes,
        ?string $revisionNotes = null,
        ?string $revisionDueAt = null,
    ): ThesisProject {
        return DB::transaction(function () use ($project, $decidedBy, $result, $notes, $revisionNotes, $revisionDueAt): ThesisProject {
            $defense = $project->sidangDefenses()
                ->latest('attempt_no')
                ->with('examiners')
                ->first();

            if (! $defense instanceof ThesisDefense) {
                throw new RuntimeException('Sidang belum dijadwalkan untuk proyek ini.');
            }

            $defense->forceFill([
                'status' => 'completed',
                'result' => $result,
                'decided_by' => $decidedBy,
                'decision_at' => now(),
                'notes' => $notes,
            ])->save();

            $defense->examiners->each(function ($examiner) use ($decidedBy, $result, $notes): void {
                $examiner->forceFill([
                    'decision' => $result,
                    'notes' => $notes,
                    'decided_at' => now(),
                    'assigned_by' => $decidedBy,
                ])->save();
            });

            if ($result === 'pass') {
                $project->forceFill([
                    'phase' => 'completed',
                    'state' => 'completed',
                    'completed_at' => now(),
                    'closed_by' => $decidedBy,
                ])->save();
            }

            if ($result === 'pass_with_revision') {
                ThesisRevision::query()->create([
                    'project_id' => $project->getKey(),
                    'defense_id' => $defense->getKey(),
                    'requested_by_user_id' => $decidedBy,
                    'status' => 'open',
                    'notes' => $revisionNotes ?? $notes,
                    'due_at' => $revisionDueAt,
                ]);

                $project->forceFill([
                    'phase' => 'sidang',
                    'state' => 'active',
                    'completed_at' => null,
                    'closed_by' => null,
                ])->save();
            }

            if ($result === 'fail') {
                $project->forceFill([
                    'phase' => 'sidang',
                    'state' => 'active',
                    'completed_at' => null,
                    'closed_by' => null,
                ])->save();
            }

            $this->recordEvent(
                $project->fresh(),
                actorUserId: $decidedBy,
                eventType: 'sidang_completed',
                label: 'Sidang diselesaikan',
                description: $notes,
                occurredAt: now()->toDateTimeString(),
            );

            if ($result === 'pass_with_revision') {
                $this->recordEvent(
                    $project->fresh(),
                    actorUserId: $decidedBy,
                    eventType: 'revision_opened',
                    label: 'Revisi sidang dibuka',
                    description: $revisionNotes ?? $notes,
                    occurredAt: now()->toDateTimeString(),
                );
            }

            return $project->fresh();
        });
    }

    private function resolveLegacySubmission(ThesisProject $project): ThesisSubmission
    {
        $submission = ThesisSubmission::query()->find($project->legacy_thesis_submission_id);

        if ($submission instanceof ThesisSubmission) {
            return $submission;
        }

        throw new RuntimeException('Proyek ini belum memiliki relasi ke data legacy pengajuan judul.');
    }

    private function resolveCurrentTitleVersion(ThesisProject $project): ?ThesisProjectTitle
    {
        $project->loadMissing(['latestTitle', 'titles']);

        /** @var ThesisProjectTitle|null $approvedTitle */
        $approvedTitle = $project->titles
            ->where('status', 'approved')
            ->sortByDesc('version_no')
            ->first();

        return $approvedTitle ?? $project->latestTitle;
    }

    private function freshProject(ThesisProject $project): ThesisProject
    {
        return ThesisProject::query()->findOrFail($project->getKey());
    }

    private function recordEvent(
        ThesisProject $project,
        ?int $actorUserId,
        string $eventType,
        string $label,
        ?string $description,
        string $occurredAt,
    ): void {
        ThesisProjectEvent::query()->create([
            'project_id' => $project->getKey(),
            'actor_user_id' => $actorUserId,
            'event_type' => $eventType,
            'label' => $label,
            'description' => $description,
            'payload' => null,
            'occurred_at' => $occurredAt,
        ]);
    }
}
