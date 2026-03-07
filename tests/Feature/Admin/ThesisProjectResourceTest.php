<?php

use App\Filament\Resources\ThesisProjects\Pages\ListThesisProjects;
use App\Filament\Resources\ThesisProjects\ThesisProjectResource;
use App\Models\AdminProfile;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Models\User;
use Livewire\Livewire;

test('admin can only see thesis projects from their prodi', function (): void {
    $prodiA = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $prodiB = ProgramStudi::factory()->create(['name' => 'Teknologi Informasi']);

    $admin = User::factory()->asAdmin()->create();
    AdminProfile::query()->create([
        'user_id' => $admin->id,
        'program_studi_id' => $prodiA->id,
    ]);

    $studentA = User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa A']);
    MahasiswaProfile::query()->create([
        'user_id' => $studentA->id,
        'nim' => '2210510101',
        'program_studi_id' => $prodiA->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $studentB = User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa B']);
    MahasiswaProfile::query()->create([
        'user_id' => $studentB->id,
        'nim' => '2210510102',
        'program_studi_id' => $prodiB->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $projectA = ThesisProject::query()->create([
        'student_user_id' => $studentA->id,
        'program_studi_id' => $prodiA->id,
        'phase' => 'research',
        'state' => 'active',
        'started_at' => now()->subDays(10),
    ]);

    $projectB = ThesisProject::query()->create([
        'student_user_id' => $studentB->id,
        'program_studi_id' => $prodiB->id,
        'phase' => 'sempro',
        'state' => 'active',
        'started_at' => now()->subDays(8),
    ]);

    ThesisProjectTitle::query()->create([
        'project_id' => $projectA->id,
        'version_no' => 1,
        'title_id' => 'Judul A',
        'status' => 'approved',
        'submitted_by_user_id' => $studentA->id,
        'submitted_at' => now()->subDays(9),
    ]);

    ThesisProjectTitle::query()->create([
        'project_id' => $projectB->id,
        'version_no' => 1,
        'title_id' => 'Judul B',
        'status' => 'approved',
        'submitted_by_user_id' => $studentB->id,
        'submitted_at' => now()->subDays(7),
    ]);

    /** @var \Tests\TestCase $this */
    $this->actingAs($admin);

    Livewire::test(ListThesisProjects::class)
        ->assertCanSeeTableRecords([$projectA])
        ->assertCanNotSeeTableRecords([$projectB]);

    $this->get(ThesisProjectResource::getUrl('view', ['record' => $projectA]))
        ->assertOk();
});

test('mahasiswa cannot open filament thesis projects page', function (): void {
    $mahasiswa = User::factory()->asMahasiswa()->create();

    /** @var \Tests\TestCase $this */
    $this->actingAs($mahasiswa)
        ->get('/admin/thesis-projects')
        ->assertForbidden();
});
