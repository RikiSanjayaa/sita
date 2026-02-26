<?php

use App\Enums\AssignmentStatus;
use App\Models\MentorshipAssignment;
use App\Models\MentorshipChatThread;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('mentorship.thread.{threadId}', function ($user, int $threadId): bool {
    $thread = MentorshipChatThread::query()->find($threadId);
    if ($thread === null) {
        return false;
    }

    if ($user->hasRole('admin')) {
        return true;
    }

    if ($user->hasRole('mahasiswa')) {
        return $thread->student_user_id === $user->id;
    }

    if (! $user->hasRole('dosen')) {
        return false;
    }

    return MentorshipAssignment::query()
        ->where('student_user_id', $thread->student_user_id)
        ->where('lecturer_user_id', $user->id)
        ->where('status', AssignmentStatus::Active->value)
        ->exists();
});

Broadcast::channel('schedule.user.{userId}', function ($user, int $userId): bool {
    return (int) $user->id === $userId;
});
