<?php

namespace Database\Seeders;

use App\Enums\AppRole;
use App\Models\AdminProfile;
use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * All 17 program studi with their slugs (used for email generation).
     *
     * @var array<int, array{name: string, slug: string}>
     */
    private const PRODI_LIST = [
        ['name' => 'Ilmu Komputer', 'slug' => 'ilkom'],
        ['name' => 'Teknologi Informasi', 'slug' => 'ti'],
        ['name' => 'Rekayasa Perangkat Lunak', 'slug' => 'rpl'],
        ['name' => 'Sistem Informasi', 'slug' => 'si'],
        ['name' => 'Teknologi Pangan', 'slug' => 'tpangan'],
        ['name' => 'Desain Komunikasi Visual', 'slug' => 'dkv'],
        ['name' => 'Seni Pertunjukan', 'slug' => 'sp'],
        ['name' => 'Gizi', 'slug' => 'gizi'],
        ['name' => 'Farmasi', 'slug' => 'farmasi'],
        ['name' => 'Sastra Inggris', 'slug' => 'sasingg'],
        ['name' => 'Pariwisata', 'slug' => 'pariwisata'],
        ['name' => 'Hukum', 'slug' => 'hukum'],
        ['name' => 'Manajemen', 'slug' => 'manajemen'],
        ['name' => 'Akuntansi', 'slug' => 'akuntansi'],
        ['name' => 'Bisnis Digital', 'slug' => 'bisdig'],
        ['name' => 'Pendidikan Teknologi Informasi', 'slug' => 'pti'],
        ['name' => 'Pendidikan Kepelatihan Olahraga', 'slug' => 'pko'],
    ];

    /**
     * Known test accounts for Ilmu Komputer (kept for backward compatibility).
     *
     * @var array<int, array{name: string, email: string, nim: string, angkatan: int}>
     */
    private const ILKOM_MAHASISWA = [
        ['name' => 'Mahasiswa SiTA', 'email' => 'mahasiswa@sita.test', 'nim' => '2210510999', 'angkatan' => 2022],
        ['name' => 'Muhammad Akbar', 'email' => 'akbar@sita.test', 'nim' => '2210510001', 'angkatan' => 2022],
        ['name' => 'Nadia Putri', 'email' => 'nadia@sita.test', 'nim' => '2210510020', 'angkatan' => 2022],
        ['name' => 'Rizky Pratama', 'email' => 'rizky@sita.test', 'nim' => '2210510011', 'angkatan' => 2022],
        ['name' => 'Siti Aminah', 'email' => 'siti@sita.test', 'nim' => '2210510030', 'angkatan' => 2022],
        ['name' => 'Farhan Maulana', 'email' => 'farhan@sita.test', 'nim' => '2210510041', 'angkatan' => 2022],
        ['name' => 'Bagas Saputra', 'email' => 'bagas@sita.test', 'nim' => '2210510052', 'angkatan' => 2022],
        ['name' => 'Laila Rahma', 'email' => 'laila@sita.test', 'nim' => '2210510063', 'angkatan' => 2022],
        ['name' => 'Putra Mahendra', 'email' => 'putra@sita.test', 'nim' => '2210510074', 'angkatan' => 2022],
    ];

    /**
     * Known test dosen accounts for Ilmu Komputer (kept for backward compatibility).
     *
     * @var array<int, array{name: string, email: string, nik: string}>
     */
    private const ILKOM_DOSEN = [
        ['name' => 'Dr. Budi Santoso, M.Kom.', 'email' => 'dosen@sita.test', 'nik' => '7301010101010001'],
        ['name' => 'Dr. Ratna Kusuma, M.Kom.', 'email' => 'dosen2@sita.test', 'nik' => '7301010101010002'],
        ['name' => 'Prof. Ahmad Hidayat, Ph.D.', 'email' => 'dosen3@sita.test', 'nik' => '7301010101010003'],
        ['name' => 'Dr. Dewi Lestari, M.T.', 'email' => 'dosen4@sita.test', 'nik' => '7301010101010004'],
    ];

    /**
     * Generated dosen names per prodi (for non-Ilkom prodi).
     * 2 dosen per prodi.
     *
     * @var array<string, array<int, array{name: string, title_prefix: string, title_suffix: string}>>
     */
    private const PRODI_DOSEN = [
        'ti' => [
            ['name' => 'Agus Setiawan', 'title_prefix' => 'Dr.', 'title_suffix' => 'M.Kom.'],
            ['name' => 'Rina Fitriani', 'title_prefix' => 'Dr.', 'title_suffix' => 'M.T.'],
        ],
        'rpl' => [
            ['name' => 'Hendra Pratama', 'title_prefix' => 'Dr.', 'title_suffix' => 'M.Kom.'],
            ['name' => 'Sri Wahyuni', 'title_prefix' => '', 'title_suffix' => 'M.Kom.'],
        ],
        'si' => [
            ['name' => 'Bambang Irawan', 'title_prefix' => 'Dr.', 'title_suffix' => 'M.SI.'],
            ['name' => 'Ani Rahmawati', 'title_prefix' => 'Dr.', 'title_suffix' => 'M.Kom.'],
        ],
        'tpangan' => [
            ['name' => 'Eko Prasetyo', 'title_prefix' => 'Dr.', 'title_suffix' => 'M.Si.'],
            ['name' => 'Yuni Kartika', 'title_prefix' => 'Prof.', 'title_suffix' => 'Ph.D.'],
        ],
        'dkv' => [
            ['name' => 'Arif Wicaksono', 'title_prefix' => '', 'title_suffix' => 'M.Ds.'],
            ['name' => 'Maya Sari', 'title_prefix' => 'Dr.', 'title_suffix' => 'M.Sn.'],
        ],
        'sp' => [
            ['name' => 'Didik Nugroho', 'title_prefix' => 'Dr.', 'title_suffix' => 'M.Sn.'],
            ['name' => 'Lina Marlina', 'title_prefix' => '', 'title_suffix' => 'M.Sn.'],
        ],
        'gizi' => [
            ['name' => 'Nurul Hidayah', 'title_prefix' => 'Dr.', 'title_suffix' => 'M.Gizi'],
            ['name' => 'Fajar Kurniawan', 'title_prefix' => '', 'title_suffix' => 'M.Kes.'],
        ],
        'farmasi' => [
            ['name' => 'Putri Wulandari', 'title_prefix' => 'Dr.', 'title_suffix' => 'M.Farm.'],
            ['name' => 'Awan Setiadi', 'title_prefix' => 'Prof.', 'title_suffix' => 'Ph.D.'],
        ],
        'sasingg' => [
            ['name' => 'Diana Permata', 'title_prefix' => 'Dr.', 'title_suffix' => 'M.Hum.'],
            ['name' => 'Rudi Hartono', 'title_prefix' => '', 'title_suffix' => 'M.A.'],
        ],
        'pariwisata' => [
            ['name' => 'Indra Gunawan', 'title_prefix' => 'Dr.', 'title_suffix' => 'M.Par.'],
            ['name' => 'Sinta Dewi', 'title_prefix' => '', 'title_suffix' => 'M.M.'],
        ],
        'hukum' => [
            ['name' => 'Teguh Prabowo', 'title_prefix' => 'Dr.', 'title_suffix' => 'S.H., M.H.'],
            ['name' => 'Wati Susilowati', 'title_prefix' => 'Prof.', 'title_suffix' => 'S.H., M.H.'],
        ],
        'manajemen' => [
            ['name' => 'Bimo Adi', 'title_prefix' => 'Dr.', 'title_suffix' => 'M.M.'],
            ['name' => 'Citra Ayu', 'title_prefix' => '', 'title_suffix' => 'M.M.'],
        ],
        'akuntansi' => [
            ['name' => 'Doni Saputra', 'title_prefix' => 'Dr.', 'title_suffix' => 'M.Ak.'],
            ['name' => 'Eva Nuraini', 'title_prefix' => '', 'title_suffix' => 'M.Ak.'],
        ],
        'bisdig' => [
            ['name' => 'Gilang Ramadhan', 'title_prefix' => 'Dr.', 'title_suffix' => 'M.M.'],
            ['name' => 'Hesti Permatasari', 'title_prefix' => '', 'title_suffix' => 'M.Kom.'],
        ],
        'pti' => [
            ['name' => 'Irfan Hakim', 'title_prefix' => 'Dr.', 'title_suffix' => 'M.Pd.'],
            ['name' => 'Jamilah Azizah', 'title_prefix' => '', 'title_suffix' => 'M.Pd.'],
        ],
        'pko' => [
            ['name' => 'Kurniawan Adi', 'title_prefix' => 'Dr.', 'title_suffix' => 'M.Pd.'],
            ['name' => 'Linda Susanti', 'title_prefix' => '', 'title_suffix' => 'M.Or.'],
        ],
    ];

    /**
     * Indonesian first names for generating mahasiswa accounts.
     *
     * @var array<int, string>
     */
    private const FIRST_NAMES = [
        'Adi',
        'Bayu',
        'Candra',
        'Dewi',
        'Eka',
        'Farid',
        'Gita',
        'Hana',
        'Imam',
        'Juni',
        'Kartika',
        'Luthfi',
        'Mega',
        'Nanda',
        'Oki',
        'Putri',
        'Qori',
        'Rani',
        'Surya',
        'Tari',
        'Umar',
        'Vina',
        'Wawan',
        'Yusuf',
        'Zahra',
        'Anisa',
        'Dimas',
        'Fandi',
        'Hafiz',
        'Intan',
    ];

    /**
     * Indonesian last names for generating mahasiswa accounts.
     *
     * @var array<int, string>
     */
    private const LAST_NAMES = [
        'Wijaya',
        'Saputra',
        'Kusuma',
        'Permata',
        'Santoso',
        'Lestari',
        'Nugroho',
        'Hidayat',
        'Prasetyo',
        'Rahmawati',
        'Wicaksono',
        'Haryanto',
        'Susanto',
        'Purnama',
        'Cahyani',
        'Utami',
        'Siregar',
        'Pangestu',
        'Mahendra',
        'Anggraeni',
    ];

    /** @var int Counter for generating unique NIK */
    private int $nikCounter = 100;

    /** @var int Counter for generating unique NIM */
    private int $nimCounter = 1000;

    public function run(): void
    {
        $roles = $this->seedRoles();

        // 1. Seed all 17 Program Studi
        $prodiModels = $this->seedProgramStudis();

        // 2. Super Admin
        $this->upsertSuperAdmin($roles[AppRole::SuperAdmin->value]);

        // 3. Ilmu Komputer — known test accounts (backward compatible)
        $this->upsertIlkomAccounts($roles, $prodiModels['ilkom']);

        // 4. All other prodi (index 1..16)
        foreach (array_slice(self::PRODI_LIST, 1) as $prodiIndex => $prodi) {
            $this->seedProdi($prodi, $prodiIndex + 1, $roles, $prodiModels[$prodi['slug']]);
        }
    }

    /**
     * @return array<string, Role>
     */
    private function seedRoles(): array
    {
        return collect(AppRole::values())
            ->mapWithKeys(fn(string $role): array => [
                $role => Role::query()->firstOrCreate(['name' => $role]),
            ])
            ->all();
    }

    /**
     * @return array<string, \App\Models\ProgramStudi>
     */
    private function seedProgramStudis(): array
    {
        $models = [];
        foreach (self::PRODI_LIST as $prodi) {
            $models[$prodi['slug']] = \App\Models\ProgramStudi::query()->updateOrCreate(
                ['slug' => $prodi['slug']],
                ['name' => $prodi['name']],
            );
        }

        return $models;
    }

    /**
     * @param  array<string, Role>  $roles
     */
    private function upsertSuperAdmin(Role $superAdminRole): void
    {
        $user = User::query()->updateOrCreate([
            'email' => 'superadmin@sita.test',
        ], [
            'name' => 'Super Admin SiTA',
            'password' => Hash::make('password'),
            'last_active_role' => AppRole::SuperAdmin->value,
        ]);

        $user->roles()->syncWithoutDetaching([$superAdminRole->id]);
    }

    /**
     * Seed Ilmu Komputer prodi with known test accounts.
     *
     * @param  array<string, Role>  $roles
     */
    private function upsertIlkomAccounts(array $roles, \App\Models\ProgramStudi $prodi): void
    {
        $adminRole = $roles[AppRole::Admin->value];
        $dosenRole = $roles[AppRole::Dosen->value];
        $mahasiswaRole = $roles[AppRole::Mahasiswa->value];

        // Admin for Ilmu Komputer
        $admin = User::query()->updateOrCreate([
            'email' => 'admin@sita.test',
        ], [
            'name' => 'Admin Ilmu Komputer',
            'password' => Hash::make('password'),
            'last_active_role' => AppRole::Admin->value,
        ]);
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);
        AdminProfile::query()->updateOrCreate(
            ['user_id' => $admin->id],
            ['program_studi_id' => $prodi->id],
        );

        // Known dosen accounts
        foreach (self::ILKOM_DOSEN as $account) {
            $dosen = User::query()->updateOrCreate([
                'email' => $account['email'],
            ], [
                'name' => $account['name'],
                'password' => Hash::make('password'),
                'last_active_role' => AppRole::Dosen->value,
            ]);
            $dosen->roles()->syncWithoutDetaching([$dosenRole->id]);
            DosenProfile::query()->updateOrCreate(
                ['user_id' => $dosen->id],
                ['nik' => $account['nik'], 'program_studi_id' => $prodi->id, 'is_active' => true],
            );
        }

        // Known mahasiswa accounts
        foreach (self::ILKOM_MAHASISWA as $account) {
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
                ['nim' => (string) $account['nim'], 'program_studi_id' => $prodi->id, 'angkatan' => (int) $account['angkatan'], 'is_active' => true],
            );
        }

        // Additional random students for Ilmu Komputer
        $this->seedRandomMahasiswa($mahasiswaRole, $prodi, 0, 4);
    }

    /**
     * Seed a non-Ilkom prodi with admin, dosen, and mahasiswa.
     *
     * @param  array{name: string, slug: string}  $prodiData
     * @param  array<string, Role>  $roles
     */
    private function seedProdi(array $prodiData, int $prodiIndex, array $roles, \App\Models\ProgramStudi $prodi): void
    {
        $slug = $prodiData['slug'];

        // Admin
        $admin = User::query()->updateOrCreate([
            'email' => "admin.{$slug}@sita.test",
        ], [
            'name' => "Admin {$prodiData['name']}",
            'password' => Hash::make('password'),
            'last_active_role' => AppRole::Admin->value,
        ]);
        $admin->roles()->syncWithoutDetaching([$roles[AppRole::Admin->value]->id]);
        AdminProfile::query()->updateOrCreate(
            ['user_id' => $admin->id],
            ['program_studi_id' => $prodi->id],
        );

        // Dosen (2 per prodi)
        $dosenData = self::PRODI_DOSEN[$slug] ?? [];
        foreach ($dosenData as $dosenIndex => $d) {
            $fullName = trim("{$d['title_prefix']} {$d['name']}, {$d['title_suffix']}");
            $emailSlug = strtolower(str_replace(' ', '.', $d['name']));
            $nik = '73010101'.str_pad((string) ($this->nikCounter++), 8, '0', STR_PAD_LEFT);

            $dosen = User::query()->updateOrCreate([
                'email' => "{$emailSlug}@sita.test",
            ], [
                'name' => $fullName,
                'password' => Hash::make('password'),
                'last_active_role' => AppRole::Dosen->value,
            ]);
            $dosen->roles()->syncWithoutDetaching([$roles[AppRole::Dosen->value]->id]);
            DosenProfile::query()->updateOrCreate(
                ['user_id' => $dosen->id],
                ['nik' => $nik, 'program_studi_id' => $prodi->id, 'is_active' => true],
            );
        }

        // Mahasiswa (3 per prodi)
        $this->seedRandomMahasiswa($roles[AppRole::Mahasiswa->value], $prodi, $prodiIndex, 3);
    }

    /**
     * Generate random mahasiswa accounts for a prodi.
     */
    private function seedRandomMahasiswa(Role $mahasiswaRole, \App\Models\ProgramStudi $prodi, int $prodiIndex, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $firstName = self::FIRST_NAMES[($prodiIndex * $count + $i) % count(self::FIRST_NAMES)];
            $lastName = self::LAST_NAMES[($prodiIndex * $count + $i) % count(self::LAST_NAMES)];
            $name = "{$firstName} {$lastName}";
            $emailSlug = strtolower("{$firstName}.{$lastName}");
            $nim = '22'.str_pad((string) ($this->nimCounter++), 8, '0', STR_PAD_LEFT);

            $mahasiswa = User::query()->updateOrCreate([
                'email' => "{$emailSlug}@sita.test",
            ], [
                'name' => $name,
                'password' => Hash::make('password'),
                'last_active_role' => AppRole::Mahasiswa->value,
            ]);
            $mahasiswa->roles()->syncWithoutDetaching([$mahasiswaRole->id]);
            MahasiswaProfile::query()->updateOrCreate(
                ['user_id' => $mahasiswa->id],
                ['nim' => $nim, 'program_studi_id' => $prodi->id, 'angkatan' => 2022, 'is_active' => true],
            );
        }
    }
}
