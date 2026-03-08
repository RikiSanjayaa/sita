<?php

namespace App\Services;

use App\Enums\AdvisorType;
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
        return DB::transaction(function () use ($project, $scheduledBy, $scheduledFor, $location, $mode, $examinerUserIds): ThesisProject {
            $defense = $project->semproDefenses()
                ->whereIn('status', ['draft', 'scheduled'])
                ->latest('attempt_no')
                ->first();

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
                eventType: 'sempro_scheduled',
                label: 'Sempro dijadwalkan',
                description: sprintf('Sempro dijadwalkan di %s.', $location),
                occurredAt: $scheduledFor,
            );

            return $project->fresh();
        });
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

        return DB::transaction(function () use ($project, $defense, $decidedBy, $result, $notes, $revisionDueAt): ThesisProject {
            $defense->forceFill([
                'status' => 'completed',
                'result' => $result,
                'decided_by' => $decidedBy,
                'decision_at' => now(),
                'notes' => $notes,
            ])->save();

            $defense->examiners->each(function (ThesisDefenseExaminer $examiner) use ($decidedBy, $result, $notes): void {
                $examiner->forceFill([
                    'decision' => $result,
                    'score' => $examiner->score,
                    'notes' => $notes,
                    'decided_at' => now(),
                    'assigned_by' => $decidedBy,
                ])->save();
            });

            if ($result === 'pass') {
                ThesisRevision::query()
                    ->where('project_id', $project->getKey())
                    ->where('defense_id', $defense->getKey())
                    ->whereIn('status', ['open', 'submitted'])
                    ->update([
                        'status' => 'resolved',
                        'resolved_at' => now(),
                        'resolved_by_user_id' => $decidedBy,
                        'resolution_notes' => $notes,
                    ]);

                $project->forceFill([
                    'phase' => 'research',
                    'state' => 'active',
                    'completed_at' => null,
                    'closed_by' => null,
                ])->save();
            }

            if ($result === 'pass_with_revision') {
                ThesisRevision::query()->updateOrCreate(
                    [
                        'project_id' => $project->getKey(),
                        'defense_id' => $defense->getKey(),
                        'status' => 'open',
                    ],
                    [
                        'requested_by_user_id' => $decidedBy,
                        'notes' => $notes,
                        'due_at' => $revisionDueAt,
                        'submitted_at' => null,
                        'resolved_at' => null,
                        'resolved_by_user_id' => null,
                        'resolution_notes' => null,
                    ],
                );

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
                eventType: $result === 'pass' ? 'sempro_completed' : 'revision_opened',
                label: $result === 'pass' ? 'Sempro selesai' : 'Revisi sempro dibuka',
                description: $notes,
                occurredAt: now()->toDateTimeString(),
            );

            return $freshProject;
        });
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

        return DB::transaction(function () use ($project, $assignedBy, $primaryLecturerUserId, $secondaryLecturerUserId, $notes): ThesisProject {
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
    }
}
