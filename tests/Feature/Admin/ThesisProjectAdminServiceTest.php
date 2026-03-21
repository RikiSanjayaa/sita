<?php

use App\Enums\AdvisorType;
use App\Models\AdminProfile;
use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipAssignment;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipChatThreadParticipant;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisRevision;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use App\Services\ThesisProjectAdminService;
use Illuminate\Support\Facades\Notification;

test('admin service schedules sempro from thesis project aggregate without creating legacy sempro rows', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $superAdmin = User::factory()->asSuperAdmin()->create();
    $student = User::factory()->asMahasiswa()->create();
    $dosenA = User::factory()->asDosen()->create();
    $dosenB = User::factory()->asDosen()->create();
    $prodi = ProgramStudi::factory()->create(['name' => 'Informatika']);

    AdminProfile::query()->create([
        'user_id' => $admin->id,
        'program_studi_id' => $prodi->id,
    ]);

    MahasiswaProfile::query()->create([
        'user_id' => $student->id,
        'nim' => '2210510300',
        'program_studi_id' => $prodi->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'title_review',
        'state' => 'active',
        'started_at' => now()->subDays(2),
        'created_by' => $student->id,
    ]);

    ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Penjadwalan Sempro dari Proyek',
        'status' => 'approved',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subDays(2),
        'decided_by_user_id' => $admin->id,
        'decided_at' => now()->subDay(),
    ]);

    app(ThesisProjectAdminService::class)->scheduleSempro(
        project: $project,
        scheduledBy: $admin->id,
        scheduledFor: now()->addDays(5)->format('Y-m-d H:i:s'),
        location: 'Ruang Seminar Proyek',
        mode: 'offline',
        examinerUserIds: [$dosenA->id, $dosenB->id],
    );

    $semproDefense = ThesisDefense::query()->where('project_id', $project->id)->where('type', 'sempro')->firstOrFail();
    $semproThread = MentorshipChatThread::query()->where('type', 'sempro')->firstOrFail();

    expect(ThesisDefense::query()->where('type', 'sempro')->count())->toBe(1)
        ->and($semproDefense->status)->toBe('scheduled')
        ->and($semproDefense->examiners()->count())->toBe(2)
        ->and($semproThread->context_id)->toBe($semproDefense->id)
        ->and(MentorshipChatThreadParticipant::query()->where('role', 'student')->count())->toBe(1)
        ->and(MentorshipChatThreadParticipant::query()->where('role', 'examiner')->count())->toBe(2)
        ->and(ThesisProjectEvent::query()->where('event_type', 'sempro_scheduled')->count())->toBe(1)
        ->and($admin->notifications()->count())->toBe(1)
        ->and($superAdmin->notifications()->count())->toBe(1)
        ->and($project->fresh()->phase)->toBe('sempro');
});

test('admin service notifies mahasiswa when sempro is scheduled', function (): void {
    Notification::fake();

    $admin = User::factory()->asAdmin()->create();
    $student = User::factory()->asMahasiswa()->create();
    $examinerOne = User::factory()->asDosen()->create();
    $examinerTwo = User::factory()->asDosen()->create();
    $prodi = ProgramStudi::factory()->create(['name' => 'Teknik Informatika']);

    MahasiswaProfile::query()->create([
        'user_id' => $student->id,
        'nim' => '2210510310',
        'program_studi_id' => $prodi->id,
        'concentration' => 'Artificial Intelligence',
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    foreach ([$examinerOne, $examinerTwo] as $lecturer) {
        DosenProfile::query()->create([
            'user_id' => $lecturer->id,
            'nik' => fake()->numerify('730101010101####'),
            'program_studi_id' => $prodi->id,
            'concentration' => 'Artificial Intelligence',
            'supervision_quota' => 10,
            'is_active' => true,
        ]);
    }

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'title_review',
        'state' => 'active',
        'started_at' => now()->subDays(5),
        'created_by' => $student->id,
    ]);

    ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Deteksi Semantik Jadwal Seminar',
        'status' => 'approved',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subDays(4),
        'decided_by_user_id' => $admin->id,
        'decided_at' => now()->subDays(3),
    ]);

    app(ThesisProjectAdminService::class)->scheduleSempro(
        project: $project,
        scheduledBy: $admin->id,
        scheduledFor: '2026-04-10 09:00:00',
        location: 'Ruang Seminar 1',
        mode: 'offline',
        examinerUserIds: [$examinerOne->id, $examinerTwo->id],
    );

    Notification::assertSentTo($student, RealtimeNotification::class, function (RealtimeNotification $notification, array $channels) use ($student): bool {
        $data = $notification->toArray($student);

        return in_array('database', $channels, true)
            && $data['title'] === 'Sempro dijadwalkan'
            && str_contains($data['description'], 'Ruang Seminar 1')
            && $data['preferenceKey'] === 'statusTugasAkhir';
    });
});

