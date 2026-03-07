<?php

use App\Enums\SemproExaminerDecision;
use App\Enums\SemproStatus;
use App\Enums\ThesisSubmissionStatus;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\Sempro;
use App\Models\SemproExaminer;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisSubmission;
use App\Models\User;
use App\Services\SemproWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('requires exactly two dosen examiners when assigning sempro examiners', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $student = User::factory()->asMahasiswa()->create();
    $dosen = User::factory()->asDosen()->create();

    $submission = ThesisSubmission::query()->create([
        'student_user_id' => $student->id,
        'title_id' => 'Implementasi SITA',
        'status' => ThesisSubmissionStatus::MenungguPersetujuan->value,
        'is_active' => true,
    ]);

    $sempro = Sempro::query()->create([
        'thesis_submission_id' => $submission->id,
        'status' => SemproStatus::Draft->value,
        'created_by' => $admin->id,
    ]);

    $service = app(SemproWorkflowService::class);

    expect(fn() => $service->assignExaminers($sempro, [$dosen->id], $admin->id))
        ->toThrow(ValidationException::class);
});

it('schedules sempro only after two examiners are assigned', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $student = User::factory()->asMahasiswa()->create();
    $dosenA = User::factory()->asDosen()->create();
    $dosenB = User::factory()->asDosen()->create();

    $submission = ThesisSubmission::query()->create([
        'student_user_id' => $student->id,
        'title_id' => 'Sistem Informasi Seminar Proposal',
        'status' => ThesisSubmissionStatus::MenungguPersetujuan->value,
        'is_active' => true,
    ]);

    $sempro = Sempro::query()->create([
        'thesis_submission_id' => $submission->id,
        'status' => SemproStatus::Draft->value,
        'scheduled_for' => now()->addDays(3),
        'created_by' => $admin->id,
    ]);

    $service = app(SemproWorkflowService::class);
    $service->assignExaminers($sempro, [$dosenA->id, $dosenB->id], $admin->id);
    $service->scheduleSempro($sempro->fresh());

    expect($sempro->fresh()->status)->toBe(SemproStatus::Scheduled->value)
        ->and($submission->fresh()->status)->toBe(ThesisSubmissionStatus::SemproDijadwalkan->value);
});

it('approves sempro only when both examiners approved', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $student = User::factory()->asMahasiswa()->create();
    $dosenA = User::factory()->asDosen()->create();
    $dosenB = User::factory()->asDosen()->create();

    $submission = ThesisSubmission::query()->create([
        'student_user_id' => $student->id,
        'title_id' => 'Penjadwalan Sempro Otomatis',
        'status' => ThesisSubmissionStatus::RevisiSempro->value,
        'is_active' => true,
    ]);

    $sempro = Sempro::query()->create([
        'thesis_submission_id' => $submission->id,
        'status' => SemproStatus::RevisionOpen->value,
        'created_by' => $admin->id,
    ]);

    SemproExaminer::query()->create([
        'sempro_id' => $sempro->id,
        'examiner_user_id' => $dosenA->id,
        'examiner_order' => 1,
        'decision' => SemproExaminerDecision::Approved->value,
        'assigned_by' => $admin->id,
    ]);

    SemproExaminer::query()->create([
        'sempro_id' => $sempro->id,
        'examiner_user_id' => $dosenB->id,
        'examiner_order' => 2,
        'decision' => SemproExaminerDecision::Approved->value,
        'assigned_by' => $admin->id,
    ]);

    $service = app(SemproWorkflowService::class);
    $service->approveSempro($sempro->fresh(), $admin->id);

    expect($sempro->fresh()->status)->toBe(SemproStatus::Approved->value)
        ->and($submission->fresh()->status)->toBe(ThesisSubmissionStatus::SemproSelesai->value);
});

