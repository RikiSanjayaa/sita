<?php

use App\Enums\AdvisorType;
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
use App\Services\ThesisProjectAdminService;

test('admin service schedules sempro from thesis project aggregate without creating legacy sempro rows', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $student = User::factory()->asMahasiswa()->create();
    $dosenA = User::factory()->asDosen()->create();
    $dosenB = User::factory()->asDosen()->create();
    $prodi = ProgramStudi::factory()->create(['name' => 'Informatika']);

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
        ->and($project->fresh()->phase)->toBe('sempro');
});

test('admin service assigns supervisors through thesis supervisor assignments only', function (): void {
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
    $admin = User::factory()->asAdmin()->create();
    $student = User::factory()->asMahasiswa()->create();
    $chair = User::factory()->asDosen()->create();
    $secretary = User::factory()->asDosen()->create();
    $examiner = User::factory()->asDosen()->create();
    $prodi = ProgramStudi::factory()->create(['name' => 'Sistem Informasi']);

    MahasiswaProfile::query()->create([
        'user_id' => $student->id,
        'nim' => '2210510301',
        'program_studi_id' => $prodi->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

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

    app(ThesisProjectAdminService::class)->scheduleSidang(
        project: $project,
        createdBy: $admin->id,
        scheduledFor: now()->addDays(10)->format('Y-m-d H:i:s'),
        location: 'Ruang Sidang Proyek',
        mode: 'offline',
        examinerAssignments: [
            'chair_user_id' => $chair->id,
            'secretary_user_id' => $secretary->id,
            'examiner_user_id' => $examiner->id,
        ],
        notes: 'Sidang tahap akhir.',
    );

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
});