test('admin service assigns supervisors through thesis supervisor assignments only', function (): void {
    Notification::fake();

    $admin = User::factory()->asAdmin()->create();
    $student = User::factory()->asMahasiswa()->create();
    $primary = User::factory()->asDosen()->create();
    $secondary = User::factory()->asDosen()->create();
    $prodi = ProgramStudi::factory()->create(['name' => 'Teknik Informatika']);

    MahasiswaProfile::query()->create([
        'user_id' => $student->id,
        'nim' => '2210510309',
        'program_studi_id' => $prodi->id,
        'concentration' => 'Computer Vision',
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    DosenProfile::query()->create([
        'user_id' => $primary->id,
        'nik' => '7301010101011111',
        'program_studi_id' => $prodi->id,
        'concentration' => 'Computer Vision',
        'supervision_quota' => 10,
        'is_active' => true,
    ]);

    DosenProfile::query()->create([
        'user_id' => $secondary->id,
        'nik' => '7301010101012222',
        'program_studi_id' => $prodi->id,
        'concentration' => 'Computer Vision',
        'supervision_quota' => 10,
        'is_active' => true,
    ]);

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'research',
        'state' => 'active',
        'started_at' => now()->subDays(20),
    ]);

    app(ThesisProjectAdminService::class)->assignSupervisors(
        project: $project,
        assignedBy: $admin->id,
        primaryLecturerUserId: $primary->id,
        secondaryLecturerUserId: $secondary->id,
        notes: 'Pembimbing awal ditetapkan.',
    );

    expect(ThesisSupervisorAssignment::query()->where('project_id', $project->id)->where('status', 'active')->count())->toBe(2)
        ->and(ThesisSupervisorAssignment::query()->where('project_id', $project->id)->where('role', AdvisorType::Primary->value)->firstOrFail()->lecturer_user_id)->toBe($primary->id)
        ->and(ThesisSupervisorAssignment::query()->where('project_id', $project->id)->where('role', AdvisorType::Secondary->value)->firstOrFail()->lecturer_user_id)->toBe($secondary->id)
        ->and(MentorshipAssignment::query()->count())->toBe(0)
        ->and(ThesisProjectEvent::query()->where('event_type', 'supervisor_assigned')->count())->toBe(1)
        ->and($project->fresh()->phase)->toBe('research');

    Notification::assertSentTo($student, RealtimeNotification::class, function (RealtimeNotification $notification, array $channels) use ($student, $primary, $secondary): bool {
        $data = $notification->toArray($student);

        return in_array('broadcast', $channels, true)
            && $data['title'] === 'Pembimbing ditetapkan'
            && str_contains($data['description'], $primary->name)
            && str_contains($data['description'], $secondary->name)
            && $data['preferenceKey'] === 'statusTugasAkhir';
    });
});

test('admin service rejects supervisor assignment when concentration does not match', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $student = User::factory()->asMahasiswa()->create();
    $lecturer = User::factory()->asDosen()->create();
    $prodi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer', 'slug' => 'ilkom', 'concentrations' => ['Jaringan', 'Computer Vision']]);

    MahasiswaProfile::query()->create([
        'user_id' => $student->id,
        'nim' => '2210510400',
        'program_studi_id' => $prodi->id,
        'concentration' => 'Jaringan',
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    DosenProfile::query()->create([
        'user_id' => $lecturer->id,
        'nik' => '7301010101013333',
        'program_studi_id' => $prodi->id,
        'concentration' => 'Computer Vision',
        'supervision_quota' => 10,
        'is_active' => true,
    ]);

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'research',
        'state' => 'active',
        'started_at' => now()->subDays(7),
        'created_by' => $student->id,
    ]);

    expect(fn() => app(ThesisProjectAdminService::class)->assignSupervisors(
        project: $project,
        assignedBy: $admin->id,
        primaryLecturerUserId: $lecturer->id,
        secondaryLecturerUserId: null,
        notes: 'Harus ditolak karena beda konsentrasi.',
    ))->toThrow(\RuntimeException::class);
});

