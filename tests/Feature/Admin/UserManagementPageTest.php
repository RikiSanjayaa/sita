<?php

use App\Enums\AppRole;
use App\Models\Role;
use App\Models\User;

test('admin can open filament users management page', function (): void {
    $adminRole = Role::query()->firstOrCreate(['name' => AppRole::Admin->value]);
    $admin = User::factory()->asAdmin()->create();
    $admin->roles()->syncWithoutDetaching([$adminRole->id]);

    $this->actingAs($admin)
        ->get('/admin/users')
        ->assertOk();
});

test('mahasiswa cannot open filament users management page', function (): void {
    $mahasiswaRole = Role::query()->firstOrCreate(['name' => AppRole::Mahasiswa->value]);
    $mahasiswa = User::factory()->asMahasiswa()->create();
    $mahasiswa->roles()->syncWithoutDetaching([$mahasiswaRole->id]);

    $this->actingAs($mahasiswa)
        ->get('/admin/users')
        ->assertForbidden();
});
