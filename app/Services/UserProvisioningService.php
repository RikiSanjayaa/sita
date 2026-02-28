<?php

namespace App\Services;

use App\Enums\AppRole;
use App\Models\Role;
use App\Models\User;

class UserProvisioningService
{
    public function syncRoleAndProfiles(User $user, array $data): void
    {
        $role = AppRole::tryFrom((string) ($data['role'] ?? ''))?->value;

        if ($role !== null) {
            $roleModel = Role::query()->firstOrCreate(['name' => $role]);
            $user->roles()->sync([$roleModel->id]);

            if ($user->last_active_role !== $role) {
                $user->forceFill(['last_active_role' => $role])->save();
            }
        }

        if ($role === AppRole::Mahasiswa->value) {
            $user->mahasiswaProfile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'nim' => $this->nullableString($data['nim'] ?? null),
                    'program_studi' => $this->nullableString($data['program_studi'] ?? null),
                    'angkatan' => $this->nullableInt($data['angkatan'] ?? null),
                    'status_akademik' => $this->nullableString($data['status_akademik'] ?? null),
                ],
            );

            return;
        }

        if ($role === AppRole::Dosen->value) {
            $user->dosenProfile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'nidn' => $this->nullableString($data['nidn'] ?? null),
                    'homebase' => $this->nullableString($data['homebase'] ?? ($data['program_studi'] ?? null)),
                    'is_active' => (bool) ($data['is_active'] ?? true),
                ],
            );
        }
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
