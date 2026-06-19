<?php

namespace App\Services;

use App\Enums\AppRole;
use App\Enums\DegreeLevel;
use App\Models\DosenProgramStudiAssignment;
use App\Models\ExpertiseField;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class UserProvisioningService
{
    public function syncRoleAndProfiles(User $user, array $data): void
    {
        $phoneNumber = $this->nullableString($data['phone_number'] ?? ($data['phone'] ?? null));

        if ($phoneNumber !== null || array_key_exists('phone_number', $data) || array_key_exists('phone', $data)) {
            $user->forceFill([
                'phone_number' => $phoneNumber,
            ])->save();
        }

        $role = AppRole::tryFrom((string) ($data['role'] ?? ''))?->value;

        if ($role !== null) {
            // Security: Only super_admin can create another admin or super_admin
            $isAdminRole = in_array($role, [AppRole::Admin->value, AppRole::SuperAdmin->value], true);
            /** @var User|null $authenticatedUser */
            $authenticatedUser = Auth::user();
            $isSuperAdmin = $authenticatedUser?->hasRole(AppRole::SuperAdmin);

            if ($isAdminRole && ! $isSuperAdmin) {
                return; // Silently prevent non-super-admins from creating admins
            }

            $roleModel = Role::query()->firstOrCreate(['name' => $role]);
            $user->roles()->sync([$roleModel->id]);

            if ($user->last_active_role !== $role) {
                $user->forceFill(['last_active_role' => $role])->save();
            }
        }

        if ($role === AppRole::Kaprodi->value) {
            if (! $user->kaprodiAssignment()->exists()) {
                throw ValidationException::withMessages([
                    'role' => ['Akun kaprodi wajib ditempatkan melalui resource Program Studi.'],
                ]);
            }

            return;
        }

        if ($role === AppRole::Admin->value) {
            $user->adminProfile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'program_studi_id' => $this->nullableInt($data['prodi'] ?? null),
                ],
            );

            return;
        }

        if ($role === AppRole::Mahasiswa->value) {
            $programStudiId = $this->nullableInt($data['prodi'] ?? ($data['program_studi_id'] ?? null));

            $user->mahasiswaProfile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'nim' => $this->nullableString($data['nim'] ?? null),
                    'program_studi_id' => $programStudiId,
                    'degree_level' => $this->resolveDegreeLevel(
                        programStudiId: $programStudiId,
                        degreeLevel: $data['degree_level'] ?? null,
                        currentValue: $user->mahasiswaProfile?->degree_level,
                    ),
                    'concentration' => $this->resolveConcentration(
                        programStudiId: $programStudiId,
                        concentration: $data['concentration'] ?? null,
                        currentValue: $user->mahasiswaProfile?->concentration,
                    ),
                    'angkatan' => $this->nullableInt($data['angkatan'] ?? null),
                    'is_active' => (bool) ($data['is_active'] ?? true),
                ],
            );

            return;
        }

        if ($role === AppRole::Dosen->value) {
            $assignments = $this->normalizeDosenAssignments($data, $user);
            $primaryAssignment = collect($assignments)->firstWhere('is_primary', true) ?? $assignments[0];

            $user->dosenProfile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'nik' => $this->nullableString($data['nik'] ?? ($data['nidn'] ?? null)),
                    'program_studi_id' => $primaryAssignment['program_studi_id'],
                    'concentration' => $primaryAssignment['concentration'],
                    'supervision_quota' => $this->nullableInt($data['supervision_quota'] ?? null)
                        ?? $user->dosenProfile?->supervision_quota
                        ?? 14,
                    'is_active' => (bool) ($data['is_active'] ?? true),
                ],
            );

            $this->syncDosenAssignments($user, $assignments);
            $this->syncExpertiseFields($user, $data);
        }
    }

    /**
     * @return array<int, int>
     */
    public function resolveExpertiseFieldIds(mixed $value): array
    {
        $values = is_array($value)
            ? $value
            : (preg_split('/[|;,]/', trim((string) $value)) ?: []);

        $normalizedValues = collect($values)
            ->map(fn($item): string => trim((string) $item))
            ->filter()
            ->unique()
            ->values();

        if ($normalizedValues->isEmpty()) {
            return [];
        }

        $fields = ExpertiseField::query()
            ->where('is_active', true)
            ->get();

        return $normalizedValues
            ->map(function (string $value) use ($fields): int {
                $field = ctype_digit($value)
                    ? $fields->firstWhere('id', (int) $value)
                    : $fields->first(function (ExpertiseField $field) use ($value): bool {
                        $lookup = str($value)->lower()->squish()->toString();

                        return str($field->name)->lower()->squish()->toString() === $lookup
                            || str($field->slug)->lower()->squish()->toString() === $lookup;
                    });

                if (! $field instanceof ExpertiseField) {
                    throw ValidationException::withMessages([
                        'expertise_field_ids' => ["Bidang keilmuan '{$value}' tidak ditemukan atau tidak aktif."],
                    ]);
                }

                return (int) $field->id;
            })
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{program_studi_id: int, concentration: string, is_primary: bool, is_active: bool}>
     */
    private function normalizeDosenAssignments(array $data, User $user): array
    {
        $rawAssignments = collect($data['academic_assignments'] ?? [])
            ->filter(fn($assignment): bool => is_array($assignment))
            ->values();

        if ($rawAssignments->isEmpty()) {
            $programStudiId = $this->nullableInt($data['prodi'] ?? ($data['program_studi_id'] ?? null));

            $rawAssignments = collect([
                [
                    'program_studi_id' => $programStudiId,
                    'concentration' => $data['concentration'] ?? $user->dosenProfile?->concentration,
                    'is_primary' => true,
                    'is_active' => $data['is_active'] ?? true,
                ],
            ]);
        }

        $assignments = $rawAssignments
            ->map(function (array $assignment, int $index): array {
                $programStudiId = $this->nullableInt($assignment['program_studi_id'] ?? ($assignment['prodi'] ?? null));

                if ($programStudiId === null) {
                    throw ValidationException::withMessages([
                        'academic_assignments' => ['Program studi wajib dipilih untuk setiap penempatan dosen.'],
                    ]);
                }

                return [
                    'program_studi_id' => $programStudiId,
                    'concentration' => $this->resolveConcentration(
                        programStudiId: $programStudiId,
                        concentration: $assignment['concentration'] ?? null,
                        currentValue: null,
                    ),
                    'is_primary' => (bool) ($assignment['is_primary'] ?? $index === 0),
                    'is_active' => (bool) ($assignment['is_active'] ?? true),
                ];
            })
            ->unique(fn(array $assignment): string => $assignment['program_studi_id'].'|'.$assignment['concentration'])
            ->values();

        if ($assignments->isEmpty()) {
            throw ValidationException::withMessages([
                'academic_assignments' => ['Dosen wajib memiliki minimal satu penempatan prodi dan konsentrasi.'],
            ]);
        }

        if ($assignments->where('is_primary', true)->count() !== 1) {
            $assignments = $assignments->map(fn(array $assignment, int $index): array => [
                ...$assignment,
                'is_primary' => $index === 0,
            ]);
        }

        return $assignments->all();
    }

    /**
     * @param  array<int, array{program_studi_id: int, concentration: string, is_primary: bool, is_active: bool}>  $assignments
     */
    private function syncDosenAssignments(User $user, array $assignments): void
    {
        $keptIds = [];

        foreach ($assignments as $assignment) {
            $record = DosenProgramStudiAssignment::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'program_studi_id' => $assignment['program_studi_id'],
                    'concentration' => $assignment['concentration'],
                ],
                [
                    'is_primary' => $assignment['is_primary'],
                    'is_active' => $assignment['is_active'],
                ],
            );

            $keptIds[] = $record->id;
        }

        DosenProgramStudiAssignment::query()
            ->where('user_id', $user->id)
            ->whereNotIn('id', $keptIds)
            ->update([
                'is_active' => false,
                'is_primary' => false,
            ]);
    }

    private function syncExpertiseFields(User $user, array $data): void
    {
        if (! array_key_exists('expertise_field_ids', $data) && ! array_key_exists('expertise_fields', $data)) {
            return;
        }

        $fieldIds = $this->resolveExpertiseFieldIds(
            $data['expertise_field_ids'] ?? $data['expertise_fields'] ?? [],
        );
        $assignedBy = Auth::id();

        $user->expertiseFields()->sync(
            collect($fieldIds)
                ->mapWithKeys(fn(int $fieldId): array => [
                    $fieldId => ['assigned_by_user_id' => $assignedBy],
                ])
                ->all(),
        );
    }

    private function resolveDegreeLevel(?int $programStudiId, mixed $degreeLevel, ?string $currentValue): string
    {
        $programStudi = $programStudiId === null
            ? null
            : ProgramStudi::query()->find($programStudiId);

        if (! $programStudi instanceof ProgramStudi) {
            throw ValidationException::withMessages([
                'prodi' => ['Program studi wajib dipilih sebelum menentukan jenjang.'],
            ]);
        }

        $normalizedDegreeLevel = strtolower(
            $this->nullableString($degreeLevel) ?? $currentValue ?? '',
        );
        $availableLevels = $programStudi->degreeLevelList();

        if ($normalizedDegreeLevel === '' && count($availableLevels) === 1) {
            return $availableLevels[0];
        }

        if (! in_array($normalizedDegreeLevel, $availableLevels, true)) {
            throw ValidationException::withMessages([
                'degree_level' => [sprintf(
                    'Jenjang wajib dipilih dari jenjang yang tersedia: %s.',
                    collect($availableLevels)
                        ->map(fn(string $level): string => DegreeLevel::from($level)->label())
                        ->implode(', '),
                )],
            ]);
        }

        return $normalizedDegreeLevel;
    }

    private function resolveConcentration(?int $programStudiId, mixed $concentration, ?string $currentValue): string
    {
        $programStudi = $programStudiId === null
            ? null
            : ProgramStudi::query()->find($programStudiId);

        if (! $programStudi instanceof ProgramStudi) {
            throw ValidationException::withMessages([
                'prodi' => ['Program studi wajib dipilih sebelum menentukan konsentrasi.'],
            ]);
        }

        $normalizedConcentration = $this->nullableString($concentration) ?? $currentValue;

        if ($normalizedConcentration === null) {
            throw ValidationException::withMessages([
                'concentration' => ['Konsentrasi wajib dipilih.'],
            ]);
        }

        if (! array_key_exists($normalizedConcentration, $programStudi->concentrationOptions())) {
            throw ValidationException::withMessages([
                'concentration' => ['Konsentrasi tidak tersedia untuk program studi yang dipilih.'],
            ]);
        }

        return $normalizedConcentration;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string) $value);

        return $stringValue === '' ? null : $stringValue;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
