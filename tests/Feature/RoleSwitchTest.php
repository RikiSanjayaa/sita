<?php

use App\Enums\AppRole;
use App\Models\Role;
use App\Models\User;

test('user can switch to another assigned role', function () {
    $user = User::factory()->create([
        'last_active_role' => AppRole::Mahasiswa->value,
    ]);

    $mahasiswaRole = Role::query()->firstOrCreate(['name' => AppRole::Mahasiswa->value]);
    $adminRole = Role::query()->firstOrCreate(['name' => AppRole::Admin->value]);

    $user->roles()->sync([$mahasiswaRole->id, $adminRole->id]);

    $this->actingAs($user)
        ->post('/role/switch', ['role' => AppRole::Admin->value])
        ->assertRedirect('/admin/dashboard');

    $user->refresh();

    expect(session('active_role'))->toBe(AppRole::Admin->value);
    expect($user->last_active_role)->toBe(AppRole::Admin->value);
});

test('user cannot switch to unassigned role', function () {
    $user = User::factory()->create([
        'last_active_role' => AppRole::Mahasiswa->value,
    ]);

    $mahasiswaRole = Role::query()->firstOrCreate(['name' => AppRole::Mahasiswa->value]);
    $user->roles()->sync([$mahasiswaRole->id]);

    $this->actingAs($user)
        ->post('/role/switch', ['role' => AppRole::Admin->value])
        ->assertForbidden();
});
