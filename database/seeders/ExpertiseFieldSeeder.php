<?php

namespace Database\Seeders;

use App\Enums\AppRole;
use App\Models\DosenProfile;
use App\Models\ExpertiseField;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ExpertiseFieldSeeder extends Seeder
{
    /**
     * Expertise is global master data. This map determines which fields are
     * relevant to seeded lecturers based on their program and concentration.
     *
     * @var array<string, array{
     *     general: array<int, string>,
     *     concentrations?: array<string, array<int, string>>
     * }>
     */
    public const PROGRAM_EXPERTISE = [
        'ilkom' => [
            'general' => ['Algoritma dan Pemrograman'],
            'concentrations' => [
                'Jaringan' => ['Jaringan Komputer', 'Keamanan Siber'],
                'Sistem Cerdas' => ['Kecerdasan Buatan', 'Pembelajaran Mesin'],
                'Computer Vision' => ['Computer Vision', 'Pengolahan Citra'],
            ],
        ],
        'ti' => [
            'general' => ['Infrastruktur Teknologi Informasi', 'Tata Kelola Teknologi Informasi'],
        ],
        'rpl' => [
            'general' => ['Rekayasa Perangkat Lunak', 'Pengujian Perangkat Lunak'],
        ],
        'tpangan' => [
            'general' => ['Teknologi Pengolahan Pangan', 'Keamanan dan Mutu Pangan'],
        ],
        'si' => [
            'general' => ['Sistem Informasi', 'Analitik Bisnis'],
        ],
        'sasingg' => [
            'general' => ['Linguistik', 'Sastra dan Kajian Budaya', 'Penerjemahan'],
        ],
        'pariwisata' => [
            'general' => ['Manajemen Pariwisata', 'Perhotelan'],
        ],
        'hukum' => [
            'general' => ['Hukum Perdata', 'Hukum Pidana'],
        ],
        'kedokteran' => [
            'general' => ['Ilmu Kedokteran', 'Ilmu Biomedik'],
        ],
        'profesi-pendidikan-kedokteran' => [
            'general' => ['Pendidikan Kedokteran', 'Pendidikan Klinis'],
        ],
        'gizi' => [
            'general' => ['Gizi Klinis', 'Gizi Masyarakat'],
        ],
        'farmasi' => [
            'general' => ['Farmakologi', 'Teknologi Farmasi'],
        ],
        'manajemen' => [
            'general' => ['Manajemen Strategis', 'Manajemen Sumber Daya Manusia'],
        ],
        'akuntansi' => [
            'general' => ['Akuntansi Keuangan', 'Audit'],
        ],
        'bisdig' => [
            'general' => ['Bisnis Digital', 'Pemasaran Digital'],
        ],
        'dkv' => [
            'general' => ['Desain Komunikasi Visual', 'Multimedia'],
        ],
        'sp' => [
            'general' => ['Seni Pertunjukan', 'Kajian Seni'],
        ],
        'pti' => [
            'general' => ['Teknologi Pendidikan', 'Media Pembelajaran'],
        ],
        'pko' => [
            'general' => ['Kepelatihan Olahraga', 'Ilmu Keolahragaan'],
        ],
        's2-ilmu-komputer' => [
            'general' => ['Metodologi Penelitian Komputasi', 'Sains Data'],
        ],
        's2-sastra-inggris' => [
            'general' => ['Linguistik Terapan', 'Kajian Sastra Lanjut', 'Metodologi Penelitian Humaniora'],
        ],
    ];

    public function run(): void
    {
        $fields = collect(self::PROGRAM_EXPERTISE)
            ->flatMap(function (array $configuration): array {
                return [
                    ...$configuration['general'],
                    ...collect($configuration['concentrations'] ?? [])->flatten()->all(),
                ];
            })
            ->unique()
            ->mapWithKeys(function (string $name): array {
                $field = ExpertiseField::query()->updateOrCreate(
                    ['slug' => Str::slug($name)],
                    [
                        'name' => $name,
                        'description' => "Keahlian pada bidang {$name}.",
                        'is_active' => true,
                    ],
                );

                return [$name => $field];
            });

        $assignedBy = User::query()
            ->whereHas('roles', fn($query) => $query->where('name', AppRole::SuperAdmin->value))
            ->orderBy('id')
            ->first();

        DosenProfile::query()
            ->with(['programStudi', 'user'])
            ->get()
            ->each(function (DosenProfile $profile) use ($assignedBy, $fields): void {
                $programSlug = $profile->programStudi?->slug;
                $configuration = is_string($programSlug)
                    ? self::PROGRAM_EXPERTISE[$programSlug] ?? null
                    : null;

                if ($configuration === null || $profile->user === null) {
                    return;
                }

                $fieldNames = collect($configuration['general'])
                    ->merge($configuration['concentrations'][$profile->concentration] ?? [])
                    ->unique();

                $assignments = $fieldNames
                    ->mapWithKeys(function (string $fieldName) use ($assignedBy, $fields): array {
                        /** @var ExpertiseField $field */
                        $field = $fields->get($fieldName);

                        return [
                            $field->id => ['assigned_by_user_id' => $assignedBy?->id],
                        ];
                    })
                    ->all();

                $profile->user->expertiseFields()->syncWithoutDetaching($assignments);
            });
    }
}
