<?php

use App\Enums\AppRole;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\User;
use App\Services\UserExcelImportService;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

/**
 * @param  array<int, array<int, string>>  $rows
 */
function obeImportFile(array $rows): UploadedFile
{
    $sheetRows = collect($rows)
        ->map(fn(array $row, int $index): string => '<row r="'.($index + 1).'">'.collect($row)
            ->map(fn(string $value, int $column): string => '<c r="'.chr(65 + $column).($index + 1).'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1).'</t></is></c>')
            ->implode('').'</row>')
        ->implode('');

    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>';

    $path = tempnam(sys_get_temp_dir(), 'obe-import').'.xlsx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();

    return new UploadedFile($path, 'users.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

function obeImportProgramStudi(): ProgramStudi
{
    return ProgramStudi::factory()->create([
        'degree_levels' => ['s1'],
        'concentrations' => ['Umum'],
    ]);
}

function runObeImport(array $header, array $values, ProgramStudi $programStudi): array
{
    $actor = User::factory()->asSuperAdmin()->create();

    return app(UserExcelImportService::class)->import(
        obeImportFile([[...$header, 'role'], [...$values, AppRole::Mahasiswa->value]]),
        ['import_type' => AppRole::Mahasiswa->value, 'program_studi_id' => $programStudi->id],
        $actor,
    );
}

test('mahasiswa profiles default to non-OBE curriculum', function (): void {
    $profile = MahasiswaProfile::factory()->create();

    expect($profile->fresh()->is_obe_curriculum)->toBeFalse()
        ->and(MahasiswaProfile::factory()->obeCurriculum()->create()->is_obe_curriculum)->toBeTrue();
});

test('excel import parses kurikulum_obe values', function (string $value, bool $expected): void {
    $programStudi = obeImportProgramStudi();

    $result = runObeImport(
        ['nama', 'email', 'password', 'nim', 'angkatan', 'jenjang', 'konsentrasi', 'kurikulum_obe'],
        ['Mahasiswa Uji', 'obe-uji@sita.test', 'Rahasia123!', '2210510100', '2022', 's1', 'Umum', $value],
        $programStudi,
    );

    expect($result['imported'])->toBe(1)
        ->and($result['failures'])->toBe([])
        ->and(User::query()->where('email', 'obe-uji@sita.test')->firstOrFail()->mahasiswaProfile?->is_obe_curriculum)->toBe($expected);
})->with([
    'ya' => ['ya', true],
    'YA with spaces' => [' YA ', true],
    'one' => ['1', true],
    'true' => ['true', true],
    'tidak' => ['tidak', false],
    'zero' => ['0', false],
    'false' => ['False', false],
    'empty means non-OBE' => ['', false],
]);

test('excel import reports invalid kurikulum_obe value as a row error', function (): void {
    $programStudi = obeImportProgramStudi();

    $result = runObeImport(
        ['nama', 'email', 'password', 'nim', 'angkatan', 'jenjang', 'konsentrasi', 'kurikulum_obe'],
        ['Mahasiswa Salah', 'obe-salah@sita.test', 'Rahasia123!', '2210510101', '2022', 's1', 'Umum', 'mungkin'],
        $programStudi,
    );

    expect($result['imported'])->toBe(0)
        ->and($result['failed'])->toBe(1)
        ->and($result['failures'][0])->toContain('Baris 2')
        ->and($result['failures'][0])->toContain("Nilai kurikulum_obe 'mungkin' tidak valid")
        ->and(User::query()->where('email', 'obe-salah@sita.test')->exists())->toBeFalse();
});

test('excel import without kurikulum_obe column keeps existing flag', function (): void {
    $programStudi = obeImportProgramStudi();
    $existing = User::factory()->asMahasiswa()->create(['email' => 'obe-lama@sita.test']);
    MahasiswaProfile::factory()->obeCurriculum()->create([
        'user_id' => $existing->id,
        'program_studi_id' => $programStudi->id,
        'concentration' => 'Umum',
    ]);

    $result = runObeImport(
        ['nama', 'email', 'password', 'nim', 'angkatan', 'jenjang', 'konsentrasi'],
        ['Mahasiswa Lama', 'obe-lama@sita.test', 'Rahasia123!', '2210510102', '2022', 's1', 'Umum'],
        $programStudi,
    );

    expect($result['imported'])->toBe(1)
        ->and($existing->fresh()->mahasiswaProfile?->is_obe_curriculum)->toBeTrue();
});

test('super admin can toggle kurikulum OBE on a mahasiswa from the user form', function (): void {
    $superAdmin = User::factory()->asSuperAdmin()->create();
    $programStudi = obeImportProgramStudi();
    $student = User::factory()->asMahasiswa()->create();
    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $programStudi->id,
        'degree_level' => 's1',
        'concentration' => 'Umum',
    ]);

    $this->actingAs($superAdmin);

    Livewire::test(EditUser::class, ['record' => $student->getKey()])
        ->assertFormFieldExists('is_obe_curriculum')
        ->fillForm(['is_obe_curriculum' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($student->fresh()->mahasiswaProfile?->is_obe_curriculum)->toBeTrue();

    Livewire::test(EditUser::class, ['record' => $student->getKey()])
        ->fillForm(['is_obe_curriculum' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($student->fresh()->mahasiswaProfile?->is_obe_curriculum)->toBeFalse();
});
