<?php

use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\User;

test('authenticated user can view mahasiswa profile page', function () {
    $viewer = User::factory()->create();
    $student = User::factory()->asMahasiswa()->create();
    $programStudi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $programStudi->id,
        'concentration' => ProgramStudi::DEFAULT_GENERAL_CONCENTRATION,
        'is_active' => true,
    ]);

    $response = $this->actingAs($viewer)
        ->get(route('users.profile.show', ['user' => $student->id]))
        ->assertOk();

    $page = $response->viewData('page');

    expect($page['component'])->toBe('profile/show')
        ->and(data_get($page, 'props.profile.name'))->toBe($student->name)
        ->and(data_get($page, 'props.profile.roleLabel'))->toBe('Mahasiswa');
});

test('authenticated user can view dosen profile page', function () {
    $viewer = User::factory()->create();
    $lecturer = User::factory()->asDosen()->create();
    $programStudi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);

    DosenProfile::factory()->create([
        'user_id' => $lecturer->id,
        'program_studi_id' => $programStudi->id,
        'concentration' => ProgramStudi::DEFAULT_GENERAL_CONCENTRATION,
        'is_active' => true,
    ]);

    $response = $this->actingAs($viewer)
        ->get(route('users.profile.show', ['user' => $lecturer->id]))
        ->assertOk();

    $page = $response->viewData('page');

    expect($page['component'])->toBe('profile/show')
        ->and(data_get($page, 'props.profile.name'))->toBe($lecturer->name)
        ->and(data_get($page, 'props.profile.roleLabel'))->toBe('Dosen');
});
