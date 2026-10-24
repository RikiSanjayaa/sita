<?php

namespace App\Services;

use App\Models\MentorshipChatThread;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use Illuminate\Support\Collection;

/**
 * Chat threads of a completed thesis are read-only until the archive command deletes them.
 *
 * - sempro and sidang threads belong to the project of their defense;
 * - the bimbingan thread belongs to the student's current project;
 * - private chats are not tied to a project and are never read-only.
 */
class CompletedChatThreadService
{
    public const READ_ONLY_MESSAGE = 'Percakapan ini hanya dapat dibaca karena tugas akhir sudah selesai.';

    /**
     * Thread ids (as keys, value true) that belong to a completed project.
     *
     * @param  Collection<int, MentorshipChatThread>  $threads
     * @return array<int, true>
     */
    public function completedThreadIds(Collection $threads): array
    {
        if ($threads->isEmpty()) {
            return [];
        }

        $defenseThreads = $threads->whereIn('type', ['sempro', 'sidang'])->filter(fn (MentorshipChatThread $thread): bool => $thread->context_id !== null);
        $contextIds = $defenseThreads->pluck('context_id')->unique()->values()->all();
        $defenses = $contextIds === []
            ? collect()
            : ThesisDefense::query()
                ->with('project:id,state')
                ->where(fn ($query) => $query->whereIn('id', $contextIds)->orWhereIn('legacy_sempro_id', $contextIds))
                ->get(['id', 'project_id', 'legacy_sempro_id', 'type']);

        $studentIds = $threads->where('type', 'pembimbing')->pluck('student_user_id')->filter()->unique()->values()->all();
        $currentStates = $studentIds === []
            ? collect()
            : ThesisProject::query()
                ->current()
                ->whereIn('student_user_id', $studentIds)
                ->pluck('state', 'student_user_id');

        $completed = [];
        foreach ($threads as $thread) {
            $isCompleted = match ($thread->type) {
                'pembimbing' => $currentStates->get($thread->student_user_id) === 'completed',
                'sempro', 'sidang' => $this->defenseFor($defenses, $thread)?->project?->state === 'completed',
                default => false,
            };

            if ($isCompleted) {
                $completed[(int) $thread->getKey()] = true;
            }
        }

        return $completed;
    }

    public function isCompleted(MentorshipChatThread $thread): bool
    {
        return isset($this->completedThreadIds(new Collection([$thread]))[(int) $thread->getKey()]);
    }

    /**
     * @param  Collection<int, ThesisDefense>  $defenses
     */
    private function defenseFor(Collection $defenses, MentorshipChatThread $thread): ?ThesisDefense
    {
        $byId = $defenses->first(fn (ThesisDefense $defense): bool => (int) $defense->getKey() === (int) $thread->context_id && $defense->type === $thread->type);

        return $byId ?? $defenses->first(fn (ThesisDefense $defense): bool => $thread->type === 'sempro'
            && $defense->legacy_sempro_id !== null
            && (int) $defense->legacy_sempro_id === (int) $thread->context_id);
    }
}
