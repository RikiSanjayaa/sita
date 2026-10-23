<?php

namespace App\Services;

use App\Models\ThesisProject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LecturerSearchService
{
    public const MINIMUM_QUERY_LENGTH = 2;

    public const RESULT_LIMIT = 20;

    /**
     * @param  array<int, int|string>  $selectedIds
     * @return Collection<int, array<string, mixed>>
     */
    public function search(
        ThesisProject $project,
        ?string $search,
        string $purpose,
        array $selectedIds = [],
    ): Collection {
        return $this->searchLecturers(
            programStudiId: (int) $project->program_studi_id,
            search: $search,
            purpose: $purpose,
            selectedIds: $selectedIds,
            project: $project,
            restrictToProgramStudi: false,
        );
    }

    /**
     * Searches lecturers teaching in the student's program studi, for the proposal form.
     *
     * @param  array<int, int|string>  $selectedIds
     * @return Collection<int, array<string, mixed>>
     */
    public function searchForStudent(User $student, ?string $search, array $selectedIds = []): Collection
    {
        $programStudiId = $student->mahasiswaProfile?->program_studi_id;

        if ($programStudiId === null) {
            return collect();
        }

        return $this->searchLecturers(
            programStudiId: (int) $programStudiId,
            search: $search,
            purpose: 'supervisor',
            selectedIds: $selectedIds,
            project: null,
            restrictToProgramStudi: true,
        );
    }

    /**
     * Returns the IDs from $lecturerIds that are active lecturers teaching in the program studi.
     *
     * @param  array<int, int>  $lecturerIds
     * @return array<int, int>
     */
    public function eligibleIdsInProgramStudi(int $programStudiId, array $lecturerIds): array
    {
        return $this->eligibleLecturersQuery($programStudiId)
            ->whereIn('id', $lecturerIds)
            ->whereHas('activeDosenProgramStudiAssignments', fn(Builder $query) => $query->where('program_studi_id', $programStudiId))
            ->pluck('id')
            ->map(fn($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int|string>  $selectedIds
     * @return Collection<int, array<string, mixed>>
     */
    private function searchLecturers(
        int $programStudiId,
        ?string $search,
        string $purpose,
        array $selectedIds,
        ?ThesisProject $project,
        bool $restrictToProgramStudi,
    ): Collection {
        $term = trim((string) $search);
        $selectedIds = collect($selectedIds)
            ->map(static fn($id): int => (int) $id)
            ->filter(static fn(int $id): bool => $id > 0)
            ->unique()
            ->values();

        if (mb_strlen($term) < self::MINIMUM_QUERY_LENGTH && $selectedIds->isEmpty()) {
            return collect();
        }

        $query = $this->eligibleLecturersQuery($programStudiId);

        if ($restrictToProgramStudi) {
            $query->whereHas('activeDosenProgramStudiAssignments', fn(Builder $query) => $query->where('program_studi_id', $programStudiId));
        }

        $query->where(function (Builder $query) use ($term, $selectedIds): void {
            if (mb_strlen($term) >= self::MINIMUM_QUERY_LENGTH) {
                $like = '%'.$term.'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->whereLike('name', $like)
                        ->orWhereHas('dosenProfile', fn(Builder $query) => $query->whereLike('nik', $like))
                        ->orWhereHas('activeDosenProgramStudiAssignments', function (Builder $query) use ($like): void {
                            $query->whereLike('concentration', $like)
                                ->orWhereHas('programStudi', fn(Builder $query) => $query->whereLike('name', $like));
                        })
                        ->orWhereHas('expertiseFields', fn(Builder $query) => $query->whereLike('name', $like));
                });
            }

            if ($selectedIds->isNotEmpty()) {
                $method = mb_strlen($term) >= self::MINIMUM_QUERY_LENGTH ? 'orWhereIn' : 'whereIn';
                $query->{$method}('id', $selectedIds->all());
            }
        });

        return $query
            ->limit(self::RESULT_LIMIT + $selectedIds->count())
            ->get()
            ->sortBy([
                ['is_same_program', 'desc'],
                ['name', 'asc'],
            ])
            ->take(self::RESULT_LIMIT + $selectedIds->count())
            ->map(fn(User $lecturer): array => $this->present($lecturer, $project, $purpose))
            ->values();
    }

    /**
     * @return array<int, string>
     */
    public function filamentOptions(ThesisProject $project, string $search, string $purpose): array
    {
        return $this->search($project, $search, $purpose)
            ->mapWithKeys(fn(array $lecturer): array => [
                $lecturer['id'] => $this->optionLabel($lecturer),
            ])
            ->all();
    }

    public function filamentOptionLabel(ThesisProject $project, int|string|null $lecturerId, string $purpose): ?string
    {
        if (blank($lecturerId)) {
            return null;
        }

        $lecturer = $this->search($project, null, $purpose, [(int) $lecturerId])->first();

        return is_array($lecturer) ? $this->optionLabel($lecturer) : null;
    }

    /**
     * @param  array<int, int|string>  $lecturerIds
     * @return array<int, string>
     */
    public function filamentOptionLabels(ThesisProject $project, array $lecturerIds, string $purpose): array
    {
        return $this->search($project, null, $purpose, $lecturerIds)
            ->mapWithKeys(fn(array $lecturer): array => [
                $lecturer['id'] => $this->optionLabel($lecturer),
            ])
            ->all();
    }

    private function eligibleLecturersQuery(int $programStudiId): Builder
    {
        return User::query()
            ->whereHas('roles', static fn(Builder $query) => $query->where('name', 'dosen'))
            ->whereHas('dosenProfile', static fn(Builder $query) => $query->where('is_active', true))
            ->with([
                'dosenProfile',
                'activeDosenProgramStudiAssignments.programStudi',
                'expertiseFields',
            ])
            ->withExists([
                'activeDosenProgramStudiAssignments as is_same_program' => fn(Builder $query) => $query->where('program_studi_id', $programStudiId),
            ])
            ->withCount([
                'thesisSupervisorAssignments as active_supervision_count' => static fn(Builder $query) => $query
                    ->where('status', 'active')
                    ->whereHas('project', static fn(Builder $query) => $query->current()->where('state', 'active')),
            ])
            ->orderByDesc('is_same_program')
            ->orderBy('name');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(User $lecturer, ?ThesisProject $project, string $purpose): array
    {
        $quota = max(1, (int) ($lecturer->dosenProfile?->supervision_quota ?? 14));
        $workload = (int) ($lecturer->active_supervision_count ?? 0);
        $isSelectedSupervisor = $project?->activeSupervisorAssignments
            ->contains('lecturer_user_id', $lecturer->id) ?? false;
        $available = $purpose !== 'supervisor' || $workload < $quota || $isSelectedSupervisor;

        return [
            'id' => (int) $lecturer->id,
            'name' => $lecturer->name,
            'nik' => $lecturer->dosenProfile?->nik,
            'sameProgram' => (bool) $lecturer->is_same_program,
            'placements' => $lecturer->activeDosenProgramStudiAssignments
                ->map(fn($assignment): string => $assignment->programStudi?->name ?? '-')
                ->filter(fn(string $name): bool => $name !== '-')
                ->unique()
                ->values()
                ->all(),
            'concentrations' => $lecturer->activeDosenProgramStudiAssignments
                ->pluck('concentration')
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'expertiseFields' => $lecturer->expertiseFields
                ->pluck('name')
                ->sort()
                ->values()
                ->all(),
            'activeSupervisionCount' => $workload,
            'quota' => $quota,
            'available' => $available,
            'unavailableReason' => $available ? null : sprintf('Kuota bimbingan penuh (%d/%d).', $workload, $quota),
            'profileUrl' => route('users.profile.show', ['user' => $lecturer->id]),
        ];
    }

    /**
     * @param  array<string, mixed>  $lecturer
     */
    private function optionLabel(array $lecturer): string
    {
        $placements = $lecturer['placements'] !== [] ? implode(', ', $lecturer['placements']) : 'Tanpa penempatan prodi';
        $expertise = $lecturer['expertiseFields'] !== [] ? implode(', ', $lecturer['expertiseFields']) : 'Bidang keilmuan belum diatur';
        $capacity = sprintf('%d/%d aktif', $lecturer['activeSupervisionCount'], $lecturer['quota']);

        return sprintf(
            '%s (%s) - %s - %s - %s%s',
            $lecturer['name'],
            $lecturer['nik'] ?? '-',
            $placements,
            $expertise,
            $capacity,
            $lecturer['available'] ? '' : ' - KUOTA PENUH',
        );
    }
}
