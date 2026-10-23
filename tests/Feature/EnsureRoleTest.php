<?php

use App\Enums\AppRole;
use App\Models\KaprodiAssignment;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\User;

/**
 * @param  array<int, string>  $roles
 */
function ensureRoleUserWith(array $roles, string $lastActiveRole): User
{
    $user = User::factory()->create(['last_active_role' => $lastActiveRole]);

    $roleIds = collect($roles)
        ->map(fn(string $name): int => Role::query()->firstOrCreate(['name' => $name])->id)
        ->all();

    $user->roles()->sync($roleIds);

    return $user;
}

/**
 * @param  array<int, string>  $roles
 */
function ensureRoleKaprodiWith(array $roles): User
{
    $user = ensureRoleUserWith($roles, AppRole::Kaprodi->value);

    KaprodiAssignment::factory()->create([
        'program_studi_id' => ProgramStudi::factory()->create()->id,
        'user_id' => $user->id,
        'is_primary' => true,
    ]);

    return $user;
}

dataset('route access by role', [
    'kaprodi opens kaprodi area' => ['/kaprodi/dashboard', [AppRole::Kaprodi->value], 200],
    'kaprodi opens dosen area without switching' => ['/dosen/dashboard', [AppRole::Kaprodi->value], 200],
    'kaprodi with dosen role opens dosen area' => ['/dosen/dashboard', [AppRole::Kaprodi->value, AppRole::Dosen->value], 200],
    'dosen opens dosen area' => ['/dosen/dashboard', [AppRole::Dosen->value], 200],
    'dosen is refused kaprodi area' => ['/kaprodi/dashboard', [AppRole::Dosen->value], 403],
    'mahasiswa is refused dosen area' => ['/dosen/dashboard', [AppRole::Mahasiswa->value], 403],
    'mahasiswa is refused kaprodi area' => ['/kaprodi/dashboard', [AppRole::Mahasiswa->value], 403],
]);

test('role-gated area follows the route role', function (string $path, array $roles, int $status) {
    $user = in_array(AppRole::Kaprodi->value, $roles, true)
        ? ensureRoleKaprodiWith($roles)
        : ensureRoleUserWith($roles, $roles[0]);

    $this->actingAs($user)->get($path)->assertStatus($status);
})->with('route access by role');

test('kaprodi visiting dosen area keeps the kaprodi role in session and database', function () {
    $kaprodi = ensureRoleKaprodiWith([AppRole::Kaprodi->value, AppRole::Dosen->value]);

    $this->actingAs($kaprodi)
        ->get('/dosen/dashboard')
        ->assertOk()
        ->assertSessionHas('active_role', AppRole::Kaprodi->value);

    $kaprodi->refresh();

    expect($kaprodi->last_active_role)->toBe(AppRole::Kaprodi->value);

    $this->actingAs($kaprodi)
        ->get('/kaprodi/dashboard')
        ->assertOk();

    $kaprodi->refresh();

    expect($kaprodi->last_active_role)->toBe(AppRole::Kaprodi->value);
});

test('legacy dosen last active role does not pull a kaprodi into the dosen role', function () {
    $kaprodi = ensureRoleKaprodiWith([AppRole::Kaprodi->value, AppRole::Dosen->value]);
    $kaprodi->forceFill(['last_active_role' => AppRole::Dosen->value])->save();

    $this->actingAs($kaprodi)
        ->get('/kaprodi/dashboard')
        ->assertOk();

    expect($kaprodi->resolveActiveRole(null))->toBe(AppRole::Kaprodi->value);
});
