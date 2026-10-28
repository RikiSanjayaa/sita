<?php

namespace App\Http\Controllers;

use App\Enums\AppRole;
use App\Http\Requests\StudentProgressIndexRequest;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Services\StudentProgressMoments;
use App\Services\StudentProgressSnapshot;
use App\Support\AcademicTerminology;
use App\Support\WitaDateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Progress Seluruh Mahasiswa: the same student population as the kaprodi "Mahasiswa Prodi" page
 * (see KaprodiPortalService::studentIndex()) across every program studi. Each student is shown
 * with the current thesis project (ThesisProject::current()); students without one are
 * "Belum ada tugas akhir". Newest stage progress first, see StudentProgressMoments.
 */
class StudentProgressController extends Controller
{
    private const NO_PROJECT = 'none';

    public function __invoke(
        StudentProgressIndexRequest $request,
        StudentProgressSnapshot $snapshot,
        StudentProgressMoments $moments,
    ): Response {
        $filters = [
            'search' => $this->searchTerm($request->validated('search')),
            'prodi' => $request->validated('prodi') ?: null,
            'phase' => $request->validated('phase'),
            'angkatan' => $request->validated('angkatan') ?: null,
            'perPage' => (int) ($request->validated('per_page') ?? 15),
        ];

        $counts = $this->filteredQuery($filters, includePhase: false)
            ->select(DB::raw($this->phaseKeySql().' as phase_key'), DB::raw('count(*) as total'))
            ->groupBy('phase_key')
            ->pluck('total', 'phase_key');

        $studentUserId = $request->user()->hasRole(AppRole::Mahasiswa) ? $request->user()->id : null;

        $paginator = $this->filteredQuery($filters, includePhase: true)
            ->leftJoinSub($moments->latestPerProject(), 'progress_moments', 'progress_moments.project_id', '=', 'thesis_projects.id')
            ->select('mahasiswa_profiles.*', 'thesis_projects.id as current_project_id')
            ->when($studentUserId !== null, fn (Builder $query): Builder => $query
                ->orderByRaw('CASE WHEN mahasiswa_profiles.user_id = ? THEN 0 ELSE 1 END', [$studentUserId]))
            ->orderByRaw('CASE WHEN thesis_projects.id IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('progress_moments.moment')
            ->orderBy('users.name')
            ->orderBy('mahasiswa_profiles.id')
            ->with(['user', 'programStudi'])
            ->paginate($filters['perPage'])
            ->withQueryString();

        $projects = $this->projectsFor($paginator->getCollection());
        $progress = $snapshot->forProjects($projects, $request->user(), detailForAll: true);
        $latestMoments = $moments->forProjects($projects->keys()->all(), $projects);

        $rows = $paginator->getCollection()
            ->map(function (MahasiswaProfile $profile) use ($projects, $progress, $latestMoments, $studentUserId): array {
                $project = $projects[(int) $profile->getAttribute('current_project_id')] ?? null;

                return $this->row(
                    $profile,
                    $project,
                    $project instanceof ThesisProject ? $progress[$project->id] : null,
                    $project instanceof ThesisProject ? ($latestMoments[$project->id] ?? null) : null,
                    $profile->user_id === $studentUserId,
                );
            })
            ->values()
            ->all();

        return Inertia::render('progres-mahasiswa', [
            'filters' => $filters,
            'filterOptions' => [
                'prodi' => ProgramStudi::query()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (ProgramStudi $prodi): array => ['value' => $prodi->id, 'label' => $prodi->name])
                    ->values()
                    ->all(),
                'angkatan' => MahasiswaProfile::query()
                    ->where('is_active', true)
                    ->whereNotNull('angkatan')
                    ->distinct()
                    ->orderByDesc('angkatan')
                    ->pluck('angkatan')
                    ->map(fn (int $year): array => ['value' => $year, 'label' => (string) $year])
                    ->values()
                    ->all(),
            ],
            'phases' => $this->phasePills($counts),
            'rows' => $rows,
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'perPage' => $paginator->perPage(),
            ],
            'agenda' => $this->agenda(),
        ]);
    }

    private function searchTerm(?string $search): ?string
    {
        $term = trim((string) $search);

        return $term === '' ? null : $term;
    }

    /**
     * Phase key of a student as the kaprodi page shows it: a completed project is always
     * "completed", no current project is "none", anything else is the project phase.
     */
    private function phaseKeySql(): string
    {
        return "CASE WHEN thesis_projects.id IS NULL THEN 'none' WHEN thesis_projects.state = 'completed' THEN 'completed' ELSE thesis_projects.phase END";
    }

    /**
     * Mahasiswa profiles with their current project, filtered in SQL so every count and page is
     * computed over the full filtered set. Population = KaprodiPortalService: active students
     * (with or without a thesis) plus every completed current project.
     *
     * @param  array{search: ?string, prodi: ?int, phase: ?string, angkatan: ?int, perPage: int}  $filters
     * @return Builder<MahasiswaProfile>
     */
    private function filteredQuery(array $filters, bool $includePhase): Builder
    {
        return MahasiswaProfile::query()
            ->join('users', 'users.id', '=', 'mahasiswa_profiles.user_id')
            ->leftJoin('program_studis', 'program_studis.id', '=', 'mahasiswa_profiles.program_studi_id')
            ->leftJoin('thesis_projects', function (JoinClause $join): void {
                $join->on('thesis_projects.student_user_id', '=', 'mahasiswa_profiles.user_id')
                    ->whereIn('thesis_projects.id', ThesisProject::query()->current()->select('thesis_projects.id'));
            })
            ->where(fn (Builder $query): Builder => $query
                ->where('mahasiswa_profiles.is_active', true)
                ->orWhere('thesis_projects.state', 'completed'))
            ->when($filters['search'], fn (Builder $query, string $search): Builder => $query->where(fn (Builder $inner): Builder => $inner
                ->whereLike('users.name', "%{$search}%")
                ->orWhereLike('mahasiswa_profiles.nim', "%{$search}%")
                ->orWhereLike('program_studis.name', "%{$search}%")))
            ->when($filters['prodi'], fn (Builder $query, int $prodi): Builder => $query->where('mahasiswa_profiles.program_studi_id', $prodi))
            ->when($filters['angkatan'], fn (Builder $query, int $angkatan): Builder => $query->where('mahasiswa_profiles.angkatan', $angkatan))
            ->when($includePhase ? $filters['phase'] : null, fn (Builder $query, string $phase): Builder => $query->whereRaw($this->phaseKeySql().' = ?', [$phase]));
    }

    /**
     * Current projects of the page, keyed by project id, with everything the rows and the
     * snapshot read loaded up front.
     *
     * @param  Collection<int, MahasiswaProfile>  $profiles
     * @return EloquentCollection<int, ThesisProject>
     */
    private function projectsFor(Collection $profiles): EloquentCollection
    {
        $ids = $profiles->pluck('current_project_id')->filter()->map(fn ($id): int => (int) $id)->values()->all();

        return ThesisProject::query()
            ->whereKey($ids)
            ->with(['student.mahasiswaProfile', 'programStudi', 'approvedTitles', 'passedSemproDefenses'])
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  array<string, mixed>|null  $progress  result of StudentProgressSnapshot::forProjects()
     * @param  array<string, mixed>|null  $moment
     * @return array<string, mixed>
     */
    private function row(MahasiswaProfile $profile, ?ThesisProject $project, ?array $progress, ?array $moment, bool $isSelf): array
    {
        $student = $profile->user;
        $phaseKey = match (true) {
            ! $project instanceof ThesisProject => self::NO_PROJECT,
            $project->state === 'completed' => 'completed',
            default => (string) $project->phase,
        };

        if ($progress !== null) {
            $progress['phase'] = $phaseKey;
            $progress['phaseLabel'] = $phaseKey === 'cancelled' ? 'Dibatalkan' : AcademicTerminology::phaseLabel($phaseKey);
        }

        return [
            'id' => $profile->user_id,
            'isSelf' => $isSelf,
            'projectId' => $project?->id,
            'name' => $student?->name ?? '-',
            'nim' => $profile->nim,
            'avatar' => $student?->avatar,
            'profileUrl' => route('users.profile.show', ['user' => $profile->user_id]),
            'prodi' => $profile->programStudi?->name,
            'degreeLevel' => strtoupper((string) $profile->degree_level),
            'angkatan' => $profile->angkatan,
            'concentration' => $profile->concentration,
            'isObeCurriculum' => (bool) $profile->is_obe_curriculum,
            'phaseKey' => $phaseKey,
            'phaseLabel' => match ($phaseKey) {
                self::NO_PROJECT => $this->noProjectLabel(),
                'cancelled' => 'Dibatalkan',
                default => AcademicTerminology::phaseLabel($phaseKey),
            },
            'title' => $project?->publicTitle()?->title_id,
            'progress' => $progress,
            'moment' => $moment,
        ];
    }

    /** Cross-degree list, so the neutral label. */
    private function noProjectLabel(): string
    {
        return 'Belum ada '.mb_strtolower(AcademicTerminology::neutral()['finalWork']);
    }

    /**
     * @param  \Illuminate\Support\Collection<string, int>  $counts
     * @return list<array{value: string, label: string, count: int}>
     */
    private function phasePills($counts): array
    {
        $pills = [[
            'value' => 'all',
            'label' => 'Semua',
            'count' => (int) $counts->sum(),
        ]];

        $labels = AcademicTerminology::phaseLabels()
            + ['cancelled' => 'Dibatalkan', self::NO_PROJECT => $this->noProjectLabel()];

        foreach ($labels as $value => $label) {
            $pills[] = [
                'value' => $value,
                'label' => $label,
                'count' => (int) ($counts[$value] ?? 0),
            ];
        }

        return $pills;
    }

    /**
     * Next scheduled defenses of active students, across the whole active set.
     *
     * @return list<array{id: int, when: string, type: string, name: string, prodi: ?string}>
     */
    private function agenda(): array
    {
        return ThesisDefense::query()
            ->with(['project.student.mahasiswaProfile', 'project.programStudi'])
            ->where('status', 'scheduled')
            ->whereIn('type', ['sempro', 'sidang'])
            ->where('scheduled_for', '>=', now())
            ->whereHas('project', fn (Builder $query): Builder => $query->current()->where('state', 'active'))
            ->orderBy('scheduled_for')
            ->limit(5)
            ->get()
            ->map(fn (ThesisDefense $defense): array => [
                'id' => $defense->id,
                'when' => WitaDateTime::format($defense->scheduled_for, 'd M Y H:i'),
                'type' => AcademicTerminology::defenseLabel(
                    AcademicTerminology::forProject($defense->project),
                    $defense->type,
                    true,
                ),
                'name' => $defense->project->student->name,
                'prodi' => $defense->project->programStudi?->name,
            ])
            ->values()
            ->all();
    }
}