test('admin service respects configurable lecturer quota when assigning supervisors', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $prodi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer', 'slug' => 'ilkom', 'concentrations' => ['Jaringan']]);
    $lecturer = User::factory()->asDosen()->create();

    DosenProfile::query()->create([
        'user_id' => $lecturer->id,
        'nik' => '7301010101014444',
        'program_studi_id' => $prodi->id,
        'concentration' => 'Jaringan',
        'supervision_quota' => 1,
        'is_active' => true,
    ]);

    $existingStudent = User::factory()->asMahasiswa()->create();
    MahasiswaProfile::query()->create([
        'user_id' => $existingStudent->id,
        'nim' => '2210510401',
        'program_studi_id' => $prodi->id,
        'concentration' => 'Jaringan',
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $existingProject = ThesisProject::query()->create([
        'student_user_id' => $existingStudent->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'research',
        'state' => 'active',
        'started_at' => now()->subDays(14),
        'created_by' => $existingStudent->id,
    ]);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $existingProject->id,
        'lecturer_user_id' => $lecturer->id,
        'role' => AdvisorType::Primary->value,
        'status' => 'active',
        'assigned_by' => $admin->id,
        'started_at' => now()->subDays(13),
    ]);

    $newStudent = User::factory()->asMahasiswa()->create();
    MahasiswaProfile::query()->create([
        'user_id' => $newStudent->id,
        'nim' => '2210510402',
        'program_studi_id' => $prodi->id,
        'concentration' => 'Jaringan',
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $newProject = ThesisProject::query()->create([
        'student_user_id' => $newStudent->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'research',
        'state' => 'active',
        'started_at' => now()->subDays(3),
        'created_by' => $newStudent->id,
    ]);

    expect(fn() => app(ThesisProjectAdminService::class)->assignSupervisors(
        project: $newProject,
        assignedBy: $admin->id,
        primaryLecturerUserId: $lecturer->id,
        secondaryLecturerUserId: null,
        notes: 'Harus ditolak karena kuota penuh.',
    ))->toThrow(\RuntimeException::class);
});

test('admin service schedules and completes sidang with revision', function (): void {
    Notification::fake();

    $admin = User::factory()->asAdmin()->create();
    $student = User::factory()->asMahasiswa()->create();
    $primarySupervisor = User::factory()->asDosen()->create();
    $secondarySupervisor = User::factory()->asDosen()->create();
    $examiner = User::factory()->asDosen()->create();
    $prodi = ProgramStudi::factory()->create(['name' => 'Sistem Informasi']);

    MahasiswaProfile::query()->create([
        'user_id' => $student->id,
        'nim' => '2210510301',
        'program_studi_id' => $prodi->id,
        'concentration' => 'Data Science',
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    foreach ([$primarySupervisor, $secondarySupervisor, $examiner] as $lecturer) {
        DosenProfile::query()->create([
            'user_id' => $lecturer->id,
            'nik' => fake()->numerify('730101010101####'),
            'program_studi_id' => $prodi->id,
            'concentration' => 'Data Science',
            'supervision_quota' => 10,
            'is_active' => true,
        ]);
    }

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'research',
        'state' => 'active',
        'started_at' => now()->subDays(30),
    ]);

    ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Analisis Dashboard Akademik Interaktif',
        'status' => 'approved',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subDays(28),
        'decided_by_user_id' => $admin->id,
        'decided_at' => now()->subDays(27),
    ]);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $project->id,
        'lecturer_user_id' => $primarySupervisor->id,
        'role' => AdvisorType::Primary->value,
        'status' => 'active',
        'assigned_by' => $admin->id,
        'started_at' => now()->subDays(25),
    ]);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $project->id,
        'lecturer_user_id' => $secondarySupervisor->id,
        'role' => AdvisorType::Secondary->value,
        'status' => 'active',
        'assigned_by' => $admin->id,
        'started_at' => now()->subDays(25),
    ]);

    app(ThesisProjectAdminService::class)->scheduleSidang(
        project: $project,
        createdBy: $admin->id,
        scheduledFor: now()->addDays(10)->format('Y-m-d H:i:s'),
        location: 'Ruang Sidang Proyek',
        mode: 'offline',
        panelUserIds: [$primarySupervisor->id, $secondarySupervisor->id, $examiner->id],
        notes: 'Sidang tahap akhir.',
    );

    $scheduledSidang = ThesisDefense::query()->where('project_id', $project->id)->where('type', 'sidang')->firstOrFail();

    expect($scheduledSidang->examiners()->count())->toBe(3)
        ->and($scheduledSidang->examiners()->where('role', 'primary_supervisor')->exists())->toBeTrue()
        ->and($scheduledSidang->examiners()->where('role', 'secondary_supervisor')->exists())->toBeTrue()
        ->and($scheduledSidang->examiners()->where('role', 'examiner')->exists())->toBeTrue();

    $scheduledSidang->examiners()->update([
        'decision' => 'pass_with_revision',
        'score' => 84,
        'decided_at' => now(),
    ]);

    $scheduledSidang->forceFill([
        'status' => 'awaiting_finalization',
        'result' => 'pending',
    ])->save();

    app(ThesisProjectAdminService::class)->completeSidang(
        project: $project->fresh(),
        decidedBy: $admin->id,
        result: 'pass_with_revision',
        notes: 'Sidang diterima dengan revisi minor.',
        revisionNotes: 'Rapikan format daftar pustaka.',
        revisionDueAt: now()->addDays(17)->format('Y-m-d H:i:s'),
    );

    $sidang = ThesisDefense::query()->where('project_id', $project->id)->where('type', 'sidang')->firstOrFail();

    expect($sidang->status)->toBe('completed')
        ->and($sidang->result)->toBe('pass_with_revision')
        ->and(ThesisRevision::query()->where('project_id', $project->id)->count())->toBe(1)
        ->and(ThesisProjectEvent::query()->where('event_type', 'sidang_scheduled')->count())->toBe(1)
        ->and(ThesisProjectEvent::query()->where('event_type', 'sidang_completed')->count())->toBe(1)
        ->and($project->fresh()->phase)->toBe('sidang')
        ->and($project->fresh()->state)->toBe('active');

    Notification::assertSentTo($student, RealtimeNotification::class, function (RealtimeNotification $notification, array $channels) use ($student): bool {
        $data = $notification->toArray($student);

        return in_array('database', $channels, true)
            && $data['title'] === 'Sidang dijadwalkan'
            && str_contains($data['description'], 'Ruang Sidang Proyek')
            && $data['preferenceKey'] === 'statusTugasAkhir';
    });

    Notification::assertSentTo($student, RealtimeNotification::class, function (RealtimeNotification $notification, array $channels) use ($student): bool {
        $data = $notification->toArray($student);

        return in_array('broadcast', $channels, true)
            && $data['title'] === 'Sidang selesai dengan revisi'
            && $data['description'] === 'Sidang diterima dengan revisi minor.'
            && $data['preferenceKey'] === 'statusTugasAkhir';
    });
});

