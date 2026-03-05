<?php

use App\Enums\AppRole;
use App\Models\Role;
use App\Models\User;

use App\Models\AdminProfile;
use App\Models\MahasiswaProfile;
use Filament\Tables\Http\Controllers\PropertyValueController;
use Livewire\Livewire;
use App\Filament\Resources\Users\Pages\ListUsers;

test('admin can only see users from their prodi', function (): void {
    $prodiA = 'Ilmu Komputer';
    $prodiB = 'Teknologi Informasi';

    // Admin for Prodi A
    $admin = User::factory()->asAdmin()->create();
    AdminProfile::create(['user_id' => $admin->id, 'program_studi' => $prodiA]);

    // Student in Prodi A
    $studentA = User::factory()->asMahasiswa()->create(['name' => 'Student A']);
    MahasiswaProfile::create(['user_id' => $studentA->id, 'nim' => '111', 'program_studi' => $prodiA, 'angkatan' => 2022]);

    // Student in Prodi B
    $studentB = User::factory()->asMahasiswa()->create(['name' => 'Student B']);
    MahasiswaProfile::create(['user_id' => $studentB->id, 'nim' => '222', 'program_studi' => $prodiB, 'angkatan' => 2022]);

    $this->actingAs($admin);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$studentA])
        ->assertCanNotSeeTableRecords([$studentB]);
});

test('super admin can see all users from all prodi', function (): void {
    $superAdmin = User::factory()->asSuperAdmin()->create();

    $studentA = User::factory()->asMahasiswa()->create(['name' => 'Student A']);
    MahasiswaProfile::create(['user_id' => $studentA->id, 'nim' => '111', 'program_studi' => 'Prodi A', 'angkatan' => 2022]);

    $studentB = User::factory()->asMahasiswa()->create(['name' => 'Student B']);
    MahasiswaProfile::create(['user_id' => $studentB->id, 'nim' => '222', 'program_studi' => 'Prodi B', 'angkatan' => 2022]);

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
