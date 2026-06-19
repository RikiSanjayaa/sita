<?php

use App\Models\ExpertiseField;
use App\Models\User;
use Database\Seeders\ExpertiseFieldSeeder;
use Database\Seeders\UserSeeder;

test('expertise field seeder creates a comprehensive and idempotent master catalogue', function (): void {
    $this->seed(ExpertiseFieldSeeder::class);
    $this->seed(ExpertiseFieldSeeder::class);

    expect(ExpertiseField::query()->count())->toBe(49)
        ->and(ExpertiseField::query()->where('name', 'Jaringan Komputer')->exists())->toBeTrue()
        ->and(ExpertiseField::query()->where('name', 'Teknologi Pengolahan Pangan')->exists())->toBeTrue()
        ->and(ExpertiseField::query()->where('name', 'Hukum Perdata')->exists())->toBeTrue()
        ->and(ExpertiseField::query()->where('name', 'Gizi Klinis')->exists())->toBeTrue()
        ->and(ExpertiseField::query()->where('name', 'Akuntansi Keuangan')->exists())->toBeTrue()
        ->and(ExpertiseField::query()->where('name', 'Metodologi Penelitian Komputasi')->exists())->toBeTrue()
        ->and(ExpertiseField::query()->where('name', 'Kajian Sastra Lanjut')->exists())->toBeTrue();
});

test('default user seeder assigns expertise by lecturer program and concentration', function (): void {
    $this->seed(UserSeeder::class);

    $networkLecturer = User::query()->where('email', 'dosen@sita.test')->firstOrFail();
    $intelligentSystemsLecturer = User::query()->where('email', 'dosen2@sita.test')->firstOrFail();
    $technologyInformationLecturer = User::query()
        ->whereHas('dosenProfile.programStudi', fn($query) => $query->where('slug', 'ti'))
        ->firstOrFail();

    expect($networkLecturer->expertiseFields()->pluck('name')->all())
        ->toEqualCanonicalizing(['Algoritma dan Pemrograman', 'Jaringan Komputer', 'Keamanan Siber'])
        ->and($intelligentSystemsLecturer->expertiseFields()->pluck('name')->all())
        ->toEqualCanonicalizing(['Algoritma dan Pemrograman', 'Kecerdasan Buatan', 'Pembelajaran Mesin'])
        ->and($technologyInformationLecturer->expertiseFields()->pluck('name')->all())
        ->toEqualCanonicalizing(['Infrastruktur Teknologi Informasi', 'Tata Kelola Teknologi Informasi']);

    $assignmentCount = $networkLecturer->expertiseFields()->count();

    $this->seed(ExpertiseFieldSeeder::class);

    expect($networkLecturer->expertiseFields()->count())->toBe($assignmentCount);
});
