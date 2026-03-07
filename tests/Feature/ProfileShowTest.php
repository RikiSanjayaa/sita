<?php

use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

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

    $this->actingAs($viewer)
        ->get(route('users.profile.show', ['user' => $student->id]))
        ->assertInertia(fn(Assert $page) => $page
            ->component('profile/show')
            ->where('profile.name', $student->name)
            ->where('profile.roleLabel', 'Mahasiswa'));
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

    $this->actingAs($viewer)
        ->get(route('users.profile.show', ['user' => $lecturer->id]))
        ->assertInertia(fn(Assert $page) => $page
            ->component('profile/show')
            ->where('profile.name', $lecturer->name)
            ->where('profile.roleLabel', 'Dosen'));
});
