<?php

namespace App\Observers;

use App\Events\ChatUnreadUpdated;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatRead;
use App\Models\MentorshipChatThread;
use App\Services\ChatUnreadService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends the unread invalidation after a real incoming message or a receipt that actually changed.
 * Runs after the surrounding transaction commits, and never depends on notification preferences.
 */
class ChatUnreadObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly ChatUnreadService $unread,
    ) {}

    public function created(MentorshipChatMessage|MentorshipChatRead $model): void
    {
        if ($model instanceof MentorshipChatMessage) {
            // Same rule as the unread query: system events and null-sender rows never count as unread.
            if ($model->message_type === 'document_event' || $model->sender_user_id === null) {
                return;
            }

            $this->invalidate($model->thread, $model->sender_user_id);

            return;
        }

        $this->invalidate($model->thread, null, $model->user_id);
    }

    public function updated(MentorshipChatMessage|MentorshipChatRead $model): void
    {
        if ($model instanceof MentorshipChatRead && ($model->wasChanged('last_read_at') || $model->wasChanged('last_read_message_id'))) {
            $this->invalidate($model->thread, null, $model->user_id);
        }
    }

    /**
     * @param  int|null  $exceptUserId  message sender: their own unread state cannot grow
     * @param  int|null  $onlyUserId  receipt owner: every session of that user, no one else
     */
    private function invalidate(?MentorshipChatThread $thread, ?int $exceptUserId, ?int $onlyUserId = null): void
    {
        if ($thread === null) {
            return;
        }

        $userIds = $onlyUserId !== null
            ? [$onlyUserId]
            : array_values(array_diff($this->unread->affectedUserIds($thread), [(int) $exceptUserId]));

        foreach ($userIds as $userId) {
            try {
                broadcast(new ChatUnreadUpdated($userId));
            } catch (Throwable $exception) {
                Log::warning('Chat unread broadcast skipped because realtime server is unavailable.', [
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
