<?php

namespace App\Services;

use App\Enums\AppRole;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatRead;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipChatThreadParticipant;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for the sidebar "Pesan" badge: how many chat messages, in threads
 * visible in the active role's Pesan page, arrived after the user's own read receipt?
 *
 * Visibility mirrors the Pesan pages (Mahasiswa\PesanController, Dosen\PesanBimbinganController);
 * the unread rule mirrors their per-thread counters (other sender, no document_event, newer
 * than the receipt's id watermark, or last_read_at for legacy receipts). The count is exact and
 * counts messages, not threads. Read-only: never creates threads or receipts.
 */
class ChatUnreadService
{
    public function __construct(
        private readonly DosenBimbinganService $dosenBimbinganService,
    ) {}

    public function unreadMessageCount(User $user, ?string $activeRole): int
    {
        $threads = $this->visibleThreads($user, $activeRole);

        if ($threads === null) {
            return 0;
        }

        return MentorshipChatMessage::query()
            ->whereIn('mentorship_chat_messages.mentorship_chat_thread_id', $threads->select('mentorship_chat_threads.id'))
            ->unreadFor($user->id)
            ->count();
    }

    /**
     * Saves the receipt up to the newest message the reader actually saw (id watermark plus timestamp).
     * Never moves backwards, so a stale tab showing an older window cannot un-read newer messages.
     */
    public function markRead(User $user, MentorshipChatThread $thread, int $lastMessageId): void
    {
        $message = MentorshipChatMessage::query()
            ->whereKey($lastMessageId)
            ->where('mentorship_chat_thread_id', $thread->id)
            ->first();

        abort_if($message === null, 422, 'Pesan tidak ditemukan di percakapan ini.');

        for ($attempt = 0; ; $attempt++) {
            try {
                DB::transaction(function () use ($user, $thread, $message): void {
                    $receipt = MentorshipChatRead::query()
                        ->where('mentorship_chat_thread_id', $thread->id)
                        ->where('user_id', $user->id)
                        ->lockForUpdate()
                        ->first() ?? new MentorshipChatRead([
                            'mentorship_chat_thread_id' => $thread->id,
                            'user_id' => $user->id,
                        ]);

                    $receipt->last_read_message_id = max((int) $receipt->last_read_message_id, $message->id);

                    if ($receipt->last_read_at === null || $receipt->last_read_at->lessThan($message->created_at)) {
                        $receipt->last_read_at = $message->created_at;
                    }

                    $receipt->save();
                });

                return;
            } catch (UniqueConstraintViolationException $exception) {
                // A concurrent first receipt won the race; retry once against the row it created.
                if ($attempt >= 1) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * Whether the thread appears in the Pesan page of the given active role.
     */
    public function canSeeThread(User $user, ?string $activeRole, MentorshipChatThread $thread): bool
    {
        $threads = $this->visibleThreads($user, $activeRole);

        return $threads !== null && $threads->whereKey($thread->getKey())->exists();
    }

    /**
     * Users whose unread state may change when a message lands in, or a receipt is saved for, the thread.
     * Deliberately generous: the event is an identity-free invalidation, the client re-fetches its own count.
     *
     * @return array<int, int>
     */
    public function affectedUserIds(MentorshipChatThread $thread): array
    {
        $participantIds = MentorshipChatThreadParticipant::query()
            ->where('thread_id', $thread->id)
            ->pluck('user_id');

        if ($thread->type === 'private') {
            return $participantIds->map(static fn ($id): int => (int) $id)->unique()->values()->all();
        }

        $lecturerIds = $thread->type === 'pembimbing' && $thread->student_user_id !== null
            ? ThesisSupervisorAssignment::query()
                ->whereIn('status', ['active', 'ended'])
                ->whereHas('project', fn (Builder $project) => $project->where('student_user_id', $thread->student_user_id))
                ->pluck('lecturer_user_id')
            : collect();

        return $participantIds
            ->merge($lecturerIds)
            ->when($thread->student_user_id !== null, fn ($ids) => $ids->push($thread->student_user_id))
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Builder<MentorshipChatThread>|null
     */
    private function visibleThreads(User $user, ?string $activeRole): ?Builder
    {
        if ($activeRole === AppRole::Mahasiswa->value) {
            return $this->studentThreads($user);
        }

        if ($activeRole === AppRole::Dosen->value || $activeRole === AppRole::Kaprodi->value) {
            return $this->lecturerThreads($user);
        }

        return null;
    }

    /**
     * @return Builder<MentorshipChatThread>
     */
    private function studentThreads(User $student): Builder
    {
        return MentorshipChatThread::query()->where(function (Builder $query) use ($student): void {
            $query->where(fn (Builder $own) => $own
                ->where('type', 'pembimbing')
                ->where('student_user_id', $student->id))
                ->orWhereIn('id', MentorshipChatThreadParticipant::query()
                    ->select('thread_id')
                    ->where('user_id', $student->id)
                    ->where('role', 'student'))
                ->orWhere(fn (Builder $private) => $private
                    ->where('type', 'private')
                    ->whereIn('id', MentorshipChatThreadParticipant::query()
                        ->select('thread_id')
                        ->where('user_id', $student->id)));
        });
    }

    /**
     * @return Builder<MentorshipChatThread>
     */
    private function lecturerThreads(User $lecturer): Builder
    {
        $studentIds = array_values(array_unique(array_merge(
            $this->dosenBimbinganService->activeStudentIds($lecturer),
            $this->dosenBimbinganService->archivedStudentIds($lecturer),
        )));

        return MentorshipChatThread::query()->where(function (Builder $query) use ($lecturer, $studentIds): void {
            $query->where(fn (Builder $supervised) => $supervised
                ->where('type', 'pembimbing')
                ->whereIn('student_user_id', $studentIds))
                ->orWhereIn('id', MentorshipChatThreadParticipant::query()
                    ->select('thread_id')
                    ->where('user_id', $lecturer->id)
                    ->where('role', 'examiner'))
                ->orWhere(fn (Builder $private) => $private
                    ->where('type', 'private')
                    ->whereIn('id', MentorshipChatThreadParticipant::query()
                        ->select('thread_id')
                        ->where('user_id', $lecturer->id)));
        });
    }
}
