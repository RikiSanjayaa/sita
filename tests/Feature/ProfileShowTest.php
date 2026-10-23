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
        ->and(data_get($page, 'props.profile.thesis.examinerGroups.1.title'))->toBe('Penguji Sidang Skripsi')
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
        ->and(data_get($page, 'props.profile.relatedUsers.0.users.0.mentorshipCount'))->toBe(0)
        ->and(data_get($page, 'props.profile.relatedUsers.0.users.0.phase'))->toBe('research')
        ->and(data_get($page, 'props.profile.relatedUsers.0.users.0.phaseLabel'))->toBe('Riset')
        ->and(data_get($page, 'props.profile.relatedUsers.0.users.1'))->toBeNull()
        ->and(data_get($page, 'props.profile.relatedUsers.1.title'))->toBe('Mahasiswa yang Anda uji')
        ->and(data_get($page, 'props.profile.relatedUsers.1.users.0.name'))->toBe('Mahasiswa Diuji')
        ->and(data_get($page, 'props.profile.relatedUsers.1.users.0.phase'))->toBe('sempro')
        ->and(data_get($page, 'props.profile.relatedUsers.1.users.0.phaseLabel'))->toBe('Tahap Proposal')
        ->and(data_get($page, 'props.profile.relatedUsers.1.users.0.mentorshipCount'))->toBeNull();
});

test('dosen examination list follows open defenses on the current active thesis', function (string $status, string $state, bool $visible) {
    $viewer = User::factory()->create();
    $lecturer = User::factory()->asDosen()->create();
    $student = User::factory()->asMahasiswa()->create();
    $programStudi = ProgramStudi::factory()->create();
    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $programStudi->id,
        'phase' => $state === 'completed' ? 'completed' : 'sidang',
        'state' => $state,
        'started_at' => now()->subMonth(),
    ]);
    $defense = ThesisDefense::query()->create([
        'project_id' => $project->id,
        'type' => 'sidang',
        'attempt_no' => 1,
        'status' => $status,
    ]);
    ThesisDefenseExaminer::query()->create([
        'defense_id' => $defense->id,
        'lecturer_user_id' => $lecturer->id,
        'role' => 'examiner',
        'order_no' => 1,
    ]);

    $page = $this->actingAs($viewer)->get(route('users.profile.show', $lecturer))->assertOk()->viewData('page');
    $examined = data_get($page, 'props.profile.relatedUsers.1.users', []);
    expect(collect($examined)->pluck('id')->all())->toBe($visible ? [$student->id] : []);
    $this->assertDatabaseHas('thesis_defense_examiners', ['defense_id' => $defense->id, 'lecturer_user_id' => $lecturer->id]);
})->with([
    'draft assignment' => ['draft', 'active', true],
    'scheduled assignment' => ['scheduled', 'active', true],
    'awaiting finalization' => ['awaiting_finalization', 'active', true],
    'completed defense' => ['completed', 'active', false],
    'cancelled defense' => ['cancelled', 'active', false],
    'graduated thesis' => ['scheduled', 'completed', false],
    'cancelled thesis' => ['scheduled', 'cancelled', false],
]);

