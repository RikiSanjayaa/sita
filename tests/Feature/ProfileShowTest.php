<?php

use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisSupervisorAssignment;
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

test('mahasiswa profile separates sempro and sidang examiners', function () {
    $viewer = User::factory()->create();
    $student = User::factory()->asMahasiswa()->create();
    $examiner = User::factory()->asDosen()->create(['name' => 'Dosen Penguji Sama']);
    $programStudi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $programStudi->id,
        'is_active' => true,
    ]);

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $programStudi->id,
        'phase' => 'sidang',
        'state' => 'active',
        'started_at' => now()->subMonths(2),
    ]);

    ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Sistem Informasi Akademik',
        'status' => 'accepted',
    ]);

    $sempro = ThesisDefense::query()->create([
        'project_id' => $project->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'completed',
        'scheduled_for' => now()->subMonth(),
    ]);

    $sidang = ThesisDefense::query()->create([
        'project_id' => $project->id,
        'type' => 'sidang',
        'attempt_no' => 1,
        'status' => 'scheduled',
        'scheduled_for' => now()->addWeek(),
    ]);

    foreach ([$sempro, $sidang] as $defense) {
        ThesisDefenseExaminer::query()->create([
            'defense_id' => $defense->id,
            'lecturer_user_id' => $examiner->id,
            'role' => 'examiner',
            'order_no' => 1,
        ]);
    }

    $response = $this->actingAs($viewer)
        ->get(route('users.profile.show', ['user' => $student->id]))
        ->assertOk();

    $page = $response->viewData('page');

    expect(data_get($page, 'props.profile.thesis.examinerGroups.0.title'))->toBe('Penguji Sempro')
        ->and(data_get($page, 'props.profile.thesis.examinerGroups.0.users.0.name'))->toBe('Dosen Penguji Sama')
        ->and(data_get($page, 'props.profile.thesis.examinerGroups.1.title'))->toBe('Penguji Sidang')
        ->and(data_get($page, 'props.profile.thesis.examinerGroups.1.users.0.name'))->toBe('Dosen Penguji Sama');
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
        ->and(data_get($page, 'props.profile.roleLabel'))->toBe('Dosen')
        ->and(data_get($page, 'props.profile.stats.0.label'))->toBe('Mahasiswa Bimbingan Aktif')
        ->and(data_get($page, 'props.profile.relatedUsers.0.title'))->toBe('Mahasiswa bimbingan aktif');
});

test('dosen profile only counts active supervised students', function () {
    $viewer = User::factory()->create();
    $lecturer = User::factory()->asDosen()->create();
    $student = User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa Bimbingan']);
    $examinedStudent = User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa Diuji']);
    $programStudi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);

    DosenProfile::factory()->create([
        'user_id' => $lecturer->id,
        'program_studi_id' => $programStudi->id,
        'is_active' => true,
    ]);

    foreach ([$student, $examinedStudent] as $user) {
        MahasiswaProfile::factory()->create([
            'user_id' => $user->id,
            'program_studi_id' => $programStudi->id,
            'is_active' => true,
        ]);
    }

    $supervisedProject = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $programStudi->id,
        'phase' => 'research',
        'state' => 'active',
        'started_at' => now()->subMonth(),
    ]);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $supervisedProject->id,
        'lecturer_user_id' => $lecturer->id,
        'role' => 'primary',
        'status' => 'active',
        'started_at' => now()->subMonth(),
    ]);

    $examinedProject = ThesisProject::query()->create([
        'student_user_id' => $examinedStudent->id,
        'program_studi_id' => $programStudi->id,
        'phase' => 'sempro',
        'state' => 'active',
        'started_at' => now()->subMonth(),
    ]);

    $defense = ThesisDefense::query()->create([
        'project_id' => $examinedProject->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'scheduled',
        'scheduled_for' => now()->addWeek(),
    ]);

    ThesisDefenseExaminer::query()->create([
        'defense_id' => $defense->id,
        'lecturer_user_id' => $lecturer->id,
        'role' => 'examiner',
        'order_no' => 1,
    ]);

    $response = $this->actingAs($viewer)
        ->get(route('users.profile.show', ['user' => $lecturer->id]))
        ->assertOk();

    $page = $response->viewData('page');

    expect(data_get($page, 'props.profile.stats.0.value'))->toBe('1')
        ->and(data_get($page, 'props.profile.relatedUsers.0.users.0.name'))->toBe('Mahasiswa Bimbingan')
        ->and(data_get($page, 'props.profile.relatedUsers.0.users.1'))->toBeNull();
});
