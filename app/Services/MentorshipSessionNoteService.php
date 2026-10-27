<?php

namespace App\Services;

use App\Models\MentorshipSchedule;
use App\Models\ThesisProject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MentorshipSessionNoteService
{
    /** @var array<int, Collection<int, ThesisProject>> */
    private array $projectsByStudent = [];

    public function canMonitor(ThesisProject $project, User $viewer): bool
    {
        return $viewer->hasRole('super_admin')
            || ($viewer->hasRole('admin') && ($viewer->adminProgramStudiId() === null
                || $viewer->adminProgramStudiId() === (int) $project->program_studi_id))
            || ($viewer->hasRole('kaprodi') && $viewer->kaprodiProgramStudiId() !== null
                && $viewer->kaprodiProgramStudiId() === (int) $project->program_studi_id);
    }

    public function canView(MentorshipSchedule $schedule, User $viewer): bool
    {
        if ($viewer->hasRole('dosen') && (int) $schedule->lecturer_user_id === (int) $viewer->id) {
            return true;
        }
        if ($viewer->hasRole('mahasiswa') && (int) $schedule->student_user_id === (int) $viewer->id) {
            return (int) $schedule->created_by_user_id !== (int) $schedule->lecturer_user_id
                || ! app(SecretSidangExaminers::class)->isSecret((int) $viewer->id, (int) $schedule->lecturer_user_id);
        }

        $project = $this->projectForSchedule($schedule);

        return $project !== null && $this->canMonitor($project, $viewer);
    }

    public function readOnlyReason(MentorshipSchedule $schedule, User $viewer): ?string
    {
        $participant = ($viewer->hasRole('mahasiswa') && (int) $schedule->student_user_id === (int) $viewer->id)
            || ($viewer->hasRole('dosen') && (int) $schedule->lecturer_user_id === (int) $viewer->id);
        if (! $participant || ! $this->canView($schedule, $viewer)) {
            return 'Catatan sesi hanya dapat diubah oleh peserta yang menulisnya.';
        }
        if (! in_array($schedule->status, ['approved', 'rescheduled', 'completed'], true)) {
            return 'Catatan hasil bimbingan tersedia setelah jadwal dikonfirmasi. Sesi ditolak atau dibatalkan hanya dapat dibaca.';
        }
        $project = $this->projectForSchedule($schedule);
        if ($project === null || $project->state !== 'active' || $project->isArchived()
            || (int) $this->projectsByStudent[(int) $schedule->student_user_id]->first()?->id !== (int) $project->id) {
            return 'Tugas akhir tidak aktif atau sudah diarsipkan. Catatan sesi hanya dapat dibaca.';
        }
        $schedule->loadMissing('student.mahasiswaProfile');
        if ($schedule->student?->mahasiswaProfile?->is_active !== true) {
            return 'Mahasiswa tidak aktif. Catatan sesi hanya dapat dibaca.';
        }
        if ($project->isMentorshipSuspended()) {
            return MentorshipTicketService::suspensionMessage($project, false);
        }
        $supervisor = $project->supervisorAssignments->contains(fn ($assignment): bool => (int) $assignment->lecturer_user_id === (int) $schedule->lecturer_user_id && $assignment->status === 'active');
        $examiner = $project->defenses->contains(fn ($defense): bool => ($defense->status === 'scheduled'
            || ($defense->status === 'completed' && $defense->result === 'pass_with_revision'))
            && $defense->examiners->contains(fn ($examiner): bool => (int) $examiner->lecturer_user_id === (int) $schedule->lecturer_user_id));
        if (! $supervisor && ! $examiner) {
            return 'Penugasan dosen sudah berakhir. Catatan sesi hanya dapat dibaca.';
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function present(MentorshipSchedule $schedule, User $viewer): array
    {
        abort_unless($this->canView($schedule, $viewer), 403);
        $reason = $this->readOnlyReason($schedule, $viewer);
        $role = (int) $schedule->student_user_id === (int) $viewer->id ? 'mahasiswa' : 'dosen';

        return [
            'entries' => $this->entries($schedule),
            'updateUrl' => $reason === null ? route($role.'.jadwal-bimbingan.notes', $schedule) : null,
            'readOnlyReason' => $reason,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function entries(MentorshipSchedule $schedule): array
    {
        $schedule->loadMissing(['student', 'lecturer']);
        $entries = [];
        foreach (['student' => 'Mahasiswa', 'lecturer' => 'Dosen'] as $side => $role) {
            if ($schedule->{$side.'_session_note'} === null) {
                continue;
            }
            $entries[] = [
                'authorId' => (int) $schedule->{$side.'_user_id'},
                'authorName' => $schedule->{$side}?->name ?? $role,
                'authorRole' => $role,
                'body' => $schedule->{$side.'_session_note'},
                'updatedAt' => $schedule->{$side.'_session_noted_at'}?->toIso8601String(),
            ];
        }

        return $entries;
    }

    /** @return array<string, mixed> */
    public function snapshot(MentorshipSchedule $schedule): array
    {
        $schedule->loadMissing(['student', 'lecturer']);

        return [
            'id' => (int) $schedule->id,
            'topic' => $schedule->topic,
            'status' => $schedule->status,
            'scheduledAt' => ($schedule->scheduled_for ?? $schedule->requested_for)?->toIso8601String(),
            'lecturer' => $schedule->lecturer?->name ?? 'Dosen',
            'student' => $schedule->student?->name ?? 'Mahasiswa',
            'studentUserId' => (int) $schedule->student_user_id,
            'lecturerUserId' => (int) $schedule->lecturer_user_id,
            'createdByUserId' => (int) $schedule->created_by_user_id,
            'sessionNotes' => [
                'entries' => $this->entries($schedule),
                'updateUrl' => null,
                'readOnlyReason' => 'Catatan sesi pada pemantauan dan arsip hanya dapat dibaca.',
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function forProject(ThesisProject $project, User $viewer): array
    {
        $monitor = $this->canMonitor($project, $viewer);
        $student = $viewer->hasRole('mahasiswa') && (int) $viewer->id === (int) $project->student_user_id;
        $lecturer = $viewer->hasRole('dosen');
        if (! $monitor && ! $student && ! $lecturer) {
            return [];
        }
        if ($project->isArchived()) {
            return collect($project->archive_summary['mentorship_session_notes'] ?? [])
                ->filter(fn (array $row): bool => (int) $row['studentUserId'] === (int) $project->student_user_id
                    && ($monitor || $student || ($lecturer && (int) $row['lecturerUserId'] === (int) $viewer->id))
                    && (! $student || $monitor
                        || (isset($row['createdByUserId']) && (int) $row['createdByUserId'] !== (int) $row['lecturerUserId'])
                        || ! app(SecretSidangExaminers::class)->isSecret((int) $viewer->id, (int) $row['lecturerUserId'])))
                ->map(function (array $row): array {
                    $row['sessionNotes']['updateUrl'] = null;
                    $row['sessionNotes']['readOnlyReason'] = 'Catatan sesi sudah diarsipkan dan hanya dapat dibaca.';

                    return $row;
                })->values()->all();
        }
        [$from, $to] = app(ThesisArchiveService::class)->window($project);

        return MentorshipSchedule::query()->where('student_user_id', $project->student_user_id)
            ->when($from, fn (Builder $query) => $query->where('created_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->where('created_at', '<', $to))
            ->when(! $monitor && ! $student, fn (Builder $query) => $query->where('lecturer_user_id', $viewer->id))
            ->with(['student', 'lecturer'])->orderByDesc('scheduled_for')->orderByDesc('id')->get()
            ->filter(fn (MentorshipSchedule $schedule): bool => $monitor || $this->canView($schedule, $viewer))
            ->map(fn (MentorshipSchedule $schedule): array => $this->snapshot($schedule))->values()->all();
    }

    private function projectForSchedule(MentorshipSchedule $schedule): ?ThesisProject
    {
        $projects = $this->projectsByStudent[(int) $schedule->student_user_id] ??= ThesisProject::query()
            ->where('student_user_id', $schedule->student_user_id)
            ->with(['supervisorAssignments', 'defenses.examiners'])
            ->orderByRaw('CASE WHEN started_at IS NULL THEN 1 ELSE 0 END')->orderByDesc('started_at')->orderByDesc('id')->get();
        if ($projects->count() === 1) {
            return $projects->first();
        }

        return $projects->first(fn (ThesisProject $project): bool => $project->started_at === null
            || ($schedule->created_at !== null && $schedule->created_at->gte($project->started_at)));
    }
}
