<?php

namespace Database\Seeders;

use App\Enums\AppRole;
use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    private const MAHASISWA_ACCOUNTS = [
        [
            'name' => 'Mahasiswa SiTA',
            'email' => 'mahasiswa@sita.test',
            'nim' => '2210510999',
            'angkatan' => 2022,
        ],
        [
            'name' => 'Muhammad Akbar',
            'email' => 'akbar@sita.test',
            'nim' => '2210510001',
            'angkatan' => 2022,
        ],
        [
            'name' => 'Nadia Putri',
            'email' => 'nadia@sita.test',
            'nim' => '2210510020',
            'angkatan' => 2022,
        ],
        [
            'name' => 'Rizky Pratama',
            'email' => 'rizky@sita.test',
            'nim' => '2210510011',
            'angkatan' => 2022,
        ],
    ];

    public function run(): void
    {
        $roles = $this->seedRoles();

        $this->upsertAdmin($roles[AppRole::Admin->value]);
        $this->upsertDosen($roles[AppRole::Dosen->value]);
        $this->upsertMahasiswaAccounts($roles[AppRole::Mahasiswa->value]);
    }

    private function seedRoles(): array
    {
        return collect(AppRole::values())
            ->mapWithKeys(fn (string $role): array => [
                $role => Role::query()->firstOrCreate(['name' => $role]),
            ])
            ->all();
    }

    private function upsertAdmin(Role $adminRole): void
    {
        $admin = User::query()->updateOrCreate([
            'email' => 'admin@sita.test',
        ], [
            'name' => 'Admin SiTA',
            'password' => Hash::make('password'),
            'last_active_role' => AppRole::Admin->value,
        ]);

        $admin->roles()->syncWithoutDetaching([$adminRole->id]);
    }

    private function upsertDosen(Role $dosenRole): void
    {
        $dosen = User::query()->updateOrCreate([
            'email' => 'dosen@sita.test',
        ], [
            'name' => 'Dr. Budi Santoso, M.Kom.',
            'password' => Hash::make('password'),
            'last_active_role' => AppRole::Dosen->value,
        ]);

        $dosen->roles()->syncWithoutDetaching([$dosenRole->id]);

        DosenProfile::query()->updateOrCreate(
            ['user_id' => $dosen->id],
            [
                'nidn' => '1234567890',
                'homebase' => 'Informatika',
                'is_active' => true,
            ],
        );
    }

    private function upsertMahasiswaAccounts(Role $mahasiswaRole): void
    {
        foreach (self::MAHASISWA_ACCOUNTS as $account) {
            $mahasiswa = User::query()->updateOrCreate([
                'email' => (string) $account['email'],
            ], [
                'name' => (string) $account['name'],
                'password' => Hash::make('password'),
                'last_active_role' => AppRole::Mahasiswa->value,
            ]);

            $mahasiswa->roles()->syncWithoutDetaching([$mahasiswaRole->id]);

            MahasiswaProfile::query()->updateOrCreate(
                ['user_id' => $mahasiswa->id],
                [
                    'nim' => (string) $account['nim'],
                    'program_studi' => 'Informatika',
                    'angkatan' => (int) $account['angkatan'],
                    'status_akademik' => 'aktif',
                ],
            );
        }
    }
}
