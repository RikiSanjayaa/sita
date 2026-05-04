<?php

use App\Enums\AppRole;
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
});