it('sends mahasiswa notifications when examiners are assigned and sempro is scheduled', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $student = User::factory()->asMahasiswa()->create();
    $dosenA = User::factory()->asDosen()->create(['name' => 'Dosen A']);
    $dosenB = User::factory()->asDosen()->create(['name' => 'Dosen B']);

    $submission = ThesisSubmission::query()->create([
        'student_user_id' => $student->id,
        'title_id' => 'Integrasi Notifikasi Sempro',
        'status' => ThesisSubmissionStatus::MenungguPersetujuan->value,
        'is_active' => true,
    ]);

    $sempro = Sempro::query()->create([
        'thesis_submission_id' => $submission->id,
        'status' => SemproStatus::Draft->value,
        'scheduled_for' => now()->addDays(5),
        'created_by' => $admin->id,
    ]);

    $service = app(SemproWorkflowService::class);
    $service->assignExaminers($sempro, [$dosenA->id, $dosenB->id], $admin->id);
    $service->scheduleSempro($sempro->fresh());

    expect($student->notifications()->count())->toBe(2);
});

it('creates draft sempro when assigning sempro from thesis submission for the first time', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $student = User::factory()->asMahasiswa()->create();

    $submission = ThesisSubmission::query()->create([
        'student_user_id' => $student->id,
        'title_id' => 'Pengajuan Sempro Baru',
        'status' => ThesisSubmissionStatus::MenungguPersetujuan->value,
        'is_active' => true,
    ]);

    $service = app(SemproWorkflowService::class);
    $sempro = $service->ensureSemproForSubmission($submission, $admin->id);

    expect($sempro->thesis_submission_id)->toBe($submission->id)
        ->and($sempro->status)->toBe(SemproStatus::Draft->value)
        ->and($sempro->mode)->toBe('offline')
        ->and($sempro->created_by)->toBe($admin->id)
        ->and(Sempro::query()->where('thesis_submission_id', $submission->id)->count())->toBe(1);
});

it('reuses existing sempro when assigning sempro from thesis submission again', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $anotherAdmin = User::factory()->asAdmin()->create();
    $student = User::factory()->asMahasiswa()->create();

    $submission = ThesisSubmission::query()->create([
        'student_user_id' => $student->id,
        'title_id' => 'Pengajuan Sempro Lama',
        'status' => ThesisSubmissionStatus::MenungguPersetujuan->value,
        'is_active' => true,
    ]);

    $existingSempro = Sempro::query()->create([
        'thesis_submission_id' => $submission->id,
        'status' => SemproStatus::Draft->value,
        'created_by' => $admin->id,
    ]);

    $service = app(SemproWorkflowService::class);
    $resolvedSempro = $service->ensureSemproForSubmission($submission, $anotherAdmin->id);

    expect($resolvedSempro->id)->toBe($existingSempro->id)
        ->and(Sempro::query()->where('thesis_submission_id', $submission->id)->count())->toBe(1);
});

it('syncs the thesis project aggregate when sempro workflow changes', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $student = User::factory()->asMahasiswa()->create();
    $dosenA = User::factory()->asDosen()->create();
    $dosenB = User::factory()->asDosen()->create();
    $prodi = ProgramStudi::factory()->create();

    MahasiswaProfile::query()->create([
        'user_id' => $student->id,
        'nim' => '2210510200',
        'program_studi_id' => $prodi->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $submission = ThesisSubmission::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'title_id' => 'Sinkronisasi Snapshot Sempro',
        'status' => ThesisSubmissionStatus::MenungguPersetujuan->value,
        'is_active' => true,
        'submitted_at' => now()->subDay(),
    ]);

    $service = app(SemproWorkflowService::class);
    $sempro = $service->ensureSemproForSubmission($submission, $admin->id);

    $sempro->forceFill([
        'scheduled_for' => now()->addDays(4),
        'location' => 'Ruang Seminar Sinkron',
    ])->save();

    $service->assignExaminers($sempro, [$dosenA->id, $dosenB->id], $admin->id);
    $service->scheduleSempro($sempro->fresh());

    expect(ThesisProject::query()->count())->toBe(1)
        ->and(ThesisDefense::query()->count())->toBe(1)
        ->and(ThesisDefenseExaminer::query()->count())->toBe(2)
        ->and(ThesisProject::query()->firstOrFail()->phase)->toBe('sempro');
});
