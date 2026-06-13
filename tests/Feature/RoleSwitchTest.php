<?php

use App\Enums\AppRole;
use App\Models\KaprodiAssignment;
use App\Models\ProgramStudi;
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
        ->assertRedirect('/admin');

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

test('kaprodi can switch to dosen portal', function () {
    $programStudi = ProgramStudi::factory()->create();
    $kaprodi = User::factory()->asKaprodi()->create([
        'last_active_role' => AppRole::Kaprodi->value,
    ]);

    KaprodiAssignment::factory()->create([
        'program_studi_id' => $programStudi->id,
        'user_id' => $kaprodi->id,
        'is_primary' => true,
    ]);

    $this->actingAs($kaprodi)
        ->post('/role/switch', ['role' => AppRole::Dosen->value])
        ->assertRedirect('/dosen/dashboard');

    $kaprodi->refresh();

    expect(session('active_role'))->toBe(AppRole::Dosen->value);
    expect($kaprodi->last_active_role)->toBe(AppRole::Dosen->value);
    expect($kaprodi->hasRole(AppRole::Kaprodi))->toBeTrue();
    expect($kaprodi->hasRole(AppRole::Dosen))->toBeTrue();
});
