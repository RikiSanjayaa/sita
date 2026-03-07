<?php

use App\Enums\AdvisorType;
use App\Enums\AssignmentStatus;
use App\Enums\SemproExaminerDecision;
use App\Enums\SemproStatus;
use App\Enums\ThesisSubmissionStatus;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipAssignment;
use App\Models\ProgramStudi;
use App\Models\Sempro;
use App\Models\SemproExaminer;
use App\Models\SemproRevision;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisRevision;
use App\Models\ThesisSubmission;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\LegacyThesisProjectBackfillService;

test('backfill service migrates approved sempro workflow into thesis project aggregate', function () {
    $programStudi = ProgramStudi::query()->create([
        'name' => 'Informatika',
        'slug' => 'informatika',
    ]);

    $student = User::factory()->create();
    MahasiswaProfile::query()->create([
        'user_id' => $student->id,
        'nim' => '2210510001',
        'program_studi_id' => $programStudi->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $admin = User::factory()->create();
    $dosen1 = User::factory()->create();
    $dosen2 = User::factory()->create();

    $submission = ThesisSubmission::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $programStudi->id,
        'title_id' => 'Sistem Pendukung Keputusan Penentuan Topik Skripsi',
        'title_en' => 'Decision Support System for Thesis Topic Selection',
        'proposal_summary' => 'Ringkasan proposal awal.',
        'status' => ThesisSubmissionStatus::PembimbingDitetapkan->value,
        'is_active' => true,
        'submitted_at' => now()->subDays(20),
        'approved_at' => now()->subDays(15),
        'approved_by' => $admin->id,
    ]);

    $sempro = Sempro::query()->create([
        'thesis_submission_id' => $submission->id,
        'status' => SemproStatus::Approved->value,
        'scheduled_for' => now()->subDays(10),
        'location' => 'Ruang Seminar 1',
        'mode' => 'offline',
        'approved_at' => now()->subDays(9),
        'approved_by' => $admin->id,
        'created_by' => $admin->id,
    ]);

    SemproExaminer::query()->create([
        'sempro_id' => $sempro->id,
        'examiner_user_id' => $dosen1->id,
        'examiner_order' => 1,
        'decision' => SemproExaminerDecision::Approved->value,
        'decision_notes' => 'Layak lanjut.',
        'score' => 80,
        'decided_at' => now()->subDays(9),
        'assigned_by' => $admin->id,
    ]);

    SemproExaminer::query()->create([
        'sempro_id' => $sempro->id,
        'examiner_user_id' => $dosen2->id,
        'examiner_order' => 2,
        'decision' => SemproExaminerDecision::Approved->value,
        'decision_notes' => 'Setuju.',
        'score' => 82.5,
        'decided_at' => now()->subDays(9),
        'assigned_by' => $admin->id,
    ]);

    MentorshipAssignment::withoutEvents(function () use ($admin, $dosen1, $dosen2, $student): void {
        MentorshipAssignment::query()->create([
            'student_user_id' => $student->id,
            'lecturer_user_id' => $dosen1->id,
            'advisor_type' => AdvisorType::Primary->value,
            'status' => AssignmentStatus::Active->value,
            'assigned_by' => $admin->id,
            'started_at' => now()->subDays(8),
            'notes' => 'Pembimbing utama.',
        ]);

        MentorshipAssignment::query()->create([
            'student_user_id' => $student->id,
            'lecturer_user_id' => $dosen2->id,
            'advisor_type' => AdvisorType::Secondary->value,
            'status' => AssignmentStatus::Active->value,
            'assigned_by' => $admin->id,
            'started_at' => now()->subDays(8),
            'notes' => 'Pembimbing kedua.',
        ]);
    });

    $service = app(LegacyThesisProjectBackfillService::class);

    $service->backfill();
    $service->backfill();

    $project = ThesisProject::query()->firstOrFail();
    $title = ThesisProjectTitle::query()->firstOrFail();
    $defense = ThesisDefense::query()->firstOrFail();

    expect(ThesisProject::query()->count())->toBe(1)
        ->and($project->legacy_thesis_submission_id)->toBe($submission->id)
        ->and($project->phase)->toBe('research')
        ->and($project->state)->toBe('active')
        ->and(ThesisProjectTitle::query()->count())->toBe(1)
        ->and($title->status)->toBe('approved')
        ->and($title->project_id)->toBe($project->id)
        ->and(ThesisDefense::query()->count())->toBe(1)
        ->and($defense->legacy_sempro_id)->toBe($sempro->id)
        ->and($defense->type)->toBe('sempro')
        ->and($defense->status)->toBe('completed')
        ->and($defense->result)->toBe('pass')
        ->and(ThesisDefenseExaminer::query()->count())->toBe(2)
        ->and(ThesisSupervisorAssignment::query()->count())->toBe(2);
});

