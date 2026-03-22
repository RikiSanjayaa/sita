<?php

namespace App\Services;

use App\Enums\AdvisorType;
use App\Enums\AppRole;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipChatThreadParticipant;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisRevision;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ThesisProjectAdminService
{
    public function __construct(
        private readonly RealtimeNotificationService $realtimeNotificationService,
    ) {}

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
        $wasRescheduled = false;

        $updatedProject = DB::transaction(function () use ($project, $scheduledBy, $scheduledFor, $location, $mode, $examinerUserIds, &$wasRescheduled): ThesisProject {
            $defense = $project->semproDefenses()
                ->whereIn('status', ['draft', 'scheduled'])
                ->latest('attempt_no')
                ->first();
            $wasRescheduled = $defense instanceof ThesisDefense && $defense->status === 'scheduled';

            $attemptNo = (int) $project->semproDefenses()->max('attempt_no');

            if (! $defense instanceof ThesisDefense) {
                $defense = ThesisDefense::query()->create([
                    'project_id' => $project->getKey(),
                    'title_version_id' => $this->resolveCurrentTitleVersion($project)?->getKey(),
                    'type' => 'sempro',
                    'attempt_no' => $attemptNo + 1,
                    'status' => 'draft',
                    'result' => 'pending',
                    'mode' => 'offline',
                    'created_by' => $scheduledBy,
                ]);
            }

            $defense->forceFill([
                'title_version_id' => $this->resolveCurrentTitleVersion($project)?->getKey(),
                'status' => 'scheduled',
                'result' => 'pending',
                'scheduled_for' => $scheduledFor,
                'location' => $location,
                'mode' => $mode,
                'created_by' => $scheduledBy,
                'decided_by' => null,
                'decision_at' => null,
                'notes' => null,
            ])->save();

            $this->syncDefenseExaminers($defense, $examinerUserIds, $scheduledBy);

            $project->forceFill([
                'phase' => 'sempro',
                'state' => 'active',
                'completed_at' => null,
                'closed_by' => null,
            ])->save();

            $this->createOrRefreshSemproThread($defense->fresh(['examiners']), $project->student_user_id);

            $this->recordEvent(
                $project->fresh(),
                actorUserId: $scheduledBy,
                eventType: $wasRescheduled ? 'sempro_rescheduled' : 'sempro_scheduled',
                label: $wasRescheduled ? 'Sempro dijadwalkan ulang' : 'Sempro dijadwalkan',
                description: sprintf(
                    $wasRescheduled ? 'Sempro dijadwalkan ulang di %s.' : 'Sempro dijadwalkan di %s.',
                    $location,
                ),
                occurredAt: $scheduledFor,
            );

            return $project->fresh();
        });

        $this->notifyStudentAboutDefenseSchedule(
            project: $updatedProject,
            type: 'sempro',
            scheduledFor: $scheduledFor,
            location: $location,
            wasRescheduled: $wasRescheduled,
        );
        $this->notifyLecturersAboutDefenseSchedule(
            project: $updatedProject,
            defense: $updatedProject->semproDefenses()->latest('attempt_no')->with('examiners.lecturer')->first(),
            type: 'sempro',
            scheduledFor: $scheduledFor,
            location: $location,
            wasRescheduled: $wasRescheduled,
        );

        return $updatedProject;
    }

    public function finalizeSempro(
        ThesisProject $project,
        int $decidedBy,
        string $result,
        string $notes,
        ?string $revisionDueAt = null,
    ): ThesisProject {
        $defense = $project->semproDefenses()
            ->latest('attempt_no')
            ->with(['examiners', 'revisions'])
            ->first();

        if (! $defense instanceof ThesisDefense) {
            throw new RuntimeException('Sempro belum tersedia untuk proyek ini.');
        }

        if ($defense->status !== 'awaiting_finalization') {
            throw new RuntimeException('Sempro belum siap ditetapkan. Tunggu seluruh dosen penguji mengirim keputusan.');
        }

        $updatedProject = DB::transaction(function () use ($project, $defense, $decidedBy, $result, $notes, $revisionDueAt): ThesisProject {
            $defense->forceFill([
                'status' => 'completed',
                'result' => $result,
                'decided_by' => $decidedBy,
                'decision_at' => now(),
                'notes' => $notes,
            ])->save();

            if ($result !== 'pass_with_revision') {
                $this->closeDefenseRevisions($defense, $decidedBy, $notes);
            }

            if ($result === 'pass') {
                $project->forceFill([
                    'phase' => 'research',
                    'state' => 'active',
                    'completed_at' => null,
                    'closed_by' => null,
                ])->save();
            }

            if ($result === 'pass_with_revision') {
                $this->syncDefenseRevisionsFromExaminers($defense, $notes, $revisionDueAt);

                $project->forceFill([
                    'phase' => 'sempro',
                    'state' => 'active',
                    'completed_at' => null,
                    'closed_by' => null,
                ])->save();
            }

            if ($result === 'fail') {
                $project->forceFill([
                    'phase' => 'sempro',
                    'state' => 'active',
                    'completed_at' => null,
                    'closed_by' => null,
                ])->save();
            }

            $freshProject = $project->fresh();

            $this->recordEvent(
                $freshProject,
                actorUserId: $decidedBy,
                eventType: match ($result) {
                    'pass' => 'sempro_completed',
                    'pass_with_revision' => 'sempro_completed',
                    'fail' => 'sempro_failed',
                    default => 'sempro_completed',
                },
                label: match ($result) {
                    'pass' => 'Sempro selesai',
                    'pass_with_revision' => 'Sempro selesai dengan revisi',
                    'fail' => 'Sempro tidak lulus',
                    default => 'Hasil sempro ditetapkan',
                },
                description: $notes,
                occurredAt: now()->toDateTimeString(),
            );

            if ($result === 'pass_with_revision') {
                $this->recordEvent(
                    $freshProject,
                    actorUserId: $decidedBy,
                    eventType: 'revision_opened',
                    label: 'Revisi sempro dibuka',
                    description: $notes,
                    occurredAt: now()->toDateTimeString(),
                );
            }

            return $freshProject;
        });

        $this->notifyStudentAboutDefenseResult(
            $updatedProject,
            type: 'sempro',
            result: $result,
            notes: $notes,
        );

        return $updatedProject;
    }

    public function approveSemproRevision(
        ThesisProject $project,
        int $resolvedBy,
        ?string $resolutionNotes = null,
    ): ThesisProject {
        $defense = $project->semproDefenses()
            ->latest('attempt_no')
            ->with('revisions')
            ->first();

        if (! $defense instanceof ThesisDefense || $defense->status !== 'completed' || $defense->result !== 'pass_with_revision') {
            throw new RuntimeException('Revisi sempro belum tersedia untuk proyek ini.');
        }

        if (! $defense->revisions->whereIn('status', ['open', 'submitted'])->isNotEmpty()) {
            throw new RuntimeException('Tidak ada revisi sempro aktif yang perlu disetujui.');
        }

        $notes = $resolutionNotes ?: 'Revisi sempro disetujui. Mahasiswa dapat lanjut ke tahap penelitian.';

        $updatedProject = DB::transaction(function () use ($defense, $notes, $project, $resolvedBy): ThesisProject {
            $this->closeDefenseRevisions($defense, $resolvedBy, $notes);

            $project->forceFill([
                'phase' => 'research',
                'state' => 'active',
                'completed_at' => null,
                'closed_by' => null,
            ])->save();

            $freshProject = $project->fresh();

            $this->recordEvent(
                $freshProject,
                actorUserId: $resolvedBy,
                eventType: 'revision_resolved',
                label: 'Revisi sempro disetujui',
                description: $notes,
                occurredAt: now()->toDateTimeString(),
            );

            return $freshProject;
        });

        $this->notifyStudentAboutRevisionApproval($updatedProject, 'sempro', $notes);

        return $updatedProject;
    }

    public function assignSupervisors(
        ThesisProject $project,
        int $assignedBy,
        int $primaryLecturerUserId,
        ?int $secondaryLecturerUserId,
        ?string $notes,
    ): ThesisProject {
        if ($secondaryLecturerUserId !== null && $primaryLecturerUserId === $secondaryLecturerUserId) {
            throw new RuntimeException('Pembimbing 1 dan Pembimbing 2 harus berbeda.');
        }

        $this->assertSupervisorEligible($project, $primaryLecturerUserId, 'Pembimbing 1');

        if ($secondaryLecturerUserId !== null) {
            $this->assertSupervisorEligible($project, $secondaryLecturerUserId, 'Pembimbing 2');
        }

        $updatedProject = DB::transaction(function () use ($project, $assignedBy, $primaryLecturerUserId, $secondaryLecturerUserId, $notes): ThesisProject {
            $this->syncSupervisorAssignment(
                project: $project,
                assignedBy: $assignedBy,
                role: AdvisorType::Primary->value,
                lecturerUserId: $primaryLecturerUserId,
                notes: $notes,
            );

            $this->syncSupervisorAssignment(
                project: $project,
                assignedBy: $assignedBy,
                role: AdvisorType::Secondary->value,
                lecturerUserId: $secondaryLecturerUserId,
                notes: $notes,
            );

            $project->forceFill([
                'phase' => 'research',
                'state' => 'active',
                'completed_at' => null,
                'closed_by' => null,
            ])->save();

            $freshProject = $project->fresh();

            $this->recordEvent(
                $freshProject,
                actorUserId: $assignedBy,
                eventType: 'supervisor_assigned',
                label: 'Pembimbing diperbarui',
                description: $notes,
                occurredAt: now()->toDateTimeString(),
            );

            return $freshProject;
        });

        $this->notifyStudentAboutSupervisorAssignment($updatedProject);

        return $updatedProject;
    }

    /**
     * @param  array<int, int>  $panelUserIds
     */
    public function scheduleSidang(
        ThesisProject $project,
        int $createdBy,
        string $scheduledFor,
        string $location,
        string $mode,
        array $panelUserIds,
        ?string $notes = null,
    ): ThesisDefense {
        $wasRescheduled = false;

        $defense = DB::transaction(function () use ($project, $createdBy, $scheduledFor, $location, $mode, $panelUserIds, $notes, &$wasRescheduled): ThesisDefense {
            $attemptNo = (int) $project->sidangDefenses()->max('attempt_no');
            $openDefense = $project->sidangDefenses()
                ->whereIn('status', ['draft', 'scheduled'])
                ->latest('attempt_no')
                ->first();
            $wasRescheduled = $openDefense instanceof ThesisDefense && $openDefense->status === 'scheduled';

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
                'decided_by' => null,
                'decision_at' => null,
                'notes' => $notes,
            ])->save();

            $this->syncSidangExaminers($project, $defense, $panelUserIds, $createdBy);

            $project->forceFill([
                'phase' => 'sidang',
                'state' => 'active',
                'closed_by' => null,
                'completed_at' => null,
            ])->save();

            $this->createOrRefreshSidangThread(
                $defense->fresh(['examiners']),
                $project->student_user_id,
            );

            $this->recordEvent(
                $project->fresh(),
                actorUserId: $createdBy,
                eventType: $wasRescheduled ? 'sidang_rescheduled' : 'sidang_scheduled',
                label: $wasRescheduled ? 'Sidang dijadwalkan ulang' : 'Sidang dijadwalkan',
                description: $notes,
                occurredAt: $scheduledFor,
            );

            return $defense->fresh(['examiners.lecturer']);
        });

        $this->notifyStudentAboutDefenseSchedule(
            project: $project,
            type: 'sidang',
            scheduledFor: $scheduledFor,
            location: $location,
            wasRescheduled: $wasRescheduled,
        );
        $this->notifyLecturersAboutDefenseSchedule(
            project: $project,
            defense: $defense,
            type: 'sidang',
            scheduledFor: $scheduledFor,
            location: $location,
            wasRescheduled: $wasRescheduled,
        );

        return $defense;
    }

    public function completeSidang(
        ThesisProject $project,
        int $decidedBy,
        string $result,
        string $notes,
        ?string $revisionNotes = null,
        ?string $revisionDueAt = null,
    ): ThesisProject {
        $updatedProject = DB::transaction(function () use ($project, $decidedBy, $result, $notes, $revisionNotes, $revisionDueAt): ThesisProject {
            $defense = $project->sidangDefenses()
                ->latest('attempt_no')
                ->with('examiners')
                ->first();

            if (! $defense instanceof ThesisDefense) {
                throw new RuntimeException('Sidang belum dijadwalkan untuk proyek ini.');
            }

            if ($defense->status !== 'awaiting_finalization') {
                throw new RuntimeException('Sidang belum siap ditetapkan. Tunggu seluruh dosen penguji mengirim keputusan.');
            }

            $defense->forceFill([
                'status' => 'completed',
                'result' => $result,
                'decided_by' => $decidedBy,
                'decision_at' => now(),
                'notes' => $notes,
            ])->save();

            if ($result !== 'pass_with_revision') {
                $this->closeDefenseRevisions($defense, $decidedBy, $notes);
            }

            if ($result === 'pass') {
                $project->supervisorAssignments()
                    ->where('status', 'active')
                    ->update([
                        'status' => 'ended',
                        'ended_at' => now(),
                    ]);

                $project->forceFill([
                    'phase' => 'completed',
                    'state' => 'completed',
                    'completed_at' => now(),
                    'closed_by' => $decidedBy,
                ])->save();
            }

            if ($result === 'pass_with_revision') {
                $this->syncDefenseRevisionsFromExaminers($defense, $revisionNotes ?? $notes, $revisionDueAt);

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
                eventType: match ($result) {
                    'pass' => 'sidang_completed',
                    'pass_with_revision' => 'sidang_completed',
                    'fail' => 'sidang_failed',
                    default => 'sidang_completed',
                },
                label: match ($result) {
                    'pass' => 'Sidang selesai',
                    'pass_with_revision' => 'Sidang selesai dengan revisi',
                    'fail' => 'Sidang tidak lulus',
                    default => 'Hasil sidang ditetapkan',
                },
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

        $this->notifyStudentAboutDefenseResult(
            $updatedProject,
            type: 'sidang',
            result: $result,
            notes: $notes,
        );

        return $updatedProject;
    }

    public function approveSidangRevision(
        ThesisProject $project,
        int $resolvedBy,
        ?string $resolutionNotes = null,
    ): ThesisProject {
        $defense = $project->sidangDefenses()
            ->latest('attempt_no')
            ->with('revisions')
            ->first();

        if (! $defense instanceof ThesisDefense || $defense->status !== 'completed' || $defense->result !== 'pass_with_revision') {
            throw new RuntimeException('Revisi sidang belum tersedia untuk proyek ini.');
        }

        if (! $defense->revisions->whereIn('status', ['open', 'submitted'])->isNotEmpty()) {
            throw new RuntimeException('Tidak ada revisi sidang aktif yang perlu disetujui.');
        }

        $notes = $resolutionNotes ?: 'Revisi sidang disetujui. Proyek tugas akhir dinyatakan selesai.';

        $updatedProject = DB::transaction(function () use ($defense, $notes, $project, $resolvedBy): ThesisProject {
            $this->closeDefenseRevisions($defense, $resolvedBy, $notes);

            $project->supervisorAssignments()
                ->where('status', 'active')
                ->update([
                    'status' => 'ended',
                    'ended_at' => now(),
                ]);

            $project->forceFill([
                'phase' => 'completed',
                'state' => 'completed',
                'completed_at' => now(),
                'closed_by' => $resolvedBy,
            ])->save();

            $freshProject = $project->fresh();

            $this->recordEvent(
                $freshProject,
                actorUserId: $resolvedBy,
                eventType: 'revision_resolved',
                label: 'Revisi sidang disetujui',
                description: $notes,
                occurredAt: now()->toDateTimeString(),
            );

            return $freshProject;
        });

        $this->notifyStudentAboutRevisionApproval($updatedProject, 'sidang', $notes);

        return $updatedProject;
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

    private function closeDefenseRevisions(ThesisDefense $defense, int $resolvedBy, string $resolutionNotes): void
    {
        ThesisRevision::query()
            ->where('project_id', $defense->project_id)
            ->where('defense_id', $defense->getKey())
            ->whereIn('status', ['open', 'submitted'])
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by_user_id' => $resolvedBy,
                'resolution_notes' => $resolutionNotes,
            ]);
    }

    private function syncDefenseRevisionsFromExaminers(ThesisDefense $defense, string $fallbackNotes, ?string $dueAt): void
    {
        $requestingExaminers = $defense->examiners
            ->where('decision', 'pass_with_revision')
            ->values();

        if ($requestingExaminers->isEmpty()) {
            throw new RuntimeException('Tidak ada dosen penguji yang meminta revisi untuk hasil ini.');
        }

        foreach ($requestingExaminers as $examiner) {
            $notes = filled($examiner->revision_notes)
                ? $examiner->revision_notes
                : ($examiner->notes ?: $fallbackNotes);

            $revision = ThesisRevision::query()
                ->where('project_id', $defense->project_id)
                ->where('defense_id', $defense->getKey())
                ->where('requested_by_user_id', $examiner->lecturer_user_id)
                ->whereIn('status', ['open', 'submitted'])
                ->latest('id')
                ->first();

            if ($revision instanceof ThesisRevision) {
                $revision->forceFill([
                    'requested_by_user_id' => $examiner->lecturer_user_id,
                    'status' => 'open',
                    'notes' => $notes,
                    'due_at' => $dueAt,
                    'submitted_at' => null,
                    'resolved_at' => null,
                    'resolved_by_user_id' => null,
                    'resolution_notes' => null,
                ])->save();

                continue;
            }

            ThesisRevision::query()->create([
                'project_id' => $defense->project_id,
                'defense_id' => $defense->getKey(),
                'requested_by_user_id' => $examiner->lecturer_user_id,
                'status' => 'open',
                'notes' => $notes,
                'due_at' => $dueAt,
            ]);
        }
    }

    /**
     * @param  array<int, int>  $examinerUserIds
     */
    private function syncDefenseExaminers(ThesisDefense $defense, array $examinerUserIds, int $assignedBy): void
    {
        $normalizedExaminerIds = collect($examinerUserIds)
            ->map(static fn($id): int => (int) $id)
            ->filter(static fn(int $id): bool => $id > 0)
            ->values();

        if ($normalizedExaminerIds->count() !== 2 || $normalizedExaminerIds->unique()->count() !== 2) {
            throw new RuntimeException('Sempro harus memiliki tepat dua penguji yang berbeda.');
        }

        $defense->examiners()->delete();

        foreach ($normalizedExaminerIds as $index => $examinerUserId) {
            ThesisDefenseExaminer::query()->create([
                'defense_id' => $defense->getKey(),
                'lecturer_user_id' => $examinerUserId,
                'role' => 'examiner',
                'order_no' => $index + 1,
                'decision' => 'pending',
                'assigned_by' => $assignedBy,
            ]);
        }
    }

    /**
     * @param  array<int, int>  $panelUserIds
     */
    private function syncSidangExaminers(ThesisProject $project, ThesisDefense $defense, array $panelUserIds, int $assignedBy): void
    {
        $project->loadMissing('activeSupervisorAssignments');

        $normalizedPanelIds = collect($panelUserIds)
            ->map(static fn($id): int => (int) $id)
            ->filter(static fn(int $id): bool => $id > 0)
            ->values();

        if ($normalizedPanelIds->isEmpty() || $normalizedPanelIds->count() !== $normalizedPanelIds->unique()->count()) {
            throw new RuntimeException('Panel sidang harus berisi dosen yang berbeda.');
        }

        $activeSupervisors = $project->activeSupervisorAssignments
            ->sortBy(fn(ThesisSupervisorAssignment $assignment): int => $assignment->role === AdvisorType::Primary->value ? 1 : 2)
            ->values();

        $supervisorIds = $activeSupervisors
            ->pluck('lecturer_user_id')
            ->map(static fn($id): int => (int) $id)
            ->values();

        if ($supervisorIds->isEmpty()) {
            throw new RuntimeException('Sidang memerlukan pembimbing aktif sebelum dapat dijadwalkan.');
        }

        foreach ($supervisorIds as $index => $supervisorId) {
            if (! $normalizedPanelIds->contains($supervisorId)) {
                throw new RuntimeException(sprintf('Panel sidang harus menyertakan Pembimbing %d.', $index + 1));
            }
        }

        $additionalExaminerIds = $normalizedPanelIds
            ->reject(fn(int $id): bool => $supervisorIds->contains($id))
            ->values();

        if ($additionalExaminerIds->isEmpty()) {
            throw new RuntimeException('Sidang harus memiliki minimal satu penguji tambahan di luar pembimbing aktif.');
        }

        $orderedPanelIds = $supervisorIds
            ->concat($additionalExaminerIds)
            ->values();

        foreach ($orderedPanelIds as $index => $lecturerUserId) {
            $this->assertDefenseExaminerEligible($project, $lecturerUserId, sprintf('Panel sidang #%d', $index + 1));
        }

        $roleByLecturerId = $activeSupervisors
            ->mapWithKeys(fn(ThesisSupervisorAssignment $assignment): array => [
                (int) $assignment->lecturer_user_id => $assignment->role === AdvisorType::Primary->value ? 'primary_supervisor' : 'secondary_supervisor',
            ]);

        $defense->examiners()->delete();

        foreach ($orderedPanelIds as $index => $lecturerUserId) {
            ThesisDefenseExaminer::query()->create([
                'defense_id' => $defense->getKey(),
                'lecturer_user_id' => $lecturerUserId,
                'role' => $roleByLecturerId->get($lecturerUserId, 'examiner'),
                'order_no' => $index + 1,
                'decision' => 'pending',
                'assigned_by' => $assignedBy,
            ]);
        }
    }

    private function syncSupervisorAssignment(
        ThesisProject $project,
        int $assignedBy,
        string $role,
        ?int $lecturerUserId,
        ?string $notes,
    ): void {
        $currentAssignment = $project->supervisorAssignments()
            ->where('role', $role)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if ($lecturerUserId === null) {
            if (! $currentAssignment instanceof ThesisSupervisorAssignment) {
                return;
            }

            $currentAssignment->forceFill([
                'status' => 'ended',
                'ended_at' => now(),
                'notes' => $notes,
            ])->save();

            return;
        }

        if ($currentAssignment instanceof ThesisSupervisorAssignment && $currentAssignment->lecturer_user_id === $lecturerUserId) {
            $currentAssignment->forceFill([
                'notes' => $notes,
            ])->save();

            return;
        }

        if ($currentAssignment instanceof ThesisSupervisorAssignment) {
            $currentAssignment->forceFill([
                'status' => 'ended',
                'ended_at' => now(),
                'notes' => $notes,
            ])->save();
        }

        ThesisSupervisorAssignment::query()->create([
            'project_id' => $project->getKey(),
            'lecturer_user_id' => $lecturerUserId,
            'role' => $role,
            'status' => 'active',
            'assigned_by' => $assignedBy,
            'started_at' => now(),
            'notes' => $notes,
        ]);
    }

    private function createOrRefreshSidangThread(ThesisDefense $defense, int $studentUserId): void
    {
        $thread = MentorshipChatThread::query()
            ->where('student_user_id', $studentUserId)
            ->where('type', 'sidang')
            ->where('context_id', $defense->getKey())
            ->first();

        if (! $thread instanceof MentorshipChatThread) {
            $thread = MentorshipChatThread::query()->create([
                'student_user_id' => $studentUserId,
                'type' => 'sidang',
                'context_id' => $defense->getKey(),
                'label' => 'Sidang',
            ]);
        } elseif ($thread->label !== 'Sidang') {
            $thread->forceFill([
                'label' => 'Sidang',
            ])->save();
        }

        $wasRecentlyCreated = $thread->wasRecentlyCreated;

        MentorshipChatThreadParticipant::query()->updateOrCreate(
            [
                'thread_id' => $thread->getKey(),
                'user_id' => $studentUserId,
            ],
            [
                'role' => 'student',
            ],
        );

        $panelIds = $defense->examiners
            ->pluck('lecturer_user_id')
            ->filter()
            ->map(static fn($id): int => (int) $id)
            ->values();

        MentorshipChatThreadParticipant::query()
            ->where('thread_id', $thread->getKey())
            ->where('role', 'examiner')
            ->when(
                $panelIds->isNotEmpty(),
                static fn($query) => $query->whereNotIn('user_id', $panelIds->all()),
                static fn($query) => $query,
            )
            ->delete();

        foreach ($panelIds as $panelId) {
            MentorshipChatThreadParticipant::query()->updateOrCreate(
                [
                    'thread_id' => $thread->getKey(),
                    'user_id' => $panelId,
                ],
                [
                    'role' => 'examiner',
                ],
            );
        }

        if ($wasRecentlyCreated) {
            $thread->messages()->create([
                'sender_user_id' => null,
                'message_type' => 'text',
                'message' => 'Thread Sidang telah dibuat. Silahkan berdiskusi mengenai sidang di sini.',
                'sent_at' => now(),
            ]);
        }
    }

    private function createOrRefreshSemproThread(ThesisDefense $defense, int $studentUserId): void
    {
        $thread = MentorshipChatThread::query()
            ->where('student_user_id', $studentUserId)
            ->where('type', 'sempro')
            ->when(
                $defense->legacy_sempro_id !== null,
                fn($query) => $query->whereIn('context_id', [$defense->getKey(), $defense->legacy_sempro_id]),
                fn($query) => $query->where('context_id', $defense->getKey()),
            )
            ->first();

        if (! $thread instanceof MentorshipChatThread) {
            $thread = MentorshipChatThread::query()->create([
                'student_user_id' => $studentUserId,
                'type' => 'sempro',
                'context_id' => $defense->getKey(),
                'label' => 'Sempro',
            ]);
        } elseif ($thread->context_id !== $defense->getKey() || $thread->label !== 'Sempro') {
            $thread->forceFill([
                'context_id' => $defense->getKey(),
                'label' => 'Sempro',
            ])->save();
        }

        $wasRecentlyCreated = $thread->wasRecentlyCreated;

        MentorshipChatThreadParticipant::query()->updateOrCreate(
            [
                'thread_id' => $thread->getKey(),
                'user_id' => $studentUserId,
            ],
            [
                'role' => 'student',
            ],
        );

        $examinerIds = $defense->examiners
            ->pluck('lecturer_user_id')
            ->filter()
            ->map(static fn($id): int => (int) $id)
            ->values();

        MentorshipChatThreadParticipant::query()
            ->where('thread_id', $thread->getKey())
            ->where('role', 'examiner')
            ->when(
                $examinerIds->isNotEmpty(),
                static fn($query) => $query->whereNotIn('user_id', $examinerIds->all()),
                static fn($query) => $query,
            )
            ->delete();

        foreach ($examinerIds as $examinerId) {
            MentorshipChatThreadParticipant::query()->updateOrCreate(
                [
                    'thread_id' => $thread->getKey(),
                    'user_id' => $examinerId,
                ],
                [
                    'role' => 'examiner',
                ],
            );
        }

        if ($wasRecentlyCreated) {
            $thread->messages()->create([
                'sender_user_id' => null,
                'message_type' => 'text',
                'message' => 'Thread Seminar Proposal telah dibuat. Silahkan berdiskusi mengenai sempro di sini.',
                'sent_at' => now(),
            ]);
        }
    }

    private function assertSupervisorEligible(ThesisProject $project, int $lecturerUserId, string $label): void
    {
        $project->loadMissing('student.mahasiswaProfile');

        $studentConcentration = $project->student?->mahasiswaProfile?->concentration;

        if (! is_string($studentConcentration) || trim($studentConcentration) === '') {
            throw new RuntimeException('Konsentrasi mahasiswa belum diatur. Perbarui profil mahasiswa terlebih dahulu.');
        }

        $lecturer = User::query()
            ->with('dosenProfile')
            ->find($lecturerUserId);

        if (! $lecturer instanceof User || ! $lecturer->hasRole('dosen')) {
            throw new RuntimeException(sprintf('%s harus merupakan dosen yang valid.', $label));
        }

        $lecturerProfile = $lecturer->dosenProfile;

        if ($lecturerProfile === null || ! $lecturerProfile->is_active) {
            throw new RuntimeException(sprintf('%s belum memiliki profil dosen aktif.', $label));
        }

        if ($lecturerProfile->program_studi_id !== $project->program_studi_id) {
            throw new RuntimeException(sprintf('%s harus berasal dari program studi yang sama.', $label));
        }

        if ($lecturerProfile->concentration !== $studentConcentration) {
            throw new RuntimeException(sprintf(
                '%s harus memiliki konsentrasi yang sama dengan mahasiswa (%s).',
                $label,
                $studentConcentration,
            ));
        }

        $quota = max(1, (int) ($lecturerProfile->supervision_quota ?? 14));
        $activeStudentIds = $this->activeStudentIdsForLecturer($lecturerUserId);

        if ($activeStudentIds->contains($project->student_user_id)) {
            return;
        }

        if ($activeStudentIds->count() >= $quota) {
            throw new RuntimeException(sprintf('%s sudah mencapai kuota bimbingan (%d mahasiswa aktif).', $label, $quota));
        }
    }

    private function assertDefenseExaminerEligible(ThesisProject $project, int $lecturerUserId, string $label): void
    {
        $lecturer = User::query()
            ->with('dosenProfile')
            ->find($lecturerUserId);

        if (! $lecturer instanceof User || ! $lecturer->hasRole('dosen')) {
            throw new RuntimeException(sprintf('%s harus merupakan dosen yang valid.', $label));
        }

        $lecturerProfile = $lecturer->dosenProfile;

        if ($lecturerProfile === null || ! $lecturerProfile->is_active) {
            throw new RuntimeException(sprintf('%s belum memiliki profil dosen aktif.', $label));
        }

        if ($lecturerProfile->program_studi_id !== $project->program_studi_id) {
            throw new RuntimeException(sprintf('%s harus berasal dari program studi yang sama.', $label));
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function activeStudentIdsForLecturer(int $lecturerUserId)
    {
        return ThesisSupervisorAssignment::query()
            ->with('project')
            ->where('lecturer_user_id', $lecturerUserId)
            ->where('status', 'active')
            ->whereHas('project', static fn($query) => $query->where('state', 'active'))
            ->get()
            ->map(static fn(ThesisSupervisorAssignment $assignment): ?int => $assignment->project?->student_user_id)
            ->filter()
            ->unique()
            ->values();
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

        $this->notifyAdminsAboutEvent(
            project: $project,
            actorUserId: $actorUserId,
            title: $label,
            body: $description,
        );
    }

    private function notifyAdminsAboutEvent(
        ThesisProject $project,
        ?int $actorUserId,
        string $title,
        ?string $body,
    ): void {
        $project->loadMissing([
            'student',
            'programStudi',
        ]);

        $recipients = User::query()
            ->where(function ($query) use ($project): void {
                $query->whereHas('roles', function ($roleQuery): void {
                    $roleQuery->where('name', AppRole::SuperAdmin->value);
                })->orWhere(function ($adminQuery) use ($project): void {
                    $adminQuery->whereHas('roles', function ($roleQuery): void {
                        $roleQuery->where('name', AppRole::Admin->value);
                    })->whereHas('adminProfile', function ($profileQuery) use ($project): void {
                        $profileQuery->where('program_studi_id', $project->program_studi_id);
                    });
                });
            })
            ->get();

        foreach ($recipients as $recipient) {
            Notification::make()
                ->title($title)
                ->body(trim(implode(' - ', array_filter([
                    $project->student?->name,
                    $project->programStudi?->name,
                    $body,
                ]))))
                ->icon('heroicon-o-bell-alert')
                ->sendToDatabase($recipient, isEventDispatched: true);
        }
    }

    private function notifyStudentAboutDefenseSchedule(
        ThesisProject $project,
        string $type,
        string $scheduledFor,
        string $location,
        bool $wasRescheduled,
    ): void {
        $project->loadMissing('student');

        if (! $project->student instanceof User) {
            return;
        }

        $label = $type === 'sidang' ? 'sidang' : 'sempro';

        $this->realtimeNotificationService->notifyUser($project->student, 'statusTugasAkhir', [
            'title' => $wasRescheduled
                ? sprintf('Jadwal %s diperbarui', $label)
                : sprintf('%s dijadwalkan', ucfirst($label)),
            'description' => sprintf(
                $wasRescheduled
                    ? '%s Anda dijadwalkan ulang pada %s di %s.'
                    : '%s Anda dijadwalkan pada %s di %s.',
                ucfirst($label),
                $scheduledFor,
                $location,
            ),
            'url' => '/tugas-akhir',
            'icon' => 'calendar-clock',
            'createdAt' => now()->toIso8601String(),
        ]);
    }

    private function notifyLecturersAboutDefenseSchedule(
        ThesisProject $project,
        ?ThesisDefense $defense,
        string $type,
        string $scheduledFor,
        string $location,
        bool $wasRescheduled,
    ): void {
        $project->loadMissing('activeSupervisorAssignments.lecturer');
        $defense?->loadMissing('examiners.lecturer');

        $label = $type === 'sidang' ? 'sidang' : 'sempro';
        $recipients = $project->activeSupervisorAssignments
            ->map(fn(ThesisSupervisorAssignment $assignment): ?User => $assignment->lecturer)
            ->concat(
                collect($defense?->examiners ?? [])
                    ->map(fn(ThesisDefenseExaminer $examiner): ?User => $examiner->lecturer),
            )
            ->filter(fn(?User $user): bool => $user instanceof User)
            ->unique('id')
            ->values();

        foreach ($recipients as $recipient) {
            $this->realtimeNotificationService->notifyUser($recipient, 'statusTugasAkhir', [
                'title' => $wasRescheduled
                    ? sprintf('Jadwal %s mahasiswa diperbarui', $label)
                    : sprintf('%s mahasiswa dijadwalkan', ucfirst($label)),
                'description' => sprintf(
                    $wasRescheduled
                        ? '%s mahasiswa bimbingan Anda dijadwalkan ulang pada %s di %s.'
                        : '%s mahasiswa bimbingan Anda dijadwalkan pada %s di %s.',
                    ucfirst($label),
                    $scheduledFor,
                    $location,
                ),
                'url' => '/dosen/seminar-proposal',
                'icon' => 'calendar-clock',
                'createdAt' => now()->toIso8601String(),
            ]);
        }
    }

    private function notifyStudentAboutDefenseResult(
        ThesisProject $project,
        string $type,
        string $result,
        string $notes,
    ): void {
        $project->loadMissing('student');

        if (! $project->student instanceof User) {
            return;
        }

        $label = $type === 'sidang' ? 'sidang' : 'sempro';

        $title = match ($result) {
            'pass' => sprintf('Hasil %s tersedia', $label),
            'pass_with_revision' => sprintf('%s selesai dengan revisi', ucfirst($label)),
            'fail' => sprintf('%s dinyatakan tidak lulus', ucfirst($label)),
            default => sprintf('Update %s tersedia', $label),
        };

        $this->realtimeNotificationService->notifyUser($project->student, 'statusTugasAkhir', [
            'title' => $title,
            'description' => $notes,
            'url' => '/tugas-akhir',
            'icon' => 'check-circle',
            'createdAt' => now()->toIso8601String(),
        ]);
    }

    private function notifyStudentAboutSupervisorAssignment(ThesisProject $project): void
    {
        $project->loadMissing([
            'student',
            'activeSupervisorAssignments.lecturer',
        ]);

        if (! $project->student instanceof User) {
            return;
        }

        $names = $project->activeSupervisorAssignments
            ->sortBy('role')
            ->map(fn(ThesisSupervisorAssignment $assignment): ?string => $assignment->lecturer?->name)
            ->filter()
            ->values()
            ->all();

        $description = count($names) > 0
            ? 'Pembimbing Anda telah ditetapkan: '.implode(', ', $names).'.'
            : 'Pembimbing Anda telah diperbarui.';

        $this->realtimeNotificationService->notifyUser($project->student, 'statusTugasAkhir', [
            'title' => 'Pembimbing ditetapkan',
            'description' => $description,
            'url' => '/tugas-akhir',
            'icon' => 'bell',
            'createdAt' => now()->toIso8601String(),
        ]);
    }

    private function notifyStudentAboutRevisionApproval(
        ThesisProject $project,
        string $type,
        string $notes,
    ): void {
        $project->loadMissing('student');

        if (! $project->student instanceof User) {
            return;
        }

        $label = $type === 'sidang' ? 'sidang' : 'sempro';

        $this->realtimeNotificationService->notifyUser($project->student, 'statusTugasAkhir', [
            'title' => sprintf('Revisi %s disetujui', $label),
            'description' => $notes,
            'url' => '/tugas-akhir',
            'icon' => 'check-circle',
            'createdAt' => now()->toIso8601String(),
        ]);
    }
}
