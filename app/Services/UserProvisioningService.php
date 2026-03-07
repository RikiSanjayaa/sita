<?php

namespace App\Services;

use App\Enums\AppRole;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class UserProvisioningService
{
    public function syncRoleAndProfiles(User $user, array $data): void
    {
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
            $programStudiId = $this->nullableInt($data['prodi'] ?? ($data['program_studi_id'] ?? null));

            $user->dosenProfile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'nik' => $this->nullableString($data['nik'] ?? ($data['nidn'] ?? null)),
                    'program_studi_id' => $programStudiId,
                    'concentration' => $this->resolveConcentration(
                        programStudiId: $programStudiId,
                        concentration: $data['concentration'] ?? null,
                        currentValue: $user->dosenProfile?->concentration,
                    ),
                    'supervision_quota' => $this->nullableInt($data['supervision_quota'] ?? null)
                        ?? $user->dosenProfile?->supervision_quota
                        ?? 14,
                    'is_active' => (bool) ($data['is_active'] ?? true),
                ],
            );
        }
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
