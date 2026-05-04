<?php

namespace App\Services;

use App\Enums\AppRole;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisRevision;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ThesisDefenseRevisionService
{
    public function __construct(
        private readonly RealtimeNotificationService $realtimeNotificationService,
    ) {}

    public function approveByLecturer(
        User $lecturer,
        ThesisRevision $revision,
        ?string $resolutionNotes = null,
    ): ThesisProject {
        $revision->loadMissing([
            'defense.examiners',
            'project.student',
        ]);

        $defense = $revision->defense;
        $project = $revision->project;

        if (! $defense instanceof ThesisDefense || ! $project instanceof ThesisProject) {
            throw new RuntimeException('Revisi tidak terkait dengan sempro atau sidang yang valid.');
        }

        if (! in_array($revision->status, ['open', 'submitted'], true)) {
            throw new RuntimeException('Revisi ini sudah selesai.');
        }

        if ($revision->requested_by_user_id !== $lecturer->id) {
            throw new RuntimeException('Hanya dosen yang meminta revisi yang dapat menyelesaikan revisi ini.');
        }

        $isRequestingExaminer = $defense->examiners->contains(
            fn($examiner): bool => $examiner->lecturer_user_id === $lecturer->id
                && $examiner->decision === 'pass_with_revision',
        );

        if (! $isRequestingExaminer) {
            throw new RuntimeException('Anda tidak terdaftar sebagai dosen penguji yang meminta revisi untuk ujian ini.');
        }

        if ($defense->status !== 'completed' || $defense->result !== 'pass_with_revision') {
            throw new RuntimeException('Revisi belum dapat diselesaikan pada status ujian saat ini.');
        }

        $label = $defense->type === 'sidang' ? 'sidang' : 'sempro';
        $notes = filled($resolutionNotes)
            ? $resolutionNotes
            : sprintf('Revisi %s dari %s disetujui.', $label, $lecturer->name);

        $allResolved = false;

        $updatedProject = DB::transaction(function () use ($defense, $label, $lecturer, $notes, $project, $revision, &$allResolved): ThesisProject {
            $revision->forceFill([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by_user_id' => $lecturer->id,
                'resolution_notes' => $notes,
            ])->save();

            $allResolved = ! ThesisRevision::query()
                ->where('project_id', $project->id)
                ->where('defense_id', $defense->getKey())
                ->whereIn('status', ['open', 'submitted'])
                ->exists();

            if ($allResolved) {
                if ($defense->type === 'sidang') {
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
                        'closed_by' => $lecturer->id,
                    ])->save();
                } else {
                    $project->forceFill([
                        'phase' => 'research',
                        'state' => 'active',
                        'completed_at' => null,
                        'closed_by' => null,
                    ])->save();
                }
            }

            $freshProject = $project->fresh();

            $this->recordEvent(
                $freshProject,
                actorUserId: $lecturer->id,
                eventType: 'revision_resolved',
                label: $allResolved
                    ? sprintf('Revisi %s disetujui', $label)
                    : sprintf('Revisi %s %s disetujui', $label, $lecturer->name),
                description: $notes,
            );

            return $freshProject;
        });

        $this->notifyStudentAboutRevisionApproval($updatedProject, $label, $notes, $allResolved);

        if ($allResolved) {
            $this->notifyAdminsAboutRevisionCompletion($updatedProject, $label);
        }

        return $updatedProject;
    }

    private function recordEvent(
        ThesisProject $project,
        int $actorUserId,
        string $eventType,
        string $label,
        string $description,
    ): void {
        ThesisProjectEvent::query()->create([
            'project_id' => $project->id,
            'actor_user_id' => $actorUserId,
            'event_type' => $eventType,
            'label' => $label,
            'description' => $description,
            'occurred_at' => now(),
        ]);
    }

    private function notifyStudentAboutRevisionApproval(
        ThesisProject $project,
        string $label,
        string $notes,
        bool $allResolved,
    ): void {
        $project->loadMissing('student');

        if (! $project->student instanceof User) {
            return;
        }

        $title = $allResolved
            ? sprintf('Revisi %s disetujui', $label)
            : sprintf('Revisi %s diperbarui', $label);

        $description = $allResolved
            ? $notes
            : $notes.' Menunggu persetujuan revisi dari dosen lainnya.';

        $this->realtimeNotificationService->notifyUser($project->student, 'statusTugasAkhir', [
            'title' => $title,
            'description' => $description,
            'url' => '/tugas-akhir',
            'icon' => 'check-circle',
            'createdAt' => now()->toIso8601String(),
        ]);
    }

    private function notifyAdminsAboutRevisionCompletion(ThesisProject $project, string $label): void
    {
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

        $title = $label === 'sempro'
            ? 'Revisi sempro selesai'
            : 'Revisi sidang selesai';

        $body = $label === 'sempro'
            ? 'Semua revisi sempro telah disetujui dosen dan siap ditindaklanjuti admin untuk penetapan pembimbing.'
            : 'Semua revisi sidang telah disetujui dosen.';

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
}
