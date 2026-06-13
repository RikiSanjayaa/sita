<?php

use App\Enums\AppRole;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\AdminProfile;
use App\Models\DosenProfile;
use App\Models\KaprodiAssignment;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\UserProvisioningService;
use Illuminate\Validation\ValidationException;
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

test('super admin can filter users by program studi in filament users page', function (): void {
    $prodiA = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $prodiB = ProgramStudi::factory()->create(['name' => 'Teknologi Informasi']);

    $superAdmin = User::factory()->asSuperAdmin()->create();

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
        ->filterTable('program_studi_id', $prodiA->id)
        ->assertCanSeeTableRecords([$studentA])
        ->assertCanNotSeeTableRecords([$studentB]);
});

test('super admin users page has kaprodi tab and prodi filter includes kaprodi assignment', function (): void {
    $superAdmin = User::factory()->asSuperAdmin()->create();
    $prodiA = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $prodiB = ProgramStudi::factory()->create(['name' => 'Teknologi Informasi']);
    $kaprodiA = User::factory()->asKaprodi()->create(['name' => 'Kaprodi Ilkom']);
    $kaprodiB = User::factory()->asKaprodi()->create(['name' => 'Kaprodi TI']);

    KaprodiAssignment::factory()->create([
        'program_studi_id' => $prodiA->id,
        'user_id' => $kaprodiA->id,
        'is_primary' => true,
    ]);

    KaprodiAssignment::factory()->create([
        'program_studi_id' => $prodiB->id,
        'user_id' => $kaprodiB->id,
        'is_primary' => true,
    ]);

    $this->actingAs($superAdmin);

    $component = Livewire::test(ListUsers::class);

    expect(array_keys($component->instance()->getTabs()))->toContain('kaprodi');

    $component
        ->set('activeTab', 'kaprodi')
        ->filterTable('program_studi_id', $prodiA->id)
        ->assertCanSeeTableRecords([$kaprodiA])
        ->assertCanNotSeeTableRecords([$kaprodiB]);
});

test('kaprodi role cannot be provisioned without program studi assignment', function (): void {
    $superAdmin = User::factory()->asSuperAdmin()->create();
    $user = User::factory()->create(['name' => 'Kaprodi Tanpa Prodi']);

    $this->actingAs($superAdmin);

    expect(fn() => app(UserProvisioningService::class)->syncRoleAndProfiles($user, [
        'role' => AppRole::Kaprodi->value,
    ]))->toThrow(ValidationException::class);
});

test('mahasiswa cannot open filament users management page', function (): void {
    $mahasiswa = User::factory()->asMahasiswa()->create();

    $this->actingAs($mahasiswa)
        ->get('/admin/users')
        ->assertForbidden();
});

