<?php

use App\Models\AdminProfile;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisRevision;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\AdminDashboardService;
use Illuminate\Support\Carbon;

test('admin dashboard shows the new overview widgets', function (): void {
    $prodi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);

    $admin = User::factory()->asAdmin()->create();
    AdminProfile::query()->create([
        'user_id' => $admin->id,
        'program_studi_id' => $prodi->id,
    ]);

    $student = User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa Dashboard']);
    MahasiswaProfile::query()->create([
        'user_id' => $student->id,
        'nim' => '2210510701',
        'program_studi_id' => $prodi->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'research',
        'state' => 'active',
        'started_at' => now()->subDays(5),
        'created_by' => $student->id,
    ]);

    ThesisRevision::query()->create([
        'project_id' => $project->id,
        'defense_id' => null,
        'requested_by_user_id' => $admin->id,
        'status' => 'open',
        'notes' => 'Butuh review dari admin.',
        'due_at' => now()->addWeek(),
    ]);

    ThesisProjectEvent::query()->create([
        'project_id' => $project->id,
        'actor_user_id' => $admin->id,
        'event_type' => 'revision_opened',
        'label' => 'Revisi dibuka',
        'description' => 'Butuh tindak lanjut.',
        'occurred_at' => now()->subHour(),
    ]);

    /** @var \Tests\TestCase $this */
    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee('Radar Operasional Admin')
        ->assertSee('Prioritas Admin')
        ->assertSee('Aktivitas Operasional 6 Bulan')
        ->assertSee('Sebaran Fase Proyek')
        ->assertSee('Antrian Proyek Prioritas')
        ->assertSee('Aktivitas Admin Terkini');
});

