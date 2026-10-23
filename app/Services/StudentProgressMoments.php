<?php

namespace App\Services;

use App\Models\ThesisProject;
use App\Support\AcademicTerminology;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The "progress moment" of a thesis project: the latest of the moments below.
 * It drives the default order of the Progres Mahasiswa page.
 *
 * - sidang or sempro/sinopsis scheduled, and held (completed or awaiting a decision)
 * - supervisors assigned
 * - title submitted, and title approved
 * - project started
 *
 * Both the SQL ordering and the label of the winning moment come from the same
 * union, so they cannot disagree. Every method runs a fixed number of queries.
 */
class StudentProgressMoments
{
    /**
     * Kinds ordered by priority: on an exact tie the first one wins.
     *
     * @var list<string>
     */
    private const KINDS = [
        'sidang_held',
        'sidang_scheduled',
        'sempro_held',
        'sempro_scheduled',
        'supervisors',
        'title_approved',
        'title_submitted',
        'project_started',
    ];

    /**
     * One row per project and moment: project_id, at, kind.
     */
    public function moments(): Builder
    {
        $union = null;

        foreach (['sidang', 'sempro'] as $type) {
            $scheduled = DB::table('thesis_defenses')
                ->selectRaw("project_id, updated_at as at, '{$type}_scheduled' as kind")
                ->where('type', $type)
                ->where('status', 'scheduled');
            $held = DB::table('thesis_defenses')
                ->selectRaw("project_id, COALESCE(decision_at, scheduled_for, updated_at) as at, '{$type}_held' as kind")
                ->where('type', $type)
                ->whereIn('status', ['awaiting_finalization', 'completed']);

            $union = $union === null ? $scheduled : $union->unionAll($scheduled);
            $union->unionAll($held);
        }

        $union->unionAll(DB::table('thesis_supervisor_assignments')
            ->selectRaw("project_id, COALESCE(started_at, created_at) as at, 'supervisors' as kind")
            ->where('status', 'active'));
        $union->unionAll(DB::table('thesis_project_titles')
            ->selectRaw("project_id, submitted_at as at, 'title_submitted' as kind")
            ->whereNotNull('submitted_at'));
        $union->unionAll(DB::table('thesis_project_titles')
            ->selectRaw("project_id, decided_at as at, 'title_approved' as kind")
            ->where('status', 'approved')
            ->whereNotNull('decided_at'));
        $union->unionAll(DB::table('thesis_projects')
            ->selectRaw("id as project_id, COALESCE(started_at, created_at) as at, 'project_started' as kind"));

        return DB::query()->fromSub($union, 'progress_moment_rows')->select('project_id', 'at', 'kind');
    }

    /**
     * Latest moment per project: project_id, moment. Join it to order by recency.
     */
    public function latestPerProject(): Builder
    {
        return DB::query()
            ->fromSub($this->moments(), 'progress_moment_all')
            ->select('project_id')
            ->selectRaw('max(at) as moment')
            ->groupBy('project_id');
    }

    /**
     * The winning moment for each given project id: kind, label and time.
     *
     * @param  list<int>  $projectIds
     * @param  \Illuminate\Support\Collection<int, ThesisProject>|array<int, ThesisProject>  $projects  keyed by project id, for terminology
     * @return array<int, array{kind: string, label: string, at: string, relative: string}>
     */
    public function forProjects(array $projectIds, iterable $projects): array
    {
        if ($projectIds === []) {
            return [];
        }

        $best = [];
        foreach ($this->moments()->whereIn('project_id', $projectIds)->get() as $row) {
            $projectId = (int) $row->project_id;
            $current = $best[$projectId] ?? null;

            if ($current === null
                || $row->at > $current->at
                || ($row->at === $current->at
                    && array_search($row->kind, self::KINDS, true) < array_search($current->kind, self::KINDS, true))) {
                $best[$projectId] = $row;
            }
        }

        $result = [];
        foreach ($best as $projectId => $row) {
            $project = $projects[$projectId] ?? null;
            if (! $project instanceof ThesisProject) {
                continue;
            }

            $at = CarbonImmutable::parse($row->at);
            $result[$projectId] = [
                'kind' => $row->kind,
                'label' => $this->label($project, $row->kind),
                'at' => $at->toIso8601String(),
                'relative' => $at->locale('id')->diffForHumans(),
            ];
        }

        return $result;
    }

    private function label(ThesisProject $project, string $kind): string
    {
        $terms = AcademicTerminology::forProject($project);

        return match ($kind) {
            'sidang_scheduled' => $terms['finalExamShort'].' dijadwalkan',
            'sidang_held' => $terms['finalExamShort'].' dilaksanakan',
            'sempro_scheduled' => $terms['proposalExamShort'].' dijadwalkan',
            'sempro_held' => $terms['proposalExamShort'].' dilaksanakan',
            'supervisors' => 'Pembimbing ditetapkan',
            'title_approved' => 'Judul disetujui',
            'title_submitted' => 'Judul diajukan',
            default => $terms['finalWork'].' dimulai',
        };
    }
}
