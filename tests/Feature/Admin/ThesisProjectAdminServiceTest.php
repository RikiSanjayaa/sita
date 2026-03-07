<?php

use App\Enums\ThesisSubmissionStatus;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\Sempro;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisRevision;
use App\Models\ThesisSubmission;
use App\Models\User;
use App\Services\LegacyThesisProjectBackfillService;
use App\Services\ThesisProjectAdminService;

test('admin service schedules sempro from thesis project aggregate', function (): void {
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

    $submission = ThesisSubmission::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'title_id' => 'Penjadwalan Sempro dari Proyek',
        'status' => ThesisSubmissionStatus::MenungguPersetujuan->value,
        'is_active' => true,
        'submitted_at' => now()->subDays(2),
    ]);

    app(LegacyThesisProjectBackfillService::class)->backfill($student->id);

    $project = ThesisProject::query()->where('legacy_thesis_submission_id', $submission->id)->firstOrFail();

    app(ThesisProjectAdminService::class)->scheduleSempro(
        project: $project,
        scheduledBy: $admin->id,
        scheduledFor: now()->addDays(5)->format('Y-m-d H:i:s'),
        location: 'Ruang Seminar Proyek',
        mode: 'offline',
        examinerUserIds: [$dosenA->id, $dosenB->id],
    );

    expect(Sempro::query()->count())->toBe(1)
        ->and(ThesisDefense::query()->where('type', 'sempro')->count())->toBe(1)
        ->and(ThesisProjectEvent::query()->where('event_type', 'sempro_scheduled')->count())->toBe(1)
        ->and($submission->fresh()->status)->toBe(ThesisSubmissionStatus::SemproDijadwalkan->value)
        ->and($project->fresh()->phase)->toBe('sempro');
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
