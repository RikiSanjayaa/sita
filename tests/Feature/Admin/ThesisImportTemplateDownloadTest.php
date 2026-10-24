<?php

use App\Enums\AppRole;
use App\Models\Role;
use App\Models\User;
use App\Support\SimpleXlsxReader;

test('admin can download the thesis import template and it reads back with the expected header', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $admin->roles()->syncWithoutDetaching([Role::query()->firstOrCreate(['name' => AppRole::Admin->value])->id]);

    $response = $this->actingAs($admin)
        ->get(route('admin.thesis-projects.import-template'))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertHeader('content-disposition', 'attachment; filename="thesis-import-template.xlsx"');

    $path = tempnam(sys_get_temp_dir(), 'thesis-template-test-');
    file_put_contents($path, $response->getContent());
    $rows = app(SimpleXlsxReader::class)->rows($path);
    @unlink($path);

    expect($rows[0])->toBe(['nim', 'judul', 'pembimbing_1_nik', 'pembimbing_2_nik', 'tahap', 'tanggal_mulai_masa_aktif'])
        ->and($rows)->toHaveCount(3)
        ->and($rows[1][4])->toBe('Riset')
        ->and($rows[2][4])->toBe('Review Judul');
});

test('non-admin cannot download the thesis import template', function (): void {
    $mahasiswa = User::factory()->asMahasiswa()->create();
    $mahasiswa->roles()->syncWithoutDetaching([Role::query()->firstOrCreate(['name' => AppRole::Mahasiswa->value])->id]);

    $this->actingAs($mahasiswa)
        ->get(route('admin.thesis-projects.import-template'))
        ->assertForbidden();
});
