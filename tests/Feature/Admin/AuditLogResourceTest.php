<?php

use App\Filament\Resources\ThesisProjectEvents\Pages\ListThesisProjectEvents;
use App\Filament\Resources\ThesisProjectEvents\ThesisProjectEventResource;
use App\Models\AdminProfile;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisProjectTitle;
use App\Models\User;
use Livewire\Livewire;

test('admin audit log page is scoped by program studi', function (): void {
    $prodiA = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $prodiB = ProgramStudi::factory()->create(['name' => 'Sistem Informasi']);

    $admin = User::factory()->asAdmin()->create();
    AdminProfile::query()->create([
        'user_id' => $admin->id,
        'program_studi_id' => $prodiA->id,
    ]);

    $studentA = User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa A']);
    MahasiswaProfile::query()->create([
        'user_id' => $studentA->id,
        'nim' => '2210510501',
        'program_studi_id' => $prodiA->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $studentB = User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa B']);
    MahasiswaProfile::query()->create([
        'user_id' => $studentB->id,
        'nim' => '2210510502',
        'program_studi_id' => $prodiB->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $projectA = ThesisProject::query()->create([
        'student_user_id' => $studentA->id,
        'program_studi_id' => $prodiA->id,
        'phase' => 'sempro',
        'state' => 'active',
        'started_at' => now()->subDays(5),
        'created_by' => $studentA->id,
    ]);

    $projectB = ThesisProject::query()->create([
        'student_user_id' => $studentB->id,
        'program_studi_id' => $prodiB->id,
        'phase' => 'research',
        'state' => 'active',
        'started_at' => now()->subDays(4),
        'created_by' => $studentB->id,
    ]);

    ThesisProjectTitle::query()->create([
        'project_id' => $projectA->id,
        'version_no' => 1,
        'title_id' => 'Judul Audit A',
        'status' => 'approved',
        'submitted_by_user_id' => $studentA->id,
        'submitted_at' => now()->subDays(4),
    ]);

    ThesisProjectTitle::query()->create([
        'project_id' => $projectB->id,
        'version_no' => 1,
        'title_id' => 'Judul Audit B',
        'status' => 'approved',
        'submitted_by_user_id' => $studentB->id,
        'submitted_at' => now()->subDays(3),
    ]);

    $eventA = ThesisProjectEvent::query()->create([
        'project_id' => $projectA->id,
        'actor_user_id' => $admin->id,
        'event_type' => 'sempro_scheduled',
        'label' => 'Sempro dijadwalkan',
        'description' => 'Audit A',
        'occurred_at' => now()->subHour(),
    ]);

    $eventB = ThesisProjectEvent::query()->create([
        'project_id' => $projectB->id,
        'actor_user_id' => $admin->id,
        'event_type' => 'sidang_scheduled',
        'label' => 'Sidang dijadwalkan',
        'description' => 'Audit B',
        'occurred_at' => now()->subMinutes(30),
    ]);

    /** @var \Tests\TestCase $this */
    $this->actingAs($admin);

    Livewire::test(ListThesisProjectEvents::class)
        ->assertCanSeeTableRecords([$eventA])
        ->assertCanNotSeeTableRecords([$eventB])
        ->filterTable('event_type', 'sempro_scheduled')
        ->assertCanSeeTableRecords([$eventA]);

    $this->get(ThesisProjectEventResource::getUrl('index'))
        ->assertOk()
        ->assertSee('Audit Tugas Akhir');
});

test('super admin can see audit logs across all program studi', function (): void {
    $superAdmin = User::factory()->asSuperAdmin()->create();
    $prodiA = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $prodiB = ProgramStudi::factory()->create(['name' => 'Sistem Informasi']);

    $studentA = User::factory()->asMahasiswa()->create();
    MahasiswaProfile::query()->create([
        'user_id' => $studentA->id,
        'nim' => '2210510591',
        'program_studi_id' => $prodiA->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $studentB = User::factory()->asMahasiswa()->create();
    MahasiswaProfile::query()->create([
        'user_id' => $studentB->id,
        'nim' => '2210510592',
        'program_studi_id' => $prodiB->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $projectA = ThesisProject::query()->create([
        'student_user_id' => $studentA->id,
        'program_studi_id' => $prodiA->id,
        'phase' => 'sempro',
        'state' => 'active',
        'started_at' => now()->subDays(3),
        'created_by' => $studentA->id,
    ]);

    $projectB = ThesisProject::query()->create([
        'student_user_id' => $studentB->id,
        'program_studi_id' => $prodiB->id,
        'phase' => 'sidang',
        'state' => 'active',
        'started_at' => now()->subDays(2),
        'created_by' => $studentB->id,
    ]);

    $eventA = ThesisProjectEvent::query()->create([
        'project_id' => $projectA->id,
        'actor_user_id' => $superAdmin->id,
        'event_type' => 'sempro_scheduled',
        'label' => 'Sempro dijadwalkan',
        'description' => 'Audit A',
        'occurred_at' => now()->subHour(),
    ]);

    $eventB = ThesisProjectEvent::query()->create([
        'project_id' => $projectB->id,
        'actor_user_id' => $superAdmin->id,
        'event_type' => 'sidang_scheduled',
        'label' => 'Sidang dijadwalkan',
        'description' => 'Audit B',
        'occurred_at' => now()->subMinutes(15),
    ]);

    /** @var \Tests\TestCase $this */
    $this->actingAs($superAdmin);

    Livewire::test(ListThesisProjectEvents::class)
        ->assertCanSeeTableRecords([$eventA, $eventB]);
});
