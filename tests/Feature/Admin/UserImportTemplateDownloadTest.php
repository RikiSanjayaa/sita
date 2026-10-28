<?php

use App\Models\Role;
use App\Models\User;
use App\Support\SimpleXlsxReader;

it('downloads only the selected role columns, demo and guidance', function (string $kind, array $columns, array $guidance): void {
    $admin = User::factory()->asAdmin()->create();
    $response = $this->actingAs($admin)
        ->get(route('admin.users.import-template', ['kind' => $kind]))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertHeader('content-disposition', 'attachment; filename="user-import-'.$kind.'.xlsx"');

    $path = tempnam(sys_get_temp_dir(), 'role-template-');
    file_put_contents($path, $response->getContent());

    try {
        $rows = app(SimpleXlsxReader::class)->rows($path);
        expect($rows[0])->toBe(['nama', 'email', 'no_hp', 'role', 'password', ...$columns])
            ->and($rows[1][3])->toBe($kind)
            ->and($rows[1][1])->toBe("contoh-{$kind}@sita.test");
        $zip = new ZipArchive;
        $zip->open($path);
        $workbook = $zip->getFromName('xl/workbook.xml');
        $guide = $zip->getFromName('xl/worksheets/sheet2.xml');
        $zip->close();
        expect($workbook)->toContain('name="Template Import"', 'name="Panduan"');
        foreach ($guidance as $text) {
            expect($guide)->toContain($text);
        }
    } finally {
        unlink($path);
    }
})->with([
    'mahasiswa' => ['mahasiswa', ['nim', 'angkatan', 'jenjang', 'konsentrasi', 'kurikulum_obe'], ['Panduan import Mahasiswa', 'd3, s1, s2', 'ya/tidak, 1/0, true/false']],
    'dosen' => ['dosen', ['konsentrasi', 'penempatan_dosen', 'nik', 'kuota_bimbingan', 'bidang_keilmuan'], ['Panduan import Dosen', 'Penempatan pertama', 'Bidang keilmuan']],
    'admin' => ['admin', [], ['Panduan import Admin', 'Hanya super admin', 'Penempatan admin']],
]);

it('rejects unknown template kinds and unsupported formats', function (string $path): void {
    $this->actingAs(User::factory()->asAdmin()->create())->get($path)->assertNotFound();
})->with(['/admin/import-template/kaprodi.xlsx', '/admin/import-template/super_admin.xlsx', '/admin/import-template/mahasiswa.csv', '/admin/import-template.xlsx']);

it('forbids template downloads outside the admin roles', function (string $role, string $kind): void {
    $user = User::factory()->create();
    $user->roles()->sync([Role::query()->firstOrCreate(['name' => $role])->id]);
    $this->actingAs($user)->get(route('admin.users.import-template', ['kind' => $kind]))->assertForbidden();
})->with(['mahasiswa', 'dosen', 'kaprodi'])->with(['mahasiswa', 'dosen', 'admin']);

it('requires authentication for every template kind', function (string $kind): void {
    $this->get(route('admin.users.import-template', ['kind' => $kind]))->assertRedirect(route('login'));
})->with(['mahasiswa', 'dosen', 'admin']);