test('admin dashboard service prepares ratio-friendly metrics and activity trend', function (): void {
    Carbon::setTestNow('2026-03-18 10:00:00');

    try {
        $prodi = ProgramStudi::factory()->create(['name' => 'Sistem Informasi']);
        $otherProdi = ProgramStudi::factory()->create(['name' => 'Teknik Informatika']);

        $admin = User::factory()->asAdmin()->create();
        AdminProfile::query()->create([
            'user_id' => $admin->id,
            'program_studi_id' => $prodi->id,
        ]);

        $makeStudent = function (string $name, string $nim, ProgramStudi $programStudi): User {
            $student = User::factory()->asMahasiswa()->create(['name' => $name]);

            MahasiswaProfile::query()->create([
                'user_id' => $student->id,
                'nim' => $nim,
                'program_studi_id' => $programStudi->id,
                'angkatan' => 2022,
                'is_active' => true,
            ]);

            return $student;
        };

        $studentSemproGap = $makeStudent('Mahasiswa Sempro Gap', '2210510702', $prodi);
        $studentSemproReady = $makeStudent('Mahasiswa Sempro Ready', '2210510703', $prodi);
        $studentSidangGap = $makeStudent('Mahasiswa Sidang Gap', '2210510704', $prodi);
        $studentSidangReady = $makeStudent('Mahasiswa Sidang Ready', '2210510705', $prodi);
        $studentOtherProdi = $makeStudent('Mahasiswa Luar Prodi', '2210510706', $otherProdi);

        $semproGapProject = ThesisProject::query()->create([
            'student_user_id' => $studentSemproGap->id,
            'program_studi_id' => $prodi->id,
            'phase' => 'title_review',
            'state' => 'active',
            'started_at' => now()->subWeeks(2),
            'created_by' => $admin->id,
        ]);

        $semproReadyProject = ThesisProject::query()->create([
            'student_user_id' => $studentSemproReady->id,
            'program_studi_id' => $prodi->id,
            'phase' => 'sempro',
            'state' => 'active',
            'started_at' => now()->subWeeks(3),
            'created_by' => $admin->id,
        ]);

        ThesisDefense::query()->create([
            'project_id' => $semproReadyProject->id,
            'type' => 'sempro',
            'attempt_no' => 1,
            'status' => 'scheduled',
            'scheduled_for' => now()->addDays(3),
            'created_by' => $admin->id,
        ]);

        $sidangGapProject = ThesisProject::query()->create([
            'student_user_id' => $studentSidangGap->id,
            'program_studi_id' => $prodi->id,
            'phase' => 'research',
            'state' => 'active',
            'started_at' => now()->subWeek(),
            'created_by' => $admin->id,
        ]);

        ThesisRevision::query()->create([
            'project_id' => $sidangGapProject->id,
            'defense_id' => null,
            'requested_by_user_id' => $admin->id,
            'status' => 'open',
            'notes' => 'Butuh revisi pertama.',
            'due_at' => now()->subDay(),
        ]);

        ThesisRevision::query()->create([
            'project_id' => $sidangGapProject->id,
            'defense_id' => null,
            'requested_by_user_id' => $admin->id,
            'status' => 'submitted',
            'notes' => 'Butuh revisi kedua.',
            'due_at' => now()->addWeeks(2),
        ]);

        $sidangReadyProject = ThesisProject::query()->create([
            'student_user_id' => $studentSidangReady->id,
            'program_studi_id' => $prodi->id,
            'phase' => 'sidang',
            'state' => 'active',
            'started_at' => now()->subWeeks(4),
            'created_by' => $admin->id,
        ]);

        ThesisDefense::query()->create([
            'project_id' => $sidangReadyProject->id,
            'type' => 'sidang',
            'attempt_no' => 1,
            'status' => 'scheduled',
            'scheduled_for' => now()->addDays(5),
            'created_by' => $admin->id,
        ]);

        $lecturerOne = User::factory()->asDosen()->create();
        $lecturerTwo = User::factory()->asDosen()->create();

        ThesisSupervisorAssignment::query()->create([
            'project_id' => $sidangReadyProject->id,
            'lecturer_user_id' => $lecturerOne->id,
            'role' => 'pembimbing_1',
            'status' => 'active',
            'assigned_by' => $admin->id,
            'started_at' => now()->subWeeks(4),
        ]);

        ThesisSupervisorAssignment::query()->create([
            'project_id' => $sidangReadyProject->id,
            'lecturer_user_id' => $lecturerTwo->id,
            'role' => 'pembimbing_2',
            'status' => 'active',
            'assigned_by' => $admin->id,
            'started_at' => now()->subWeeks(4),
        ]);

        $otherProject = ThesisProject::query()->create([
            'student_user_id' => $studentOtherProdi->id,
            'program_studi_id' => $otherProdi->id,
            'phase' => 'research',
            'state' => 'active',
            'started_at' => now()->subWeeks(2),
            'created_by' => $admin->id,
        ]);

        ThesisProjectEvent::query()->create([
            'project_id' => $semproGapProject->id,
            'actor_user_id' => $admin->id,
            'event_type' => 'project_started',
            'label' => 'Project dimulai',
            'occurred_at' => now()->copy()->subMonths(5)->startOfMonth()->addDays(2),
        ]);

        ThesisProjectEvent::query()->create([
            'project_id' => $sidangGapProject->id,
            'actor_user_id' => $admin->id,
            'event_type' => 'revision_opened',
            'label' => 'Revisi dibuka',
            'occurred_at' => now()->copy()->startOfMonth()->addDays(1),
        ]);

        ThesisProjectEvent::query()->create([
            'project_id' => $sidangReadyProject->id,
            'actor_user_id' => $admin->id,
            'event_type' => 'defense_scheduled',
            'label' => 'Sidang dijadwalkan',
            'occurred_at' => now()->copy()->startOfMonth()->addDays(5),
        ]);

        ThesisProjectEvent::query()->create([
            'project_id' => $semproReadyProject->id,
            'actor_user_id' => $admin->id,
            'event_type' => 'sempro_scheduled',
            'label' => 'Sempro lama dijadwalkan',
            'occurred_at' => now()->copy()->subMonths(6)->startOfMonth()->addDays(3),
        ]);

        ThesisProjectEvent::query()->create([
            'project_id' => $semproReadyProject->id,
            'actor_user_id' => $admin->id,
            'event_type' => 'sempro_completed',
            'label' => 'Sempro lama selesai',
            'occurred_at' => now()->copy()->subMonths(6)->lastOfMonth()->subDays(2),
        ]);

        ThesisProjectEvent::query()->create([
            'project_id' => $semproGapProject->id,
            'actor_user_id' => $admin->id,
            'event_type' => 'legacy_review',
            'label' => 'Review lama',
            'occurred_at' => now()->copy()->subMonths(11)->startOfMonth()->addDays(4),
        ]);

        ThesisProjectEvent::query()->create([
            'project_id' => $otherProject->id,
            'actor_user_id' => $admin->id,
            'event_type' => 'ignored_other_prodi',
            'label' => 'Event prodi lain',
            'occurred_at' => now()->copy()->startOfMonth()->addDays(3),
        ]);

        $service = app(AdminDashboardService::class);

        $metrics = $service->metrics($admin);

        expect($metrics['activeProjects'])->toBe(4)
            ->and($metrics['earlyStageProjects'])->toBe(2)
            ->and($metrics['lateStageProjects'])->toBe(2)
            ->and($metrics['needsSempro'])->toBe(1)
            ->and($metrics['needsSidang'])->toBe(1)
            ->and($metrics['incompleteSupervisors'])->toBe(1)
            ->and($metrics['unassignedLateStageProjects'])->toBe(1)
            ->and($metrics['projectsWithOpenRevisions'])->toBe(1)
            ->and($metrics['overdueRevisionProjects'])->toBe(1)
            ->and($metrics['openRevisions'])->toBe(2)
            ->and($metrics['upcomingAgenda'])->toBe(2);

        $trend = $service->activityTrend($admin);

        expect($trend['labels'])->toHaveCount(6)
            ->and($trend['labels'][0])->toBe(now()->copy()->subMonths(5)->startOfMonth()->format('M Y'))
            ->and($trend['labels'][5])->toBe(now()->copy()->startOfMonth()->format('M Y'))
            ->and($trend['current'])->toBe([1, 0, 0, 0, 0, 2])
            ->and($trend['previous'])->toBe([1, 0, 0, 0, 0, 2])
            ->and($trend['currentTotal'])->toBe(3)
            ->and($trend['previousTotal'])->toBe(3);
    } finally {
        Carbon::setTestNow();
    }
});
