<?php

namespace App\Services;

use App\Models\MentorshipChatThread;
use App\Models\MentorshipChatThreadParticipant;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

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

        if ($thread->type === 'pembimbing') {
            return ThesisSupervisorAssignment::query()
                ->where('lecturer_user_id', $user->id)
                ->whereIn('status', ['active', 'ended'])
                ->whereHas('project', fn(Builder $query) => $query->where('student_user_id', $thread->student_user_id))
                ->exists();
        }

        return MentorshipChatThreadParticipant::query()
            ->where('thread_id', $thread->id)
            ->where('user_id', $user->id)
            ->exists();
    }
}
