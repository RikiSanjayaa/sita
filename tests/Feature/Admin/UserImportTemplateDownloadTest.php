<?php

use App\Enums\AppRole;
use App\Models\Role;
use App\Models\User;

test('admin can download excel-compatible import template', function (): void {
    $adminRole = Role::query()->firstOrCreate(['name' => AppRole::Admin->value]);
    $admin = User::factory()->asAdmin()->create();
    $admin->roles()->syncWithoutDetaching([$adminRole->id]);

    $this->actingAs($admin)
        ->get(route('admin.users.import-template', ['format' => 'xlsx']))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertHeader('content-disposition', 'attachment; filename="user-import-template.xlsx"');
});

test('admin can download csv import template', function (): void {
    $adminRole = Role::query()->firstOrCreate(['name' => AppRole::Admin->value]);
    $admin = User::factory()->asAdmin()->create();
    $admin->roles()->syncWithoutDetaching([$adminRole->id]);

    $this->actingAs($admin)
        ->get(route('admin.users.import-template', ['format' => 'csv']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertHeader('content-disposition', 'attachment; filename="user-import-template.csv"')
        ->assertSee('name,email,phone_number,role,password,nim,prodi,angkatan,concentration,nik,supervision_quota');
});

test('non-admin cannot download excel import template', function (): void {
    $mahasiswaRole = Role::query()->firstOrCreate(['name' => AppRole::Mahasiswa->value]);
    $mahasiswa = User::factory()->asMahasiswa()->create();
    $mahasiswa->roles()->syncWithoutDetaching([$mahasiswaRole->id]);

    $this->actingAs($mahasiswa)
        ->get(route('admin.users.import-template', ['format' => 'csv']))
        ->assertForbidden();
});
