<?php

namespace App\Services;

use App\Enums\AppRole;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipChatThreadParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PrivateChatService
{
    public function __construct(
        private readonly UserProfilePresenter $userProfilePresenter,
    ) {}

    public function privateKeyFor(User $firstUser, User $secondUser): string
    {
        $ids = [(int) $firstUser->id, (int) $secondUser->id];
        sort($ids);

        return implode(':', $ids);
    }

    public function findOrCreateThread(User $initiator, User $recipient): MentorshipChatThread
    {
        abort_if($initiator->is($recipient), 422, 'Tidak dapat membuat chat pribadi dengan diri sendiri.');
        abort_unless($this->canUsePrivateChat($initiator), 403);
        abort_unless($this->canUsePrivateChat($recipient), 422, 'Pengguna ini belum tersedia untuk chat pribadi.');

        return DB::transaction(function () use ($initiator, $recipient): MentorshipChatThread {
            $thread = MentorshipChatThread::query()->firstOrCreate(
                [
                    'type' => 'private',
                    'private_key' => $this->privateKeyFor($initiator, $recipient),
                ],
                [
                    'student_user_id' => $initiator->hasRole(AppRole::Mahasiswa) ? $initiator->id : null,
                    'label' => 'Chat Pribadi',
                    'is_escalated' => false,
                ],
            );

            MentorshipChatThreadParticipant::query()->updateOrCreate(
                ['thread_id' => $thread->id, 'user_id' => $initiator->id],
                ['role' => $this->participantRole($initiator)],
            );

            MentorshipChatThreadParticipant::query()->updateOrCreate(
                ['thread_id' => $thread->id, 'user_id' => $recipient->id],
                ['role' => $this->participantRole($recipient)],
            );

            return $thread->refresh();
        });
    }

    /**
     * @return Collection<int, MentorshipChatThread>
     */
    public function threadsFor(User $user): Collection
    {
        $threadIds = MentorshipChatThreadParticipant::query()
            ->where('user_id', $user->id)
            ->whereHas('thread', fn($query) => $query->where('type', 'private'))
            ->pluck('thread_id');

        return MentorshipChatThread::query()
            ->with([
                'participants.user.roles',
                'participants.user.mahasiswaProfile.programStudi',
                'participants.user.dosenProfile.programStudi',
                'latestMessage.sender',
                'latestMessage.relatedDocument',
                'messages' => fn($query) => $query->with(['sender', 'relatedDocument'])->latest('created_at')->limit(30),
            ])
            ->whereIn('id', $threadIds)
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recipientOptionsFor(User $user): array
    {
        return User::query()
            ->with(['roles', 'mahasiswaProfile.programStudi', 'dosenProfile.programStudi'])
            ->whereKeyNot($user->id)
            ->whereHas('roles', fn($query) => $query->whereIn('name', [
                AppRole::Mahasiswa->value,
                AppRole::Dosen->value,
                AppRole::Kaprodi->value,
            ]))
            ->orderBy('name')
            ->get()
            ->filter(fn(User $candidate): bool => $this->canUsePrivateChat($candidate))
            ->map(function (User $candidate): array {
                $summary = $this->userProfilePresenter->summary($candidate);

                return [
                    'id' => $candidate->id,
                    'name' => $summary['name'] ?? $candidate->name,
                    'subtitle' => $summary['subtitle'] ?? implode(', ', $candidate->roleNames()),
                    'avatar' => $summary['avatar'] ?? null,
                    'profileUrl' => $summary['profileUrl'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    public function canUsePrivateChat(User $user): bool
    {
        return $user->hasAnyRole([
            AppRole::Mahasiswa->value,
            AppRole::Dosen->value,
            AppRole::Kaprodi->value,
        ]);
    }

    private function participantRole(User $user): string
    {
        if ($user->hasRole(AppRole::Mahasiswa)) {
            return 'student';
        }

        if ($user->hasRole(AppRole::Kaprodi)) {
            return 'kaprodi';
        }

        return 'lecturer';
    }
}