test('dosen examination list deduplicates attempts and excludes supervised and superseded students', function () {
    $viewer = User::factory()->create();
    $lecturer = User::factory()->asDosen()->create();
    $programStudi = ProgramStudi::factory()->create();
    $students = User::factory()->asMahasiswa()->count(3)->create();

    foreach ($students as $index => $student) {
        $project = ThesisProject::query()->create([
            'student_user_id' => $student->id,
            'program_studi_id' => $programStudi->id,
            'phase' => 'sidang',
            'state' => 'active',
            'started_at' => now()->subMonth(),
        ]);
        foreach ([1, 2] as $attempt) {
            $defense = ThesisDefense::query()->create([
                'project_id' => $project->id,
                'type' => 'sidang',
                'attempt_no' => $attempt,
                'status' => 'scheduled',
            ]);
            ThesisDefenseExaminer::query()->create([
                'defense_id' => $defense->id,
                'lecturer_user_id' => $lecturer->id,
                'role' => 'examiner',
                'order_no' => 1,
            ]);
        }
        if ($index === 1) {
            ThesisSupervisorAssignment::query()->create([
                'project_id' => $project->id,
                'lecturer_user_id' => $lecturer->id,
                'role' => 'primary',
                'status' => 'active',
                'started_at' => now()->subMonth(),
            ]);
        }
        if ($index === 2) {
            ThesisProject::query()->create([
                'student_user_id' => $student->id,
                'program_studi_id' => $programStudi->id,
                'phase' => 'title_review',
                'state' => 'active',
                'started_at' => now(),
            ]);
        }
    }

    $page = $this->actingAs($viewer)->get(route('users.profile.show', $lecturer))->assertOk()->viewData('page');
    expect(collect(data_get($page, 'props.profile.relatedUsers.0.users'))->pluck('id')->all())->toBe([$students[1]->id])
        ->and(collect(data_get($page, 'props.profile.relatedUsers.1.users'))->pluck('id')->all())->toBe([$students[0]->id])
        ->and(data_get($page, 'props.profile.stats.1.value'))->toBe('0')
        ->and(data_get($page, 'props.profile.stats.2.value'))->toBe('4');
});

test('examined student disappears after defense completion or graduation without deleting history', function (bool $graduate) {
    $viewer = User::factory()->create();
    $lecturer = User::factory()->asDosen()->create();
    $student = User::factory()->asMahasiswa()->create();
    $project = ThesisProject::factory()->create(['student_user_id' => $student->id, 'state' => 'active', 'phase' => 'sidang']);
    $defense = ThesisDefense::query()->create(['project_id' => $project->id, 'type' => 'sidang', 'attempt_no' => 1, 'status' => 'scheduled']);
    ThesisDefenseExaminer::query()->create(['defense_id' => $defense->id, 'lecturer_user_id' => $lecturer->id, 'role' => 'examiner', 'order_no' => 1]);
    $url = route('users.profile.show', $lecturer);
    $before = $this->actingAs($viewer)->get($url)->assertOk()->viewData('page');
    expect(data_get($before, 'props.profile.relatedUsers.1.users.0.id'))->toBe($student->id);

    if ($graduate) {
        $project->update(['state' => 'completed', 'phase' => 'completed', 'completed_at' => now()]);
    } else {
        $defense->update(['status' => 'completed', 'result' => 'pass']);
    }

    $after = $this->get($url)->assertOk()->viewData('page');
    expect(data_get($after, 'props.profile.relatedUsers.1'))->toBeNull()
        ->and(data_get($after, 'props.profile.stats.2.value'))->toBe('0');
    $this->assertDatabaseHas('thesis_defense_examiners', ['defense_id' => $defense->id, 'lecturer_user_id' => $lecturer->id]);
})->with(['defense completed' => false, 'student graduated' => true]);

