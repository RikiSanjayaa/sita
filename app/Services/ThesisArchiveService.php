<?php

namespace App\Services;

use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatRead;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipChatThreadParticipant;
use App\Models\MentorshipDocument;
use App\Models\MentorshipSchedule;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisSubmission;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Archives a completed thesis once its grace period has passed: keeps the project, titles,
 * supervisors, examiners, results, revisions, events and the latest final manuscript, and
 * permanently deletes the mentorship chat, schedules, draft documents and their files.
 *
 * Mentorship rows belong to a student, not to a project. When a student has other
 * projects, only rows inside this project's time window are touched (see window()), and a
 * private chat with another student is never deleted.
 */
class ThesisArchiveService
{
    public const EVENT_TYPE = 'project_archived';

    public function __construct(
        private readonly MentorshipProgressService $mentorshipProgressService,
        private readonly StudentActivityService $studentActivityService,
    ) {}

    /**
     * Completed projects past the grace period that are not archived yet.
     *
     * @return EloquentCollection<int, ThesisProject>
     */
    public function dueProjects(): EloquentCollection
    {
        return ThesisProject::query()
            ->dueForArchive()
            ->with(['student.mahasiswaProfile'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Whether the student's current thesis is archived, so its mentorship data is gone.
     */
    public function studentHasArchivedProject(User $student): bool
    {
        return ThesisProject::query()
            ->current()
            ->where('student_user_id', $student->getKey())
            ->whereNotNull('archived_at')
            ->exists();
    }

    /**
     * What archiving the project would delete. Reads only.
     */
    public function plan(ThesisProject $project): ThesisArchivePlan
    {
        $project->loadMissing(['defenses', 'supervisorAssignments.lecturer']);
        [$from, $to] = $this->window($project);
        $studentId = (int) $project->student_user_id;

        $defenseThreadIds = $this->defenseThreadIds($project);
        $sharedThreadIds = $this->sharedThreadIds($studentId);

        $defenseMessageIds = $defenseThreadIds === []
            ? []
            : MentorshipChatMessage::query()
                ->whereIn('mentorship_chat_thread_id', $defenseThreadIds)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

        $sharedMessageIds = [];
        $sharedThreadsToDelete = [];
        if ($sharedThreadIds !== []) {
            $sharedMessages = MentorshipChatMessage::query()
                ->whereIn('mentorship_chat_thread_id', $sharedThreadIds)
                ->tap(fn ($query) => $this->applyWindow($query, 'coalesce(sent_at, created_at)', $from, $to))
                ->get(['id', 'mentorship_chat_thread_id']);
            $sharedMessageIds = $sharedMessages->pluck('id')->map(static fn ($id): int => (int) $id)->all();

            $threadsWithRemaining = MentorshipChatMessage::query()
                ->whereIn('mentorship_chat_thread_id', $sharedThreadIds)
                ->whereIntegerNotInRaw('id', $sharedMessageIds)
                ->distinct()
                ->pluck('mentorship_chat_thread_id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
            $sharedThreadsToDelete = array_values(array_diff($sharedThreadIds, $threadsWithRemaining));
        }

        $threadIds = array_values(array_unique([...$defenseThreadIds, ...$sharedThreadsToDelete]));
        $messageIds = array_values(array_unique([...$defenseMessageIds, ...$sharedMessageIds]));
        if ($sharedThreadsToDelete !== []) {
            $messageIds = array_values(array_unique([
                ...$messageIds,
                ...MentorshipChatMessage::query()
                    ->whereIn('mentorship_chat_thread_id', $sharedThreadsToDelete)
                    ->pluck('id')
                    ->map(static fn ($id): int => (int) $id)
                    ->all(),
            ]));
        }
        sort($threadIds);
        sort($messageIds);

        $schedules = MentorshipSchedule::query()
            ->where('student_user_id', $studentId)
            ->tap(fn ($query) => $this->applyWindow($query, 'created_at', $from, $to))
            ->get(['id', 'status']);
        $mentorshipDocuments = MentorshipDocument::query()
            ->where('student_user_id', $studentId)
            ->tap(fn ($query) => $this->applyWindow($query, 'created_at', $from, $to))
            ->get(['id', 'storage_disk', 'storage_path']);

        $keptFinal = ThesisDocument::query()
            ->where('project_id', $project->getKey())
            ->where('kind', 'final_manuscript')
            ->orderByDesc('version_no')
            ->orderByDesc('id')
            ->first(['id']);
        $thesisDocuments = ThesisDocument::query()
            ->where('project_id', $project->getKey())
            ->when($keptFinal, fn ($query) => $query->whereKeyNot($keptFinal->getKey()))
            ->get(['id', 'storage_disk', 'storage_path']);

        $scheduleIds = $schedules->pluck('id')->map(static fn ($id): int => (int) $id)->sort()->values()->all();
        $mentorshipDocumentIds = $mentorshipDocuments->pluck('id')->map(static fn ($id): int => (int) $id)->sort()->values()->all();
        $thesisDocumentIds = $thesisDocuments->pluck('id')->map(static fn ($id): int => (int) $id)->sort()->values()->all();

        [$filesToDelete, $filesKept] = $this->files(
            mentorshipDocuments: $mentorshipDocuments,
            thesisDocuments: $thesisDocuments,
            messageIds: $messageIds,
            mentorshipDocumentIds: $mentorshipDocumentIds,
            thesisDocumentIds: $thesisDocumentIds,
        );

        return new ThesisArchivePlan(
            project: $project,
            windowFrom: $from,
            windowTo: $to,
            threadIds: $threadIds,
            messageIds: $messageIds,
            scheduleIds: $scheduleIds,
            mentorshipDocumentIds: $mentorshipDocumentIds,
            thesisDocumentIds: $thesisDocumentIds,
            keptFinalDocumentId: $keptFinal === null ? null : (int) $keptFinal->getKey(),
            filesToDelete: $filesToDelete,
            filesKept: $filesKept,
            summary: $this->summary($project, $schedules, $mentorshipDocuments, count($messageIds)),
            threadDetail: [
                'threads' => count($threadIds),
                'read_markers' => $threadIds === [] ? 0 : MentorshipChatRead::query()->whereIn('mentorship_chat_thread_id', $threadIds)->count(),
                'participants' => $threadIds === [] ? 0 : MentorshipChatThreadParticipant::query()->whereIn('thread_id', $threadIds)->count(),
            ],
        );
    }

    /**
     * Archives one project in its own transaction. Returns the executed plan, or null when
     * the project is no longer eligible (already archived, not completed, still in grace),
     * which makes repeated runs harmless.
     */
    public function archive(ThesisProject $project): ?ThesisArchivePlan
    {
        $plan = DB::transaction(function () use ($project): ?ThesisArchivePlan {
            $fresh = ThesisProject::query()->lockForUpdate()->find($project->getKey());

            if (! $fresh instanceof ThesisProject
                || ! ThesisProject::query()->dueForArchive()->whereKey($fresh->getKey())->exists()) {
                return null;
            }

            $plan = $this->plan($fresh);
            $archivedAt = now();

            // The snapshot is saved in the same transaction, before any row is deleted.
            $fresh->forceFill([
                'archive_summary' => $plan->summary,
                'archived_at' => $archivedAt,
            ])->save();

            $this->deleteRows($plan);

            ThesisProjectEvent::query()->create([
                'project_id' => $fresh->getKey(),
                'actor_user_id' => null,
                'event_type' => self::EVENT_TYPE,
                'label' => 'Data bimbingan diarsipkan',
                'description' => 'Data bimbingan dihapus setelah masa tenggang. Data inti dan naskah akhir tetap tersimpan.',
                'payload' => [
                    'deleted' => $plan->counts(),
                    'summary' => $plan->summary,
                ],
                'occurred_at' => $archivedAt,
            ]);

            return $plan;
        });

        if ($plan instanceof ThesisArchivePlan) {
            $this->deleteFiles($plan);
        }

        return $plan;
    }

    private function deleteRows(ThesisArchivePlan $plan): void
    {
        foreach (array_chunk($plan->messageIds, 500) as $ids) {
            MentorshipChatMessage::query()->whereIntegerInRaw('id', $ids)->delete();
        }

        if ($plan->threadIds !== []) {
            MentorshipChatRead::query()->whereIn('mentorship_chat_thread_id', $plan->threadIds)->delete();
            MentorshipChatThreadParticipant::query()->whereIn('thread_id', $plan->threadIds)->delete();
            MentorshipChatThread::query()->whereKey($plan->threadIds)->delete();
        }

        foreach (array_chunk($plan->scheduleIds, 500) as $ids) {
            MentorshipSchedule::query()->whereIntegerInRaw('id', $ids)->delete();
        }

        foreach (array_chunk($plan->mentorshipDocumentIds, 500) as $ids) {
            MentorshipDocument::query()->whereIntegerInRaw('id', $ids)->delete();
        }

        foreach (array_chunk($plan->thesisDocumentIds, 500) as $ids) {
            ThesisDocument::query()->whereIntegerInRaw('id', $ids)->delete();
        }
    }

    /**
     * Files go after the commit so a rolled-back transaction never loses a file. A failed
     * delete leaves an orphan file, which is logged; the rows are already gone.
     */
    private function deleteFiles(ThesisArchivePlan $plan): void
    {
        foreach ($plan->filesToDelete as $file) {
            try {
                Storage::disk($file['disk'])->delete($file['path']);
            } catch (Throwable $exception) {
                Log::warning('Archive could not delete a file.', [
                    'project_id' => $plan->project->getKey(),
                    'disk' => $file['disk'],
                    'path' => $file['path'],
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * Time window of this project's rows inside student-level tables. Unbounded when the
     * student has no other project; otherwise from this project's start up to the start of
     * the student's next project.
     *
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function window(ThesisProject $project): array
    {
        $others = ThesisProject::query()
            ->where('student_user_id', $project->student_user_id)
            ->whereKeyNot($project->getKey())
            ->get(['id', 'started_at']);

        if ($others->isEmpty()) {
            return [null, null];
        }

        $start = $project->started_at;
        $next = $others
            ->pluck('started_at')
            ->filter(static fn ($startedAt): bool => $startedAt !== null && ($start === null || $startedAt->gt($start)))
            ->min();

        return [
            $start === null ? null : CarbonImmutable::instance($start),
            $next === null ? null : CarbonImmutable::instance($next),
        ];
    }

    private function applyWindow(mixed $query, string $column, ?CarbonImmutable $from, ?CarbonImmutable $to): void
    {
        if ($from !== null) {
            $query->whereRaw("{$column} >= ?", [$from]);
        }

        if ($to !== null) {
            $query->whereRaw("{$column} < ?", [$to]);
        }
    }

    /**
     * Sempro and sidang threads of this project's defenses: owned by the project, deleted whole.
     *
     * @return list<int>
     */
    private function defenseThreadIds(ThesisProject $project): array
    {
        $studentId = (int) $project->student_user_id;
        $semproContexts = [];
        $sidangContexts = [];

        foreach ($project->defenses as $defense) {
            if ($defense->type === 'sidang') {
                $sidangContexts[] = (int) $defense->getKey();

                continue;
            }

            $semproContexts[] = (int) $defense->getKey();
            if ($defense->legacy_sempro_id !== null) {
                $semproContexts[] = (int) $defense->legacy_sempro_id;
            }
        }

        $ids = MentorshipChatThread::query()
            ->where('student_user_id', $studentId)
            ->where(function ($query) use ($semproContexts, $sidangContexts): void {
                $query->where(fn ($inner) => $inner->where('type', 'sempro')->whereIn('context_id', $semproContexts))
                    ->orWhere(fn ($inner) => $inner->where('type', 'sidang')->whereIn('context_id', $sidangContexts));
            })
            ->pluck('id');

        return $ids->map(static fn ($id): int => (int) $id)->sort()->values()->all();
    }

    /**
     * The student's own bimbingan thread and private chats with lecturers or staff. A private
     * chat that includes another student is left alone: it is not the graduate's data alone.
     *
     * @return list<int>
     */
    private function sharedThreadIds(int $studentId): array
    {
        $bimbingan = MentorshipChatThread::query()
            ->where('student_user_id', $studentId)
            ->where('type', 'pembimbing')
            ->pluck('id');

        $private = MentorshipChatThread::query()
            ->where('type', 'private')
            ->whereHas('participants', fn ($query) => $query->where('user_id', $studentId))
            ->whereDoesntHave('participants', fn ($query) => $query
                ->where('role', 'student')
                ->where('user_id', '!=', $studentId))
            ->pluck('id');

        return $bimbingan->merge($private)->map(static fn ($id): int => (int) $id)->unique()->sort()->values()->all();
    }

    /**
     * Files of the deleted rows, split into those no remaining row still points at (deleted)
     * and those another row shares (kept). A path is compared regardless of disk, which can
     * only keep more files than strictly needed.
     *
     * @param  Collection<int, MentorshipDocument>  $mentorshipDocuments
     * @param  Collection<int, ThesisDocument>  $thesisDocuments
     * @param  list<int>  $messageIds
     * @param  list<int>  $mentorshipDocumentIds
     * @param  list<int>  $thesisDocumentIds
     * @return array{0: list<array{disk: string, path: string}>, 1: list<array{disk: string, path: string}>}
     */
    private function files(Collection $mentorshipDocuments, Collection $thesisDocuments, array $messageIds, array $mentorshipDocumentIds, array $thesisDocumentIds): array
    {
        $candidates = [];
        $add = static function (?string $disk, ?string $path) use (&$candidates): void {
            $path = trim((string) $path);
            if ($path === '') {
                return;
            }

            $disk = $disk === null || $disk === '' ? 'public' : $disk;
            $candidates[$disk."\0".$path] = ['disk' => $disk, 'path' => $path];
        };

        foreach ($mentorshipDocuments as $document) {
            $add($document->storage_disk, $document->storage_path);
        }
        foreach ($thesisDocuments as $document) {
            $add($document->storage_disk, $document->storage_path);
        }
        foreach (array_chunk($messageIds, 500) as $ids) {
            foreach (MentorshipChatMessage::query()->whereIntegerInRaw('id', $ids)->whereNotNull('attachment_path')->get(['attachment_disk', 'attachment_path']) as $message) {
                $add($message->attachment_disk, $message->attachment_path);
            }
        }

        ksort($candidates);
        $delete = [];
        $kept = [];
        foreach ($candidates as $file) {
            if ($this->isReferenced($file['path'], $messageIds, $mentorshipDocumentIds, $thesisDocumentIds)) {
                $kept[] = $file;
            } else {
                $delete[] = $file;
            }
        }

        return [$delete, $kept];
    }

    /**
     * @param  list<int>  $messageIds
     * @param  list<int>  $mentorshipDocumentIds
     * @param  list<int>  $thesisDocumentIds
     */
    private function isReferenced(string $path, array $messageIds, array $mentorshipDocumentIds, array $thesisDocumentIds): bool
    {
        return MentorshipDocument::query()->where('storage_path', $path)->whereIntegerNotInRaw('id', $mentorshipDocumentIds)->exists()
            || ThesisDocument::query()->where('storage_path', $path)->whereIntegerNotInRaw('id', $thesisDocumentIds)->exists()
            || MentorshipChatMessage::query()->where('attachment_path', $path)->whereIntegerNotInRaw('id', $messageIds)->exists()
            || ThesisSubmission::query()->where('proposal_file_path', $path)->exists();
    }

    /**
     * Snapshot that keeps progress numbers identical once the source rows are gone. It is
     * read by MentorshipProgressService and StudentActivityService.
     *
     * @param  Collection<int, MentorshipSchedule>  $schedules
     * @param  Collection<int, MentorshipDocument>  $mentorshipDocuments
     * @return array<string, mixed>
     */
    private function summary(ThesisProject $project, Collection $schedules, Collection $mentorshipDocuments, int $messageCount): array
    {
        $byLecturer = $project->supervisorAssignments->groupBy(static fn (ThesisSupervisorAssignment $assignment): int => (int) $assignment->lecturer_user_id);
        $pairs = [];
        foreach ($byLecturer as $lecturerId => $assignments) {
            $pairs[] = [
                'key' => (string) $lecturerId,
                'student_user_id' => (int) $project->student_user_id,
                'lecturer_user_id' => (int) $lecturerId,
                // Same bound the progress rule applies; one open start means no lower bound.
                'started_at' => $assignments->contains(static fn (ThesisSupervisorAssignment $assignment): bool => $assignment->started_at === null)
                    ? null
                    : $assignments->min('started_at'),
            ];
        }
        $counts = $this->mentorshipProgressService->completedCountsForPairs($pairs);

        $completed = [];
        foreach ($pairs as $pair) {
            $dates = MentorshipSchedule::query()
                ->where('student_user_id', $pair['student_user_id'])
                ->where('lecturer_user_id', $pair['lecturer_user_id'])
                ->where('status', 'completed')
                ->when($pair['started_at'], static function ($query, $startedAt): void {
                    $query->whereRaw('coalesce(scheduled_for, requested_for) >= ?', [$startedAt]);
                })
                ->selectRaw('min(coalesce(scheduled_for, requested_for)) as first_at, max(coalesce(scheduled_for, requested_for)) as last_at')
                ->first();

            $completed[] = [
                'lecturer_user_id' => $pair['lecturer_user_id'],
                'lecturer_name' => $byLecturer[$pair['lecturer_user_id']]->first()->lecturer?->name,
                'completed' => $counts[$pair['key']] ?? 0,
                'first_at' => $dates?->first_at === null ? null : CarbonImmutable::parse($dates->first_at)->toIso8601String(),
                'last_at' => $dates?->last_at === null ? null : CarbonImmutable::parse($dates->last_at)->toIso8601String(),
            ];
        }
        usort($completed, static fn (array $a, array $b): int => $a['lecturer_user_id'] <=> $b['lecturer_user_id']);

        $lastActivity = $this->studentActivityService->latestByProject(new Collection([$project]))[(int) $project->getKey()] ?? null;

        return [
            'version' => 1,
            'completed_mentorship' => $completed,
            'schedules_by_status' => $schedules->countBy('status')->sortKeys()->all(),
            'documents_uploaded' => $mentorshipDocuments
                ->unique(static fn (MentorshipDocument $document): string => $document->storage_path ?? 'id:'.$document->getKey())
                ->count(),
            'messages' => $messageCount,
            'last_activity_at' => $lastActivity === null ? null : CarbonImmutable::instance($lastActivity)->toIso8601String(),
        ];
    }
}
