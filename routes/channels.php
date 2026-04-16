<?php

use App\Models\MentorshipChatThread;
use App\Services\MentorshipAccessService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('mentorship.thread.{threadId}', function ($user, int $threadId): bool {
    $thread = MentorshipChatThread::query()->find($threadId);
    if ($thread === null) {
        return false;
    }

    return app(MentorshipAccessService::class)->canAccessThread($user, $thread);
});

Broadcast::channel('schedule.user.{userId}', function ($user, int $userId): bool {
    return (int) $user->id === $userId;
});