test('admin service finalizes sempro as failed without opening revisions', function (): void {
    Notification::fake();

    $admin = User::factory()->asAdmin()->create();
    $student = User::factory()->asMahasiswa()->create();
    $examinerOne = User::factory()->asDosen()->create();
    $examinerTwo = User::factory()->asDosen()->create();
    $prodi = ProgramStudi::factory()->create(['name' => 'Teknik Informatika']);

    MahasiswaProfile::query()->create([
        'user_id' => $student->id,
        'nim' => '2210510399',
        'program_studi_id' => $prodi->id,
        'concentration' => 'Artificial Intelligence',
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    foreach ([$examinerOne, $examinerTwo] as $lecturer) {
        DosenProfile::query()->create([
            'user_id' => $lecturer->id,
            'nik' => fake()->numerify('730101010101####'),
            'program_studi_id' => $prodi->id,
            'concentration' => 'Artificial Intelligence',
            'supervision_quota' => 10,
            'is_active' => true,
        ]);
    }

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'sempro',
        'state' => 'active',
        'started_at' => now()->subDays(14),
        'created_by' => $student->id,
    ]);

    ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Evaluasi Sistem Penilaian Adaptif',
        'status' => 'approved',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subDays(13),
        'decided_by_user_id' => $admin->id,
        'decided_at' => now()->subDays(12),
    ]);

    app(ThesisProjectAdminService::class)->scheduleSempro(
        project: $project,
        scheduledBy: $admin->id,
        scheduledFor: now()->addDays(4)->format('Y-m-d H:i:s'),
        location: 'Ruang Sempro 2',
        mode: 'offline',
        examinerUserIds: [$examinerOne->id, $examinerTwo->id],
    );

    $sempro = ThesisDefense::query()->where('project_id', $project->id)->where('type', 'sempro')->firstOrFail();

    $sempro->examiners()->update([
        'decision' => 'fail',
        'score' => 55,
        'decided_at' => now(),
    ]);

    $sempro->forceFill([
        'status' => 'awaiting_finalization',
        'result' => 'pending',
    ])->save();

    app(ThesisProjectAdminService::class)->finalizeSempro(
        project: $project->fresh(),
        decidedBy: $admin->id,
        result: 'fail',
        notes: 'Proposal belum memenuhi standar kelulusan sempro.',
    );

    expect($sempro->fresh()->status)->toBe('completed')
        ->and($sempro->fresh()->result)->toBe('fail')
        ->and(ThesisRevision::query()->where('project_id', $project->id)->count())->toBe(0)
        ->and(ThesisProjectEvent::query()->where('event_type', 'sempro_failed')->count())->toBe(1)
        ->and($project->fresh()->phase)->toBe('sempro')
        ->and($project->fresh()->state)->toBe('active');

    Notification::assertSentTo($student, RealtimeNotification::class, function (RealtimeNotification $notification, array $channels) use ($student): bool {
        $data = $notification->toArray($student);

        return in_array('database', $channels, true)
            && $data['title'] === 'Sempro dinyatakan tidak lulus'
            && $data['description'] === 'Proposal belum memenuhi standar kelulusan sempro.'
            && $data['preferenceKey'] === 'statusTugasAkhir';
    });
});
