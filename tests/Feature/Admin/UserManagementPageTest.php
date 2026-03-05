<?php

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\AdminProfile;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\User;
use Livewire\Livewire;

test('admin can only see users from their prodi', function (): void {
    $prodiA = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $prodiB = ProgramStudi::factory()->create(['name' => 'Teknologi Informasi']);

    // Admin for Prodi A
    $admin = User::factory()->asAdmin()->create();
    AdminProfile::create(['user_id' => $admin->id, 'program_studi_id' => $prodiA->id]);

    // Student in Prodi A
    $studentA = User::factory()->asMahasiswa()->create(['name' => 'Student A']);
    MahasiswaProfile::create([
        'user_id' => $studentA->id,
        'nim' => '111',
        'program_studi_id' => $prodiA->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    // Student in Prodi B
    $studentB = User::factory()->asMahasiswa()->create(['name' => 'Student B']);
    MahasiswaProfile::create([
        'user_id' => $studentB->id,
        'nim' => '222',
        'program_studi_id' => $prodiB->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $this->actingAs($admin);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$studentA])
        ->assertCanNotSeeTableRecords([$studentB]);
});

test('super admin can see all users from all prodi', function (): void {
    $superAdmin = User::factory()->asSuperAdmin()->create();

    $prodiA = ProgramStudi::factory()->create(['name' => 'Prodi A']);
    $prodiB = ProgramStudi::factory()->create(['name' => 'Prodi B']);

    $studentA = User::factory()->asMahasiswa()->create(['name' => 'Student A']);
    MahasiswaProfile::create([
        'user_id' => $studentA->id,
        'nim' => '111',
        'program_studi_id' => $prodiA->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $studentB = User::factory()->asMahasiswa()->create(['name' => 'Student B']);
    MahasiswaProfile::create([
        'user_id' => $studentB->id,
        'nim' => '222',
        'program_studi_id' => $prodiB->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $this->actingAs($superAdmin);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$studentA])
        ->assertCanSeeTableRecords([$studentB]);
});

test('mahasiswa cannot open filament users management page', function (): void {
    $mahasiswa = User::factory()->asMahasiswa()->create();

    $this->actingAs($mahasiswa)
        ->get('/admin/users')
        ->assertForbidden();
});
