<?php

use App\Models\Faculty;
use App\Models\ProgramStudi;
use Database\Seeders\AcademicStructureSeeder;
use Database\Seeders\UserSeeder;

test('academic structure seeder creates the agreed faculties program studies and degree levels', function (): void {
    $this->seed(AcademicStructureSeeder::class);
    $this->seed(AcademicStructureSeeder::class);

    $expected = [
        'ilkom' => ['Fakultas Teknik', ['s1']],
        'ti' => ['Fakultas Teknik', ['s1']],
        'rpl' => ['Fakultas Teknik', ['d3', 's1']],
        'tpangan' => ['Fakultas Teknik', ['s1']],
        'si' => ['Fakultas Teknik', ['d3']],
        'sasingg' => ['Fakultas Humaniora, Hukum dan Pariwisata', ['s1']],
        'pariwisata' => ['Fakultas Humaniora, Hukum dan Pariwisata', ['s1']],
        'hukum' => ['Fakultas Humaniora, Hukum dan Pariwisata', ['s1']],
        'kedokteran' => ['Fakultas Kedokteran', ['s1']],
        'profesi-pendidikan-kedokteran' => ['Fakultas Kedokteran', ['s1']],
        'gizi' => ['Fakultas Kesehatan', ['s1']],
        'farmasi' => ['Fakultas Kesehatan', ['s1']],
        'manajemen' => ['Fakultas Ekonomi & Bisnis', ['s1']],
        'akuntansi' => ['Fakultas Ekonomi & Bisnis', ['s1']],
        'bisdig' => ['Fakultas Ekonomi & Bisnis', ['s1']],
        'dkv' => ['Fakultas Seni & Desain', ['s1']],
        'sp' => ['Fakultas Seni & Desain', ['s1']],
        'pti' => ['Fakultas Pendidikan', ['s1']],
        'pko' => ['Fakultas Pendidikan', ['s1']],
        's2-ilmu-komputer' => ['Program Pascasarjana', ['s2']],
        's2-sastra-inggris' => ['Program Pascasarjana', ['s2']],
    ];

    expect(Faculty::query()->where('is_placeholder', false)->count())->toBe(8)
        ->and(ProgramStudi::query()->count())->toBe(21);

    foreach ($expected as $slug => [$facultyName, $degreeLevels]) {
        $programStudi = ProgramStudi::query()
            ->with('faculty')
            ->where('slug', $slug)
            ->firstOrFail();

        expect($programStudi->faculty?->name)->toBe($facultyName)
            ->and($programStudi->degreeLevelList())->toBe($degreeLevels);
    }
});

test('default user seeder consumes the centralized structure and assigns valid student degrees', function (): void {
    $this->seed(UserSeeder::class);

    $sistemInformasi = ProgramStudi::query()->where('slug', 'si')->firstOrFail();
    $rpl = ProgramStudi::query()->where('slug', 'rpl')->firstOrFail();

    expect(ProgramStudi::query()->count())->toBe(21)
        ->and($sistemInformasi->mahasiswaProfiles()->where('degree_level', 'd3')->count())->toBeGreaterThan(0)
        ->and($sistemInformasi->mahasiswaProfiles()->where('degree_level', '!=', 'd3')->count())->toBe(0)
        ->and($rpl->mahasiswaProfiles()->where('degree_level', 's1')->count())->toBeGreaterThan(0);
});
