<?php

use App\Enums\AppRole;
use App\Models\DosenProgramStudiAssignment;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Services\LecturerSearchService;

/*
 * Production runs PostgreSQL, where a case-sensitive LIKE would miss "budi"
 * for "Budi". SQLite passes either way, so tests/Unit/CaseInsensitiveSearchTest.php
 * is the real guard against a regression to 'like'.
 */

it('finds a lecturer for the student search regardless of letter case', function (string $term): void {
    $programStudi = ProgramStudi::factory()->create(['name' => 'Informatika']);

    $student = createUserWithRole(AppRole::Mahasiswa->value);
    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $programStudi->id,
        'is_active' => true,
    ]);

    $lecturer = createUserWithRole(AppRole::Dosen->value);
    $lecturer->update(['name' => 'Dr. Budi Santoso, M.Kom.']);
    $lecturer->dosenProfile()->update([
        'program_studi_id' => $programStudi->id,
        'is_active' => true,
    ]);
    DosenProgramStudiAssignment::query()->updateOrCreate(
        [
            'user_id' => $lecturer->id,
            'program_studi_id' => $programStudi->id,
            'concentration' => ProgramStudi::DEFAULT_GENERAL_CONCENTRATION,
        ],
        [
            'is_primary' => true,
            'is_active' => true,
        ],
    );

    $ids = app(LecturerSearchService::class)
        ->searchForStudent($student, $term)
        ->pluck('id')
        ->all();

    expect($ids)->toBe([$lecturer->id]);
})->with(['Budi', 'budi', 'BUDI', 'Budi Santoso', 'budi santoso']);
