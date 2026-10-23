<?php

namespace App\Services;

use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisSupervisorAssignment;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StudentActivityService
{
    /**
     * The one "last activity" value for a student's project, keyed by project id: the
     * newest of every supervisor and non-cancelled examiner relation on that project
     * (see latest()). Same value on the dosen and kaprodi lists, profile cards and
     * Progres Mahasiswa. Constant query count however many projects are passed.
     *
     * @param  Collection<int, ThesisProject>  $projects
     * @return array<int, CarbonInterface>
     */
    public function latestByProject(Collection $projects): array
    {
        $projectIds = $projects->map(fn (ThesisProject $project): int => (int) $project->getKey())->unique()->values()->all();
        if ($projectIds === []) {
            return [];
        }

        $assignments = ThesisSupervisorAssignment::query()
            ->whereIntegerInRaw('project_id', $projectIds)
            ->get(['id', 'project_id']);
        $examiners = ThesisDefenseExaminer::query()
            ->with('defense:id,project_id,updated_at')
            ->whereHas('defense', fn ($query) => $query
                ->whereIntegerInRaw('project_id', $projectIds)
                ->where('status', '!=', 'cancelled'))
            ->get();
        $latest = $this->latest($assignments, $examiners);

        $byProject = [];
        $consider = function (int $projectId, ?CarbonInterface $time) use (&$byProject): void {
            if ($time !== null && (! isset($byProject[$projectId]) || $time->gt($byProject[$projectId]))) {
                $byProject[$projectId] = $time;
            }
        };
        foreach ($projects as $project) {
            // Archived: chat, documents and schedules are deleted, so the snapshot is the value.
            $consider((int) $project->getKey(), $project->archivedLastActivity());
        }
        foreach ($assignments as $assignment) {
            $consider((int) $assignment->project_id, $latest['supervisor:'.$assignment->id] ?? null);
        }
        foreach ($examiners as $examiner) {
            $consider((int) $examiner->defense?->project_id, $latest['examiner:'.$examiner->id] ?? null);
        }

        return $byProject;
    }

    /**
     * Legacy mentorship records have no thesis project foreign key. Bound them
     * to the project/assignment lifetime instead of mixing a student's attempts.
     *
     * @param  Collection<int, ThesisSupervisorAssignment>  $assignments
     * @param  Collection<int, ThesisDefenseExaminer>  $examiners
     * @return array<string, Carbon>
     */
    public function latest(Collection $assignments, Collection $examiners): array
    {
        if ($assignments->isEmpty() && $examiners->isEmpty()) {
            return [];
        }

        $supervisorContexts = DB::table('thesis_supervisor_assignments as assignments')
            ->join('thesis_projects as projects', 'projects.id', '=', 'assignments.project_id')
            ->whereIntegerInRaw('assignments.id', $assignments->pluck('id')->all())
            ->selectRaw("assignments.id as activity_key, 'supervisor' as relation_type, projects.student_user_id as student_id, assignments.lecturer_user_id as lecturer_id")
            ->selectRaw('CASE WHEN projects.started_at IS NULL THEN assignments.started_at WHEN assignments.started_at IS NULL THEN projects.started_at WHEN projects.started_at > assignments.started_at THEN projects.started_at ELSE assignments.started_at END as starts_at')
            ->selectRaw("assignments.ended_at as ends_at, 'pembimbing' as thread_type, NULL as defense_id");
        $examinerContexts = DB::table('thesis_defense_examiners as examiners')
            ->join('thesis_defenses as defenses', 'defenses.id', '=', 'examiners.defense_id')
            ->join('thesis_projects as projects', 'projects.id', '=', 'defenses.project_id')
            ->whereIntegerInRaw('examiners.id', $examiners->pluck('id')->all())
            ->selectRaw("examiners.id as activity_key, 'examiner' as relation_type, projects.student_user_id as student_id, examiners.lecturer_user_id as lecturer_id")
            ->selectRaw('projects.started_at as starts_at, projects.completed_at as ends_at, defenses.type as thread_type, defenses.id as defense_id');
        $contexts = $supervisorContexts->unionAll($examinerContexts);

        $documents = DB::query()->fromSub(clone $contexts, 'contexts')
            ->join('mentorship_documents as activity', function ($join): void {
                $join->on('activity.student_user_id', '=', 'contexts.student_id')
                    ->on('activity.lecturer_user_id', '=', 'contexts.lecturer_id');
            });
        $this->withinLifetime($documents, 'activity.created_at');
        $this->withinLifetime($documents, 'activity.updated_at');

        $completed = DB::query()->fromSub(clone $contexts, 'contexts')
            ->join('mentorship_schedules as activity', function ($join): void {
                $join->on('activity.student_user_id', '=', 'contexts.student_id')
                    ->on('activity.lecturer_user_id', '=', 'contexts.lecturer_id');
            })
            ->where('contexts.thread_type', 'pembimbing')
            // Only the lecturer decision endpoint writes completed status.
            ->where('activity.status', 'completed');
        $this->withinLifetime($completed, 'activity.created_at');
        $this->withinLifetime($completed, 'activity.updated_at');

        $messages = DB::query()->fromSub(clone $contexts, 'contexts')
            ->join('mentorship_chat_messages as activity', 'activity.sender_user_id', '=', 'contexts.student_id')
            ->join('mentorship_chat_threads as threads', 'threads.id', '=', 'activity.mentorship_chat_thread_id')
            ->where('activity.message_type', '!=', 'system')
            ->where(function (Builder $query): void {
                $query->where(function (Builder $group): void {
                    $group->whereColumn('threads.student_user_id', 'contexts.student_id')
                        ->whereColumn('threads.type', 'contexts.thread_type')
                        ->where(function (Builder $defense): void {
                            $defense->whereNull('contexts.defense_id')
                                ->orWhereColumn('threads.context_id', 'contexts.defense_id');
                        });
                })->orWhere(function (Builder $private): void {
                    $private->where('threads.type', 'private')
                        ->whereExists(function (Builder $participant): void {
                            $participant->selectRaw('1')
                                ->from('mentorship_chat_thread_participants as student_participant')
                                ->whereColumn('student_participant.thread_id', 'threads.id')
                                ->whereColumn('student_participant.user_id', 'contexts.student_id');
                        })
                        ->whereExists(function (Builder $participant): void {
                            $participant->selectRaw('1')
                                ->from('mentorship_chat_thread_participants as lecturer_participant')
                                ->whereColumn('lecturer_participant.thread_id', 'threads.id')
                                ->whereColumn('lecturer_participant.user_id', 'contexts.lecturer_id');
                        });
                });
            });
        $this->withinLifetime($messages, 'activity.sent_at');

        $latest = [];
        foreach ([[$documents, 'updated_at'], [$completed, 'updated_at'], [$messages, 'sent_at']] as [$query, $column]) {
            $timestamps = $query->select('contexts.activity_key', 'contexts.relation_type')
                ->selectRaw("MAX(activity.{$column}) as latest_at")
                ->groupBy('contexts.activity_key', 'contexts.relation_type')->get();
            foreach ($timestamps as $row) {
                $key = $row->relation_type.':'.$row->activity_key;
                $timestamp = $row->latest_at;
                if ($timestamp !== null) {
                    $time = Carbon::parse($timestamp);
                    if (! isset($latest[$key]) || $time->gt($latest[$key])) {
                        $latest[$key] = $time;
                    }
                }
            }
        }

        // Archived projects lost their chat, documents and schedules; the snapshot keeps the value.
        // Read from the already loaded project so this stays at three queries.
        foreach ($assignments as $assignment) {
            $this->considerArchived($latest, 'supervisor:'.$assignment->id, $assignment->relationLoaded('project') ? $assignment->project : null);
        }
        foreach ($examiners as $examiner) {
            $project = $examiner->relationLoaded('defense') && $examiner->defense?->relationLoaded('project') ? $examiner->defense->project : null;
            $this->considerArchived($latest, 'examiner:'.$examiner->id, $project);
        }

        foreach ($examiners as $examiner) {
            $key = 'examiner:'.$examiner->id;
            foreach ([$examiner->decided_at, $examiner->defense?->updated_at] as $time) {
                if ($time !== null && (! isset($latest[$key]) || $time->gt($latest[$key]))) {
                    $latest[$key] = $time;
                }
            }
        }

        return $latest;
    }

    /**
     * @param  array<string, Carbon>  $latest
     */
    private function considerArchived(array &$latest, string $key, ?ThesisProject $project): void
    {
        $snapshotAt = $project?->archivedLastActivity();

        if ($snapshotAt !== null && (! isset($latest[$key]) || $snapshotAt->gt($latest[$key]))) {
            $latest[$key] = Carbon::instance($snapshotAt);
        }
    }

    private function withinLifetime(Builder $query, string $column): void
    {
        $query->where(function (Builder $query) use ($column): void {
            $query->whereNull('contexts.starts_at')->orWhereColumn($column, '>=', 'contexts.starts_at');
        })->where(function (Builder $query) use ($column): void {
            $query->whereNull('contexts.ends_at')->orWhereColumn($column, '<=', 'contexts.ends_at');
        });
    }
}
