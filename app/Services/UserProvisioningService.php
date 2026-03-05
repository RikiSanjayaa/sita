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
            // Security: Only super_admin can create another admin or super_admin
            $isAdminRole = in_array($role, [AppRole::Admin->value, AppRole::SuperAdmin->value], true);
            $isSuperAdmin = auth()->user()?->hasRole(AppRole::SuperAdmin);

            if ($isAdminRole && !$isSuperAdmin) {
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
            $user->mahasiswaProfile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'nim' => $this->nullableString($data['nim'] ?? null),
                    'program_studi_id' => $this->nullableInt($data['prodi'] ?? ($data['program_studi_id'] ?? null)),
                    'angkatan' => $this->nullableInt($data['angkatan'] ?? null),
                    'is_active' => (bool) ($data['is_active'] ?? true),
                ],
            );

            return;
        }

        if ($role === AppRole::Dosen->value) {
            $user->dosenProfile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'nik' => $this->nullableString($data['nik'] ?? ($data['nidn'] ?? null)),
                    'program_studi_id' => $this->nullableInt($data['prodi'] ?? ($data['program_studi_id'] ?? null)),
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
