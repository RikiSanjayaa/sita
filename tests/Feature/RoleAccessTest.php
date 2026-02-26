<?php

use App\Enums\AppRole;
use App\Models\Role;
use App\Models\User;

function createUserWithRoles(array $roles, ?string $activeRole = null): User
{
    $user = User::factory()->create([
        'last_active_role' => $activeRole ?? $roles[0] ?? AppRole::Mahasiswa->value,
    ]);

    $roleIds = collect($roles)
        ->map(fn (string $name): int => Role::query()->firstOrCreate(['name' => $name])->id)
        ->all();

    $user->roles()->sync($roleIds);

    return $user;
}

test('mahasiswa can only access mahasiswa area', function () {
    $user = createUserWithRoles([AppRole::Mahasiswa->value], AppRole::Mahasiswa->value);

    $this->actingAs($user);

    $this->get('/mahasiswa/dashboard')->assertOk();
    $this->get('/dosen/dashboard')->assertForbidden();
    $this->get('/admin/dashboard')->assertForbidden();
});

test('dosen can only access dosen area', function () {
    $user = createUserWithRoles([AppRole::Dosen->value], AppRole::Dosen->value);

    $this->actingAs($user);

    $this->get('/dosen/dashboard')->assertOk();
    $this->get('/mahasiswa/dashboard')->assertForbidden();
    $this->get('/admin/dashboard')->assertForbidden();
});

test('admin can only access admin area', function () {
    $user = createUserWithRoles([AppRole::Admin->value], AppRole::Admin->value);

    $this->actingAs($user);

    $this->get('/admin/dashboard')->assertOk();
    $this->get('/mahasiswa/dashboard')->assertForbidden();
    $this->get('/dosen/dashboard')->assertForbidden();
});

test('dashboard resolver sends authenticated user to active role dashboard', function () {
    $mahasiswa = createUserWithRoles([AppRole::Mahasiswa->value], AppRole::Mahasiswa->value);
    $dosen = createUserWithRoles([AppRole::Dosen->value], AppRole::Dosen->value);
    $admin = createUserWithRoles([AppRole::Admin->value], AppRole::Admin->value);

    $this->actingAs($mahasiswa)
        ->get('/dashboard')
        ->assertRedirect('/mahasiswa/dashboard');

    $this->actingAs($dosen)
        ->get('/dashboard')
        ->assertRedirect('/dosen/dashboard');

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertRedirect('/admin/dashboard');
});
