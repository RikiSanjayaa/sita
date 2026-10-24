<?php

namespace App\Services;

use App\Enums\AppRole;
use App\Models\ThesisDefense;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisRevision;
use App\Models\ThesisRevisionRound;
use App\Models\User;
use App\Support\AcademicTerminology;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ThesisDefenseRevisionService
{
    public const RESPONSE_DOCUMENT_KIND = 'revision_response';

    public function __construct(
        private readonly RealtimeNotificationService $realtimeNotificationService,
        private readonly ThesisRevisionRoundService $revisionRounds,
    ) {}

    /**
     * The student answers every open/reopened item and sends the corrected
     * manuscript: the round moves to awaiting review.
     *
     * @param  array<int, array{response: string, location?: ?string}>  $responses  keyed by item id
     */
    public function submitResponses(
        User $student,
        ThesisRevision $revision,
        array $responses,
        UploadedFile $manuscript,
    ): ThesisRevisionRound {
        $revision->loadMissing(['defense.examiners', 'project', 'requestedBy']);

        $defense = $revision->defense;
        $project = $revision->project;

        if (! $defense instanceof ThesisDefense || ! $project instanceof ThesisProject) {
            throw new RuntimeException('Revisi tidak terkait dengan ujian yang valid.');
        }

        if ($project->student_user_id !== $student->id) {
            throw new RuntimeException('Revisi ini bukan milik Anda.');
        }

        $this->assertDefenseAwaitingRevision($defense);

        $round = $this->revisionRounds->currentRound($revision);

        if (! $round instanceof ThesisRevisionRound || $round->status !== ThesisRevisionRound::STATUS_AWAITING_STUDENT) {
            throw new RuntimeException('Revisi ini tidak sedang menunggu perbaikan dari Anda.');
        }

        $pendingIds = $this->revisionRounds->itemsAwaitingStudent($revision)->modelKeys();

        if (array_diff($pendingIds, array_keys($responses)) !== [] || array_diff(array_keys($responses), $pendingIds) !== []) {
            throw new RuntimeException('Tanggapi semua butir revisi sebelum mengirim perbaikan.');
        }

        $terms = AcademicTerminology::forProject($project);
        $label = AcademicTerminology::defenseLabel($terms, $defense->type, short: true);

        DB::transaction(function () use ($defense, $label, $manuscript, $project, $responses, $revision, $round, $student): void {
            $document = $this->storeDocument(
                $student,
                $defense,
                $revision,
                self::RESPONSE_DOCUMENT_KIND,
                $round->round_no,
                'Naskah perbaikan putaran '.$round->round_no,
                $manuscript,
            );

            $this->revisionRounds->submitResponses($revision, $round, $responses, $document);

            $revision->forceFill(['submitted_at' => now()])->save();

            $this->recordEvent(
                $project,
                actorUserId: $student->id,
                eventType: 'revision_submitted',
                label: sprintf('Perbaikan %s dikirim (putaran %d)', $label, $round->round_no),
                description: sprintf('Mahasiswa menanggapi %d butir revisi dan mengunggah naskah perbaikan.', count($responses)),
            );
        });

        $lecturer = $revision->requestedBy;

        if ($lecturer instanceof User) {
            $this->realtimeNotificationService->notifyUser($lecturer, 'statusTugasAkhir', [
                'title' => sprintf('Perbaikan %s menunggu verifikasi', $label),
                'description' => sprintf('%s mengirim perbaikan putaran %d. Verifikasi setiap butir revisi Anda.', $project->student?->name ?? 'Mahasiswa', $round->round_no),
                'url' => '/dosen/seminar-proposal',
                'icon' => 'file-check',
                'createdAt' => now()->toIso8601String(),
            ]);
        }

        return $round->fresh();
    }

    /**
     * The examiner verifies the answered items of their own revision. All
     * accepted closes the revision (and moves the project on once every
     * examiner's revision is closed); otherwise the next round opens.
     *
     * @param  array<int, array{decision: string, comment?: ?string}>  $verdicts  keyed by item id
     * @param  array<int, array{location?: ?string, description: string, priority?: ?string}>  $newItems
     * @param  ?UploadedFile  $annotation  optional annotated manuscript, kept as the next round's version
     */
    public function verify(
        User $lecturer,
        ThesisRevision $revision,
        array $verdicts,
        array $newItems = [],
        ?UploadedFile $annotation = null,
    ): ThesisProject {
        $revision->loadMissing([
            'defense.examiners',
            'project.student',
        ]);

        $defense = $revision->defense;
        $project = $revision->project;

        if (! $defense instanceof ThesisDefense || ! $project instanceof ThesisProject) {
            throw new RuntimeException('Revisi tidak terkait dengan '.AcademicTerminology::neutral()['proposalExamShort'].' atau '.AcademicTerminology::neutral()['finalExamShort'].' yang valid.');
        }

        if (! in_array($revision->status, ['open', 'submitted'], true)) {
            throw new RuntimeException('Revisi ini sudah selesai.');
        }

        if ($revision->requested_by_user_id !== $lecturer->id) {
            throw new RuntimeException('Hanya dosen yang meminta revisi yang dapat memverifikasi revisi ini.');
        }

        $isRequestingExaminer = $defense->examiners->contains(
            fn ($examiner): bool => $examiner->lecturer_user_id === $lecturer->id
                && $examiner->decision === 'pass_with_revision',
        );

        if (! $isRequestingExaminer) {
            throw new RuntimeException('Anda tidak terdaftar sebagai dosen penguji yang meminta revisi untuk ujian ini.');
        }

        $this->assertDefenseAwaitingRevision($defense);

        $round = $this->revisionRounds->currentRound($revision);

        if (! $round instanceof ThesisRevisionRound || $round->status !== ThesisRevisionRound::STATUS_AWAITING_REVIEW) {
            throw new RuntimeException('Mahasiswa belum mengirim perbaikan untuk diverifikasi.');
        }

        $reviewIds = $this->revisionRounds->itemsAwaitingReview($revision)->modelKeys();

        if (array_diff($reviewIds, array_keys($verdicts)) !== [] || array_diff(array_keys($verdicts), $reviewIds) !== []) {
            throw new RuntimeException('Verifikasi semua butir yang ditanggapi mahasiswa.');
        }

        $terms = AcademicTerminology::forProject($project);
        $label = AcademicTerminology::defenseLabel($terms, $defense->type, short: true);
        $notes = sprintf('Revisi %s dari %s disetujui.', $label, $lecturer->name);

        $allResolved = false;
        $nextRound = null;

        $updatedProject = DB::transaction(function () use ($annotation, $defense, $label, $lecturer, $newItems, $notes, $project, $revision, $round, $verdicts, &$allResolved, &$nextRound): ThesisProject {
            $nextRound = $this->revisionRounds->applyVerdicts($revision, $round, $verdicts, $newItems, $lecturer->id);

            if ($nextRound instanceof ThesisRevisionRound) {
                if ($annotation instanceof UploadedFile) {
                    $this->storeDocument(
                        $lecturer,
                        $defense,
                        $revision,
                        ThesisRevisionRoundService::ANNOTATION_KIND,
                        $nextRound->round_no,
                        'Naskah beranotasi '.$lecturer->name,
                        $annotation,
                    );
                }

                $this->recordEvent(
                    $project,
                    actorUserId: $lecturer->id,
                    eventType: 'revision_round_opened',
                    label: sprintf('Putaran revisi %d %s dibuka', $nextRound->round_no, $label),
                    description: sprintf('%s meminta perbaikan lanjutan.', $lecturer->name),
                );

                return $project->fresh();
            }

            $revision->forceFill([
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

        if ($nextRound instanceof ThesisRevisionRound) {
            $this->notifyStudentAboutNextRound($updatedProject, $defense, $lecturer, $label, $nextRound);

            return $updatedProject;
        }

        $this->notifyStudentAboutRevisionApproval($updatedProject, $label, $notes, $allResolved);

        if ($allResolved) {
            $this->notifyAdminsAboutRevisionCompletion($updatedProject, $defense->type);
        }

        return $updatedProject;
    }

    private function assertDefenseAwaitingRevision(ThesisDefense $defense): void
    {
        if ($defense->status !== 'completed' || $defense->result !== 'pass_with_revision') {
            throw new RuntimeException('Revisi belum dapat diproses pada status ujian saat ini.');
        }
    }

    private function storeDocument(
        User $uploader,
        ThesisDefense $defense,
        ThesisRevision $revision,
        string $kind,
        int $roundNo,
        string $title,
        UploadedFile $file,
    ): ThesisDocument {
        $path = $file->storeAs(
            sprintf('thesis/defenses/%d/%s/attempt-%d', $defense->project_id, $defense->type, $defense->attempt_no),
            sprintf('%s-%s-%s', $kind, Str::uuid()->toString(), $file->hashName()),
            'public',
        );

        return ThesisDocument::query()->create([
            'project_id' => $defense->project_id,
            'defense_id' => $defense->getKey(),
            'revision_id' => $revision->getKey(),
            'uploaded_by_user_id' => $uploader->id,
            'kind' => $kind,
            'status' => 'active',
            'version_no' => $roundNo,
            'title' => $title,
            'storage_disk' => 'public',
            'storage_path' => $path,
            'stored_file_name' => basename($path),
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/pdf',
            'file_size_kb' => (int) ceil(($file->getSize() ?: 0) / 1024),
            'uploaded_at' => now(),
        ]);
    }

    private function notifyStudentAboutNextRound(
        ThesisProject $project,
        ThesisDefense $defense,
        User $lecturer,
        string $label,
        ThesisRevisionRound $round,
    ): void {
        $project->loadMissing('student');

        if (! $project->student instanceof User) {
            return;
        }

        // The Sidang panel stays secret from the mahasiswa until the exam is completed;
        // supervisors on it are always named.
        $examiner = $defense->examiners->firstWhere('lecturer_user_id', $lecturer->id);
        $name = $defense->examinersVisibleToStudent() || $examiner?->isSupervisorRole()
            ? $lecturer->name
            : 'Penguji '.AcademicTerminology::forProject($project)['finalExamShort'];

        $this->realtimeNotificationService->notifyUser($project->student, 'statusTugasAkhir', [
            'title' => sprintf('Putaran revisi %d %s dibuka', $round->round_no, $label),
            'description' => sprintf('%s belum menerima semua perbaikan. Tanggapi butir yang dibuka kembali atau ditambahkan.', $name),
            'url' => '/tugas-akhir',
            'icon' => 'file-warning',
            'createdAt' => now()->toIso8601String(),
        ]);
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

    private function notifyAdminsAboutRevisionCompletion(ThesisProject $project, string $defenseType): void
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

        $terms = AcademicTerminology::forProject($project);

        $title = $defenseType === 'sidang'
            ? 'Revisi '.$terms['finalExamShort'].' selesai'
            : 'Revisi '.$terms['proposalExamShort'].' selesai';
        $body = $defenseType === 'sidang'
            ? 'Semua revisi '.$terms['finalExamShort'].' telah disetujui dosen.'
            : 'Semua revisi '.$terms['proposalExamShort'].' telah disetujui dosen dan siap ditindaklanjuti admin untuk penetapan pembimbing.';

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