test('admin sees thesis-native dosen load counts in users resource', function (): void {
    $prodi = ProgramStudi::factory()->create([
        'name' => 'Ilmu Komputer',
        'slug' => 'ilkom',
        'concentrations' => ['Jaringan', 'Sistem Cerdas', 'Computer Vision'],
    ]);

    $admin = User::factory()->asAdmin()->create();
    AdminProfile::query()->create([
        'user_id' => $admin->id,
        'program_studi_id' => $prodi->id,
    ]);

    $lecturer = User::factory()->asDosen()->create(['name' => 'Dosen Beban']);
    DosenProfile::query()->create([
        'user_id' => $lecturer->id,
        'nik' => '1987001',
        'program_studi_id' => $prodi->id,
        'concentration' => 'Jaringan',
        'supervision_quota' => 4,
        'is_active' => true,
    ]);

    $students = User::factory()->count(4)->create();

    foreach ($students as $index => $student) {
        $student->roles()->sync([
            Role::query()->firstOrCreate(['name' => 'mahasiswa'])->id,
        ]);

        MahasiswaProfile::query()->create([
            'user_id' => $student->id,
            'nim' => sprintf('22105101%02d', $index + 1),
            'program_studi_id' => $prodi->id,
            'concentration' => 'Jaringan',
            'angkatan' => 2022,
            'is_active' => true,
        ]);
    }

    $projectOne = ThesisProject::query()->create([
        'student_user_id' => $students[0]->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'research',
        'state' => 'active',
        'started_at' => now()->subDays(15),
        'created_by' => $students[0]->id,
    ]);

    $projectTwo = ThesisProject::query()->create([
        'student_user_id' => $students[1]->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'research',
        'state' => 'active',
        'started_at' => now()->subDays(12),
        'created_by' => $students[1]->id,
    ]);

    $projectThree = ThesisProject::query()->create([
        'student_user_id' => $students[2]->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'sidang',
        'state' => 'active',
        'started_at' => now()->subDays(10),
        'created_by' => $students[2]->id,
    ]);

    $inactiveProject = ThesisProject::query()->create([
        'student_user_id' => $students[3]->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'completed',
        'state' => 'completed',
        'started_at' => now()->subDays(50),
        'completed_at' => now()->subDays(3),
        'created_by' => $students[3]->id,
    ]);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $projectOne->id,
        'lecturer_user_id' => $lecturer->id,
        'role' => 'primary',
        'status' => 'active',
        'assigned_by' => $admin->id,
        'started_at' => now()->subDays(14),
    ]);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $projectTwo->id,
        'lecturer_user_id' => $lecturer->id,
        'role' => 'primary',
        'status' => 'active',
        'assigned_by' => $admin->id,
        'started_at' => now()->subDays(11),
    ]);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $projectThree->id,
        'lecturer_user_id' => $lecturer->id,
        'role' => 'secondary',
        'status' => 'active',
        'assigned_by' => $admin->id,
        'started_at' => now()->subDays(9),
    ]);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $inactiveProject->id,
        'lecturer_user_id' => $lecturer->id,
        'role' => 'primary',
        'status' => 'active',
        'assigned_by' => $admin->id,
        'started_at' => now()->subDays(40),
    ]);

    $scheduledSempro = ThesisDefense::query()->create([
        'project_id' => $projectOne->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'scheduled',
        'result' => 'pending',
        'scheduled_for' => now()->addWeek(),
        'location' => 'Ruang Seminar 1',
        'mode' => 'offline',
        'created_by' => $admin->id,
    ]);

    $scheduledSidang = ThesisDefense::query()->create([
        'project_id' => $projectThree->id,
        'type' => 'sidang',
        'attempt_no' => 1,
        'status' => 'scheduled',
        'result' => 'pending',
        'scheduled_for' => now()->addDays(10),
        'location' => 'Ruang Sidang A',
        'mode' => 'offline',
        'created_by' => $admin->id,
    ]);

    $completedSempro = ThesisDefense::query()->create([
        'project_id' => $projectTwo->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'completed',
        'result' => 'pass',
        'scheduled_for' => now()->subDays(2),
        'location' => 'Ruang Seminar 2',
        'mode' => 'offline',
        'created_by' => $admin->id,
    ]);

    ThesisDefenseExaminer::query()->create([
        'defense_id' => $scheduledSempro->id,
        'lecturer_user_id' => $lecturer->id,
        'role' => 'examiner',
        'order_no' => 1,
        'decision' => 'pending',
        'assigned_by' => $admin->id,
    ]);

    ThesisDefenseExaminer::query()->create([
        'defense_id' => $scheduledSidang->id,
        'lecturer_user_id' => $lecturer->id,
        'role' => 'examiner',
        'order_no' => 1,
        'decision' => 'pending',
        'assigned_by' => $admin->id,
    ]);

    ThesisDefenseExaminer::query()->create([
        'defense_id' => $completedSempro->id,
        'lecturer_user_id' => $lecturer->id,
        'role' => 'examiner',
        'order_no' => 1,
        'decision' => 'pass',
        'assigned_by' => $admin->id,
    ]);

    $this->actingAs($admin);

    $record = UserResource::getEloquentQuery()->findOrFail($lecturer->id);

    expect($record->active_primary_supervision_count)->toBe(2)
        ->and($record->active_secondary_supervision_count)->toBe(1)
        ->and($record->scheduled_sempro_examiner_count)->toBe(1)
        ->and($record->scheduled_sidang_examiner_count)->toBe(1);

    $this->get(UserResource::getUrl('view', ['record' => $lecturer]))
        ->assertOk()
        ->assertSee('Beban Tugas Akhir')
        ->assertSee('Pembimbing 1 Aktif')
        ->assertSee('Pembimbing 2 Aktif')
        ->assertSee('Sempro Terjadwal')
        ->assertSee('Sidang Terjadwal')
        ->assertSee('Kuota pembimbing dapat diatur oleh superadmin.');
});

test('super admin can open dosen edit form with academic assignments', function (): void {
    $superAdmin = User::factory()->asSuperAdmin()->create();
    $prodi = ProgramStudi::factory()->create([
        'name' => 'Ilmu Komputer',
        'slug' => 'ilkom',
        'concentrations' => ['Jaringan', 'Sistem Cerdas'],
    ]);
    $lecturer = User::factory()->asDosen()->create(['name' => 'Dosen Multi']);

    DosenProfile::query()->create([
        'user_id' => $lecturer->id,
        'nik' => '1987002',
        'program_studi_id' => $prodi->id,
        'concentration' => 'Jaringan',
        'supervision_quota' => 12,
        'is_active' => true,
    ]);

    $this->actingAs($superAdmin);

    $component = Livewire::test(EditUser::class, ['record' => $lecturer->getKey()])
        ->assertFormSet([
            'role' => AppRole::Dosen->value,
        ]);

    expect(collect($component->get('data.academic_assignments')))
        ->contains(fn(array $assignment): bool => (int) $assignment['program_studi_id'] === $prodi->id
            && $assignment['concentration'] === 'Jaringan'
            && $assignment['is_primary'] === true
            && $assignment['is_active'] === true)
        ->toBeTrue();
});
