<?php

namespace Database\Seeders;

use App\Models\Faculty;
use App\Models\ProgramStudi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AcademicStructureSeeder extends Seeder
{
    /**
     * Single source of truth for faculties and their program studies.
     *
     * @var array<int, array{
     *     name: string,
     *     code: string,
     *     slug: string,
     *     program_studis: array<int, array{name: string, slug: string, degree_levels: array<int, string>}>
     * }>
     */
    public const FACULTIES = [
        [
            'name' => 'Fakultas Teknik',
            'code' => 'FT',
            'slug' => 'fakultas-teknik',
            'program_studis' => [
                ['name' => 'Ilmu Komputer', 'slug' => 'ilkom', 'degree_levels' => ['s1']],
                ['name' => 'Teknologi Informasi', 'slug' => 'ti', 'degree_levels' => ['s1']],
                ['name' => 'Rekayasa Perangkat Lunak', 'slug' => 'rpl', 'degree_levels' => ['d3', 's1']],
                ['name' => 'Teknologi Pangan', 'slug' => 'tpangan', 'degree_levels' => ['s1']],
                ['name' => 'Sistem Informasi', 'slug' => 'si', 'degree_levels' => ['d3']],
            ],
        ],
        [
            'name' => 'Fakultas Humaniora, Hukum dan Pariwisata',
            'code' => 'FHHP',
            'slug' => 'fakultas-humaniora-hukum-pariwisata',
            'program_studis' => [
                ['name' => 'Sastra Inggris', 'slug' => 'sasingg', 'degree_levels' => ['s1']],
                ['name' => 'Pariwisata', 'slug' => 'pariwisata', 'degree_levels' => ['s1']],
                ['name' => 'Hukum', 'slug' => 'hukum', 'degree_levels' => ['s1']],
            ],
        ],
        [
            'name' => 'Fakultas Kedokteran',
            'code' => 'FK',
            'slug' => 'fakultas-kedokteran',
            'program_studis' => [
                ['name' => 'Kedokteran', 'slug' => 'kedokteran', 'degree_levels' => ['s1']],
                ['name' => 'Profesi Pendidikan Kedokteran', 'slug' => 'profesi-pendidikan-kedokteran', 'degree_levels' => ['s1']],
            ],
        ],
        [
            'name' => 'Fakultas Kesehatan',
            'code' => 'FKES',
            'slug' => 'fakultas-kesehatan',
            'program_studis' => [
                ['name' => 'Gizi', 'slug' => 'gizi', 'degree_levels' => ['s1']],
                ['name' => 'Farmasi', 'slug' => 'farmasi', 'degree_levels' => ['s1']],
            ],
        ],
        [
            'name' => 'Fakultas Ekonomi & Bisnis',
            'code' => 'FEB',
            'slug' => 'fakultas-ekonomi-bisnis',
            'program_studis' => [
                ['name' => 'Manajemen', 'slug' => 'manajemen', 'degree_levels' => ['s1']],
                ['name' => 'Akuntansi', 'slug' => 'akuntansi', 'degree_levels' => ['s1']],
                ['name' => 'Bisnis Digital', 'slug' => 'bisdig', 'degree_levels' => ['s1']],
            ],
        ],
        [
            'name' => 'Fakultas Seni & Desain',
            'code' => 'FSD',
            'slug' => 'fakultas-seni-desain',
            'program_studis' => [
                ['name' => 'Desain Komunikasi Visual', 'slug' => 'dkv', 'degree_levels' => ['s1']],
                ['name' => 'Seni Pertunjukan', 'slug' => 'sp', 'degree_levels' => ['s1']],
            ],
        ],
        [
            'name' => 'Fakultas Pendidikan',
            'code' => 'FP',
            'slug' => 'fakultas-pendidikan',
            'program_studis' => [
                ['name' => 'Pendidikan Teknologi Informasi', 'slug' => 'pti', 'degree_levels' => ['s1']],
                ['name' => 'Pendidikan Kepelatihan Olahraga', 'slug' => 'pko', 'degree_levels' => ['s1']],
            ],
        ],
        [
            'name' => 'Program Pascasarjana',
            'code' => 'PPS',
            'slug' => 'program-pascasarjana',
            'program_studis' => [
                ['name' => 'Ilmu Komputer', 'slug' => 's2-ilmu-komputer', 'degree_levels' => ['s2']],
                ['name' => 'Sastra Inggris', 'slug' => 's2-sastra-inggris', 'degree_levels' => ['s2']],
            ],
        ],
    ];

    /**
     * Preserve the established default-account seed order.
     *
     * @var array<int, string>
     */
    private const DEFAULT_USER_PROGRAM_SLUGS = [
        'ilkom',
        'ti',
        'rpl',
        'si',
        'tpangan',
        'dkv',
        'sp',
        'gizi',
        'farmasi',
        'sasingg',
        'pariwisata',
        'hukum',
        'manajemen',
        'akuntansi',
        'bisdig',
        'pti',
        'pko',
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (self::FACULTIES as $facultyData) {
                $faculty = Faculty::query()->updateOrCreate(
                    ['slug' => $facultyData['slug']],
                    [
                        'name' => $facultyData['name'],
                        'code' => $facultyData['code'],
                        'is_active' => true,
                        'is_placeholder' => false,
                    ],
                );

                foreach ($facultyData['program_studis'] as $programStudiData) {
                    ProgramStudi::query()->updateOrCreate(
                        ['slug' => $programStudiData['slug']],
                        [
                            'faculty_id' => $faculty->id,
                            'name' => $programStudiData['name'],
                            'degree_levels' => $programStudiData['degree_levels'],
                            'concentrations' => ProgramStudi::defaultConcentrationsForSlug($programStudiData['slug']),
                        ],
                    );
                }
            }
        });
    }

    /**
     * @return array<int, array{name: string, slug: string, degree_levels: array<int, string>}>
     */
    public static function defaultUserProgramStudis(): array
    {
        $programStudis = collect(self::FACULTIES)
            ->flatMap(static fn(array $faculty): array => $faculty['program_studis'])
            ->keyBy('slug');

        return collect(self::DEFAULT_USER_PROGRAM_SLUGS)
            ->map(static fn(string $slug): array => $programStudis->get($slug))
            ->all();
    }
}
