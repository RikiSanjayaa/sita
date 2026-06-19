<?php

use App\Enums\AppRole;
use App\Models\DosenProgramStudiAssignment;
use App\Models\ExpertiseField;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\User;
use App\Services\UserExcelImportService;
use Illuminate\Http\UploadedFile;

test('excel import service imports users from the xlsx template', function (): void {
    $superAdminRole = Role::query()->firstOrCreate(['name' => AppRole::SuperAdmin->value]);
    $superAdmin = User::factory()->asSuperAdmin()->create();
    $superAdmin->roles()->syncWithoutDetaching([$superAdminRole->id]);

    $programStudi = ProgramStudi::query()->create([
        'name' => 'Ilmu Komputer',
        'slug' => 'ilkom',
        'concentrations' => ['Jaringan', 'Sistem Cerdas', 'Computer Vision'],
    ]);
    $otherProgramStudi = ProgramStudi::query()->create([
        'name' => 'Teknologi Informasi',
        'slug' => 'ti',
        'concentrations' => ['Umum'],
    ]);
    ExpertiseField::factory()->create([
        'name' => 'Jaringan Komputer',
        'slug' => 'jaringan-komputer',
    ]);
    ExpertiseField::factory()->create([
        'name' => 'Keamanan Siber',
        'slug' => 'keamanan-siber',
    ]);

    $response = $this->actingAs($superAdmin)
        ->get(route('admin.users.import-template', ['format' => 'xlsx']))
        ->assertOk();

    $tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'user-import-template-test.xlsx';
    file_put_contents($tempPath, $response->getContent());

    $uploadedFile = new UploadedFile(
        $tempPath,
        'user-import-template.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );

    $result = app(UserExcelImportService::class)->import($uploadedFile, [
        'import_type' => AppRole::Mahasiswa->value,
        'program_studi_id' => $programStudi->id,
    ], $superAdmin);

    @unlink($tempPath);

    expect($result['processed'])->toBe(3)
        ->and($result['imported'])->toBe(3)
        ->and($result['failed'])->toBe(0)
        ->and(User::query()->where('email', 'akbar@sita.test')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'budi@sita.test')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'admin2@sita.test')->exists())->toBeTrue();

    $budi = User::query()->where('email', 'budi@sita.test')->firstOrFail();

    expect(DosenProgramStudiAssignment::query()->where('user_id', $budi->id)->count())->toBe(2)
        ->and($budi->teachesInProgramStudi($programStudi->id, 'Jaringan'))->toBeTrue()
        ->and($budi->teachesInProgramStudi($otherProgramStudi->id, 'Umum'))->toBeTrue()
        ->and($budi->expertiseFields()->pluck('name')->all())
        ->toEqualCanonicalizing(['Jaringan Komputer', 'Keamanan Siber']);

    $akbar = User::query()->where('email', 'akbar@sita.test')->firstOrFail();

    expect($akbar->mahasiswaProfile?->degree_level)->toBe('s1');
});
