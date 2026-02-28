<?php

use App\Enums\SemproExaminerDecision;
use App\Enums\SemproStatus;
use App\Enums\ThesisSubmissionStatus;
use App\Models\Sempro;
use App\Models\SemproExaminer;
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
        'status' => ThesisSubmissionStatus::IntakeCreated->value,
        'is_active' => true,
    ]);

    $sempro = Sempro::query()->create([
        'thesis_submission_id' => $submission->id,
        'status' => SemproStatus::Draft->value,
        'created_by' => $admin->id,
    ]);

    $service = app(SemproWorkflowService::class);

    expect(fn () => $service->assignExaminers($sempro, [$dosen->id], $admin->id))
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
        'status' => ThesisSubmissionStatus::ProposalSubmitted->value,
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
        ->and($submission->fresh()->status)->toBe(ThesisSubmissionStatus::SemproScheduled->value);
});

it('approves sempro only when both examiners approved', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $student = User::factory()->asMahasiswa()->create();
    $dosenA = User::factory()->asDosen()->create();
    $dosenB = User::factory()->asDosen()->create();

    $submission = ThesisSubmission::query()->create([
        'student_user_id' => $student->id,
        'title_id' => 'Penjadwalan Sempro Otomatis',
        'status' => ThesisSubmissionStatus::SemproRevision->value,
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
        ->and($submission->fresh()->status)->toBe(ThesisSubmissionStatus::SemproApproved->value);
});

it('sends mahasiswa notifications when examiners are assigned and sempro is scheduled', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $student = User::factory()->asMahasiswa()->create();
    $dosenA = User::factory()->asDosen()->create(['name' => 'Dosen A']);
    $dosenB = User::factory()->asDosen()->create(['name' => 'Dosen B']);

    $submission = ThesisSubmission::query()->create([
        'student_user_id' => $student->id,
        'title_id' => 'Integrasi Notifikasi Sempro',
        'status' => ThesisSubmissionStatus::ProposalSubmitted->value,
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