test('student profile suppresses cancelled and superseded history only for non admins', function (bool $cancelledOnly) {
    $student = User::factory()->asMahasiswa()->create();
    $viewer = User::factory()->create();
    $admin = User::factory()->asAdmin()->create();
    $examiner = User::factory()->asDosen()->create(['name' => 'Archived Examiner']);
    $old = ThesisProject::factory()->create(['student_user_id' => $student->id, 'state' => 'cancelled', 'started_at' => now()->subMonths(3)]);
    ThesisProjectTitle::query()->create(['project_id' => $old->id, 'version_no' => 1, 'title_id' => 'Cancelled private title', 'status' => 'approved']);
    $defense = ThesisDefense::query()->create(['project_id' => $old->id, 'type' => 'sempro', 'attempt_no' => 1, 'status' => 'scheduled']);
    ThesisDefenseExaminer::query()->create(['defense_id' => $defense->id, 'lecturer_user_id' => $examiner->id, 'role' => 'examiner', 'order_no' => 1]);
    if (! $cancelledOnly) {
        ThesisProject::factory()->create(['student_user_id' => $student->id, 'state' => 'completed', 'started_at' => now()->subMonths(2)]);
        ThesisProject::factory()->create(['student_user_id' => $student->id, 'state' => 'active', 'started_at' => now()]);
    }

    $url = route('users.profile.show', $student);
    $page = $this->actingAs($viewer)->get($url)->assertOk()->viewData('page');
    expect(data_get($page, 'props.profile.stats.3.value'))->toBe($cancelledOnly ? '0' : '1')
        ->and(data_get($page, 'props.profile.stats.4.value'))->toBe('0')
        ->and(data_get($page, 'props.profile.stats.5.value'))->toBe('0')
        ->and(data_get($page, 'props.profile.thesis.examiners'))->toBe([])
        ->and(json_encode(data_get($page, 'props.profile')))->not->toContain('Cancelled private title', 'Archived Examiner');

    $adminPage = $this->actingAs($admin)->get($url)->assertOk()->viewData('page');
    expect(data_get($adminPage, 'props.profile.stats.3.value'))->toBe($cancelledOnly ? '1' : '3')
        ->and(data_get($adminPage, 'props.profile.stats.4.value'))->toBe($cancelledOnly ? '1' : '2')
        ->and(data_get($adminPage, 'props.profile.stats.5.value'))->toBe('1');
    $this->assertDatabaseHas('thesis_projects', ['id' => $old->id, 'state' => 'cancelled']);
    $this->assertDatabaseHas('thesis_project_titles', ['project_id' => $old->id, 'title_id' => 'Cancelled private title']);
})->with(['cancelled only' => true, 'cancelled and superseded with current thesis' => false]);

test('cancelled defense does not expose examiners or exam counts on a non admin student profile', function () {
    $student = User::factory()->asMahasiswa()->create();
    $viewer = User::factory()->create();
    $admin = User::factory()->asAdmin()->create();
    $examiner = User::factory()->asDosen()->create();
    $project = ThesisProject::factory()->create(['student_user_id' => $student->id, 'state' => 'active']);
    $defense = ThesisDefense::query()->create(['project_id' => $project->id, 'type' => 'sidang', 'attempt_no' => 1, 'status' => 'cancelled']);
    ThesisDefenseExaminer::query()->create(['defense_id' => $defense->id, 'lecturer_user_id' => $examiner->id, 'role' => 'examiner', 'order_no' => 1]);
    $url = route('users.profile.show', $student);

    $page = $this->actingAs($viewer)->get($url)->assertOk()->viewData('page');
    expect(data_get($page, 'props.profile.stats.5.value'))->toBe('0')
        ->and(data_get($page, 'props.profile.thesis.examiners'))->toBe([])
        ->and(data_get($page, 'props.profile.thesis.examinerGroups'))->toBe([]);

    $adminPage = $this->actingAs($admin)->get($url)->assertOk()->viewData('page');
    expect(data_get($adminPage, 'props.profile.stats.5.value'))->toBe('1')
        ->and(data_get($adminPage, 'props.profile.thesis.examiners.0.id'))->toBe($examiner->id);
});

test('newer cancelled thesis does not revive an older completed thesis on the student profile', function () {
    $student = User::factory()->asMahasiswa()->create();
    $viewer = User::factory()->create();
    $old = ThesisProject::factory()->create(['student_user_id' => $student->id, 'state' => 'completed', 'phase' => 'completed', 'started_at' => now()->subYear()]);
    ThesisProjectTitle::query()->create(['project_id' => $old->id, 'version_no' => 1, 'title_id' => 'Superseded completed title', 'status' => 'approved']);
    ThesisProject::factory()->create(['student_user_id' => $student->id, 'state' => 'cancelled', 'started_at' => now()]);

    $page = $this->actingAs($viewer)->get(route('users.profile.show', $student))->assertOk()->viewData('page');
    expect(data_get($page, 'props.profile.stats.3.value'))->toBe('0')
        ->and(data_get($page, 'props.profile.stats.4.value'))->toBe('0')
        ->and(data_get($page, 'props.profile.thesis.title'))->toBeNull();
    $this->assertDatabaseHas('thesis_project_titles', ['project_id' => $old->id, 'title_id' => 'Superseded completed title']);
});