test('backfill service maps revision-open sempro into defense revision records', function () {
    $programStudi = ProgramStudi::query()->create([
        'name' => 'Sistem Informasi',
        'slug' => 'sistem-informasi',
    ]);

    $student = User::factory()->create();
    MahasiswaProfile::query()->create([
        'user_id' => $student->id,
        'nim' => '2210510002',
        'program_studi_id' => $programStudi->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $admin = User::factory()->create();
    $dosen1 = User::factory()->create();
    $dosen2 = User::factory()->create();

    $submission = ThesisSubmission::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $programStudi->id,
        'title_id' => 'Analisis Sentimen Ulasan Aplikasi',
        'status' => ThesisSubmissionStatus::RevisiSempro->value,
        'is_active' => true,
        'submitted_at' => now()->subDays(12),
    ]);

    $sempro = Sempro::query()->create([
        'thesis_submission_id' => $submission->id,
        'status' => SemproStatus::RevisionOpen->value,
        'scheduled_for' => now()->subDays(5),
        'location' => 'Online',
        'mode' => 'online',
        'created_by' => $admin->id,
    ]);

    SemproExaminer::query()->create([
        'sempro_id' => $sempro->id,
        'examiner_user_id' => $dosen1->id,
        'examiner_order' => 1,
        'decision' => SemproExaminerDecision::NeedsRevision->value,
        'decision_notes' => 'Perlu revisi.',
        'score' => 68,
        'decided_at' => now()->subDays(4),
        'assigned_by' => $admin->id,
    ]);

    SemproExaminer::query()->create([
        'sempro_id' => $sempro->id,
        'examiner_user_id' => $dosen2->id,
        'examiner_order' => 2,
        'decision' => SemproExaminerDecision::Approved->value,
        'decision_notes' => 'Setuju dengan revisi.',
        'score' => 75,
        'decided_at' => now()->subDays(4),
        'assigned_by' => $admin->id,
    ]);

    $revision = SemproRevision::query()->create([
        'sempro_id' => $sempro->id,
        'notes' => 'Lengkapi metodologi penelitian.',
        'status' => 'open',
        'due_at' => now()->addDays(7),
        'requested_by_user_id' => $dosen1->id,
    ]);

    app(LegacyThesisProjectBackfillService::class)->backfill($student->id);

    $project = ThesisProject::query()->where('legacy_thesis_submission_id', $submission->id)->firstOrFail();
    $defense = ThesisDefense::query()->where('legacy_sempro_id', $sempro->id)->firstOrFail();
    $newRevision = ThesisRevision::query()->where('legacy_sempro_revision_id', $revision->id)->firstOrFail();

    expect($project->phase)->toBe('sempro')
        ->and($defense->status)->toBe('completed')
        ->and($defense->result)->toBe('pass_with_revision')
        ->and($newRevision->project_id)->toBe($project->id)
        ->and($newRevision->defense_id)->toBe($defense->id)
        ->and($newRevision->status)->toBe('open');
});
