<?php

namespace App\Services;

use App\Models\MentorshipSchedule;
use App\Models\ThesisProject;
use App\Models\ThesisSupervisorAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

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
        return $this->forProjects(new EloquentCollection([$project]))[(int) $project->getKey()] ?? [];
    }

    /**
     * forProject() for many projects, with one completed-count query for all of them.
     *
     * @param  EloquentCollection<int, ThesisProject>  $projects
     * @return array<int, list<array{lecturer: array{id: int, name: string, role: string}, completed: int, minimum: int, met: bool}>>
     */
    public function forProjects(EloquentCollection $projects): array
    {
        $projects->loadMissing('activeSupervisorAssignments.lecturer');
        $minimum = $this->minimum();

        $pairs = [];
        foreach ($projects as $project) {
            foreach ($project->activeSupervisorAssignments as $assignment) {
                $pairs[] = [
                    'key' => $project->getKey().':'.$assignment->lecturer_user_id,
                    'student_user_id' => (int) $project->student_user_id,
                    'lecturer_user_id' => (int) $assignment->lecturer_user_id,
                    'started_at' => $assignment->started_at,
                ];
            }
        }
        $counts = $this->completedCountsForPairs($pairs);

        $result = [];
        foreach ($projects as $project) {
            $result[(int) $project->getKey()] = $project->activeSupervisorAssignments
                ->sortBy('role')
                ->map(function (ThesisSupervisorAssignment $assignment) use ($project, $minimum, $counts): array {
                    $completed = $counts[$project->getKey().':'.$assignment->lecturer_user_id] ?? 0;

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

        return $result;
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

    /**
     * Completed mentorship sessions for one lecturer's supervised students, in a
     * single query. Uses the same rule as forProject(): sessions since the start
     * of the supervisor assignment.
     *
     * @param  Collection<int, ThesisSupervisorAssignment>  $assignments  assignments of one lecturer
     * @return array<int, int> completed count keyed by student user id
     */
    public function completedByStudentForLecturer(int $lecturerId, Collection $assignments): array
    {
        $assignments = $assignments
            ->filter(static fn(ThesisSupervisorAssignment $assignment): bool => $assignment->project !== null)
            ->unique(static fn(ThesisSupervisorAssignment $assignment): int => (int) $assignment->project->student_user_id);

        if ($assignments->isEmpty()) {
            return [];
        }

        $counts = MentorshipSchedule::query()
            ->selectRaw('student_user_id, count(*) as completed_count')
            ->where('lecturer_user_id', $lecturerId)
            ->where('status', 'completed')
            ->where(function (Builder $query) use ($assignments): void {
                foreach ($assignments as $assignment) {
                    $query->orWhere(function (Builder $inner) use ($assignment): void {
                        $inner->where('student_user_id', $assignment->project->student_user_id)
                            ->when($assignment->started_at, static function (Builder $query, $startedAt): void {
                                $query->whereRaw('coalesce(scheduled_for, requested_for) >= ?', [$startedAt]);
                            });
                    });
                }
            })
            ->groupBy('student_user_id')
            ->pluck('completed_count', 'student_user_id')
            ->map(static fn($count): int => (int) $count)
            ->all();

        $result = [];
        foreach ($assignments as $assignment) {
            $studentId = (int) $assignment->project->student_user_id;
            $result[$studentId] = $counts[$studentId] ?? 0;
        }

        return $result;
    }

    /**
     * Completed sessions for many student and supervisor pairs in one query, using the
     * same rule as forProject(): sessions since the start of each assignment. Pairs that
     * share a student and lecturer receive the same count.
     *
     * @param  list<array{key: string, student_user_id: int, lecturer_user_id: int, started_at: ?CarbonInterface}>  $pairs
     * @return array<string, int> completed count keyed by the pair key
     */
    public function completedCountsForPairs(array $pairs): array
    {
        if ($pairs === []) {
            return [];
        }

        $counts = MentorshipSchedule::query()
            ->selectRaw('student_user_id, lecturer_user_id, count(*) as completed_count')
            ->where('status', 'completed')
            ->where(function (Builder $query) use ($pairs): void {
                foreach ($pairs as $pair) {
                    $query->orWhere(function (Builder $inner) use ($pair): void {
                        $inner->where('student_user_id', $pair['student_user_id'])
                            ->where('lecturer_user_id', $pair['lecturer_user_id'])
                            ->when($pair['started_at'], static function (Builder $query, $startedAt): void {
                                $query->whereRaw('coalesce(scheduled_for, requested_for) >= ?', [$startedAt]);
                            });
                    });
                }
            })
            ->groupBy('student_user_id', 'lecturer_user_id')
            ->get()
            ->mapWithKeys(static fn($row): array => [
                "{$row->student_user_id}:{$row->lecturer_user_id}" => (int) $row->completed_count,
            ]);

        $result = [];
        foreach ($pairs as $pair) {
            $result[$pair['key']] = $counts["{$pair['student_user_id']}:{$pair['lecturer_user_id']}"] ?? 0;
        }

        return $result;
    }
}
