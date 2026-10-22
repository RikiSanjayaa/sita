<?php

namespace App\Services;

use App\Models\MentorshipSchedule;
use App\Models\ThesisProject;
use App\Models\ThesisSupervisorAssignment;
use Illuminate\Database\Eloquent\Builder;

class MentorshipProgressService
{
    public function minimum(): int
    {
        return (int) config('sita.minimum_mentorship_sessions', 6);
    }

    /**
     * Completed mentorship sessions per active supervisor, counted since the
     * start of that supervisor's assignment.
     *
     * @return list<array{lecturer: array{id: int, name: string, role: string}, completed: int, minimum: int, met: bool}>
     */
    public function forProject(ThesisProject $project): array
    {
        $minimum = $this->minimum();
        $assignments = $project->relationLoaded('activeSupervisorAssignments')
            ? $project->activeSupervisorAssignments
            : $project->activeSupervisorAssignments()->get();
        $assignments->loadMissing('lecturer');

        return $assignments
            ->sortBy('role')
            ->map(function (ThesisSupervisorAssignment $assignment) use ($project, $minimum): array {
                $completed = $this->completedCount($project, $assignment);

                return [
                    'lecturer' => [
                        'id' => (int) $assignment->lecturer_user_id,
                        'name' => $assignment->lecturer?->name ?? '-',
                        'role' => $assignment->role === 'primary' ? 'Pembimbing 1' : 'Pembimbing 2',
                    ],
                    'completed' => $completed,
                    'minimum' => $minimum,
                    'met' => $completed >= $minimum,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array{lecturer: array{id: int, name: string, role: string}, completed: int, minimum: int, met: bool}>  $progress
     * @return list<array{lecturer: array{id: int, name: string, role: string}, completed: int, minimum: int, met: bool}>
     */
    public function unmet(array $progress): array
    {
        return array_values(array_filter($progress, static fn(array $row): bool => ! $row['met']));
    }

    /**
     * Event/audit payload fragment recorded when a defense is scheduled.
     *
     * @return array{mentorship_minimum_unmet: bool, mentorship_progress: list<array{lecturer_user_id: int, completed: int, minimum: int}>}
     */
    public function auditPayload(ThesisProject $project): array
    {
        $progress = $this->forProject($project);

        return [
            'mentorship_minimum_unmet' => $this->unmet($progress) !== [],
            'mentorship_progress' => array_map(static fn(array $row): array => [
                'lecturer_user_id' => $row['lecturer']['id'],
                'completed' => $row['completed'],
                'minimum' => $row['minimum'],
            ], $progress),
        ];
    }

    private function completedCount(ThesisProject $project, ThesisSupervisorAssignment $assignment): int
    {
        return MentorshipSchedule::query()
            ->where('student_user_id', $project->student_user_id)
            ->where('lecturer_user_id', $assignment->lecturer_user_id)
            ->where('status', 'completed')
            ->when($assignment->started_at, static function (Builder $query, $startedAt): void {
                $query->whereRaw('coalesce(scheduled_for, requested_for) >= ?', [$startedAt]);
            })
            ->count();
    }
}
