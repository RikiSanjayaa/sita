<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentProgressIndexRequest;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Services\StudentProgressSnapshot;
use App\Support\AcademicTerminology;
use App\Support\WitaDateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StudentProgressController extends Controller
{
    public function __invoke(StudentProgressIndexRequest $request, StudentProgressSnapshot $snapshot): Response
    {
        $filters = [
            'search' => $this->searchTerm($request->validated('search')),
            'prodi' => $request->validated('prodi') ?: null,
            'phase' => $request->validated('phase'),
            'angkatan' => $request->validated('angkatan') ?: null,
            'perPage' => (int) ($request->validated('per_page') ?? 15),
        ];

        $counts = $this->filteredQuery($filters, includePhase: false)
            ->reorder()
            ->select('thesis_projects.phase as phase', DB::raw('count(*) as total'))
            ->groupBy('thesis_projects.phase')
            ->pluck('total', 'phase');

        $paginator = $this->filteredQuery($filters, includePhase: true)
            ->with(['student.mahasiswaProfile', 'programStudi', 'approvedTitles'])
            ->orderBy('users.name')
            ->orderBy('thesis_projects.id')
            ->paginate($filters['perPage'])
            ->withQueryString();

        $progress = $snapshot->forProjects($paginator->getCollection(), $request->user(), detailForAll: true);

        $rows = $paginator->getCollection()
            ->map(fn (ThesisProject $project): array => $this->row($project, $progress[$project->id]))
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
     * Active thesis projects of active students, filtered in SQL so every count and page
     * is computed over the full filtered set.
     *
     * @param  array{search: ?string, prodi: ?int, phase: ?string, angkatan: ?int, perPage: int}  $filters
     * @return Builder<ThesisProject>
     */
    private function filteredQuery(array $filters, bool $includePhase): Builder
    {
        return ThesisProject::query()
            ->select('thesis_projects.*')
            ->join('users', 'users.id', '=', 'thesis_projects.student_user_id')
            ->join('mahasiswa_profiles', 'mahasiswa_profiles.user_id', '=', 'thesis_projects.student_user_id')
            ->join('program_studis', 'program_studis.id', '=', 'thesis_projects.program_studi_id')
            ->where('thesis_projects.state', 'active')
            ->where('mahasiswa_profiles.is_active', true)
            ->when($filters['search'], fn (Builder $query, string $search): Builder => $query->where(fn (Builder $inner): Builder => $inner
                ->whereLike('users.name', "%{$search}%")
                ->orWhereLike('mahasiswa_profiles.nim', "%{$search}%")
                ->orWhereLike('program_studis.name', "%{$search}%")))
            ->when($filters['prodi'], fn (Builder $query, int $prodi): Builder => $query->where('thesis_projects.program_studi_id', $prodi))
            ->when($filters['angkatan'], fn (Builder $query, int $angkatan): Builder => $query->where('mahasiswa_profiles.angkatan', $angkatan))
            ->when($includePhase ? $filters['phase'] : null, fn (Builder $query, string $phase): Builder => $query->where('thesis_projects.phase', $phase));
    }

    /**
     * @param  array<string, mixed>  $progress  result of StudentProgressSnapshot::forProjects()
     * @return array<string, mixed>
     */
    private function row(ThesisProject $project, array $progress): array
    {
        $student = $project->student;
        $profile = $student->mahasiswaProfile;

        return [
            'id' => $project->id,
            'name' => $student->name,
            'nim' => $profile?->nim,
            'profileUrl' => route('users.profile.show', ['user' => $student->id]),
            'prodi' => $project->programStudi?->name,
            'angkatan' => $profile?->angkatan,
            'title' => $project->publicTitle()?->title_id,
            'progress' => $progress,
        ];
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

        foreach (AcademicTerminology::phaseLabels() as $value => $label) {
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
            ->with(['project.student', 'project.programStudi'])
            ->where('status', 'scheduled')
            ->whereIn('type', ['sempro', 'sidang'])
            ->where('scheduled_for', '>=', now())
            ->whereHas('project', fn (Builder $query): Builder => $query->where('state', 'active'))
            ->orderBy('scheduled_for')
            ->limit(8)
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
