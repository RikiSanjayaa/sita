<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Models\MentorshipAssignment;
use App\Models\MentorshipChatThread;
use App\Models\User;

class MentorshipAccessService
{
    public function canAccessThread(User $user, MentorshipChatThread $thread): bool
    {
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
    }
}
