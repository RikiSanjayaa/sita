<?php

use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Models\User;
use App\Support\AcademicTerminology;

function gradingProgramStudi(): ProgramStudi
{
    return ProgramStudi::query()->firstOrCreate(
        ['slug' => 'ilmu-komputer-grading'],
        ['name' => 'Ilmu Komputer Grading', 'concentrations' => [ProgramStudi::DEFAULT_GENERAL_CONCENTRATION]],
    );
}

function gradingDefense(bool $obe, string $type, string $status = 'scheduled', string $decision = 'pending', ?float $score = null): array
{
    $programStudi = gradingProgramStudi();

    $student = User::factory()->asMahasiswa()->create();
    $profile = MahasiswaProfile::factory();
    $profile = ($obe ? $profile->obeCurriculum() : $profile)->create([
        'user_id' => $student->id,
        'program_studi_id' => $programStudi->id,
        'concentration' => ProgramStudi::DEFAULT_GENERAL_CONCENTRATION,
        'is_active' => true,
    ]);

    $lecturer = User::factory()->asDosen()->create();
    DosenProfile::factory()->create([
        'user_id' => $lecturer->id,
        'program_studi_id' => $programStudi->id,
        'concentration' => ProgramStudi::DEFAULT_GENERAL_CONCENTRATION,
        'is_active' => true,
    ]);

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $programStudi->id,
        'phase' => $type,
        'state' => 'active',
        'started_at' => now()->subWeek(),
    ]);

    $title = ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Sistem Penilaian Ujian',
        'title_en' => 'Exam Grading System',
        'proposal_summary' => 'Ringkasan.',
        'status' => 'approved',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subDays(6),
        'decided_at' => now()->subDays(5),
    ]);

    $defense = ThesisDefense::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $title->id,
        'type' => $type,
        'attempt_no' => 1,
        'status' => $status,
        'result' => $status === 'completed' ? 'pass' : 'pending',
        'scheduled_for' => $status === 'completed' ? now()->subDays(2) : now()->addDays(2),
        'location' => 'Ruang Uji',
        'mode' => 'offline',
    ]);

    $examiner = ThesisDefenseExaminer::query()->create([
        'defense_id' => $defense->id,
        'lecturer_user_id' => $lecturer->id,
        'role' => 'examiner',
        'order_no' => 1,
        'decision' => $decision,
        'score' => $score,
        'assigned_by' => $lecturer->id,
    ]);

    return [$defense, $lecturer, $examiner, $profile];
}

test('submitDecision validates score and comment per curriculum and exam type', function (bool $obe, string $type, array $payload, ?array $invalidFields): void {
    [$defense, $lecturer, $examiner] = gradingDefense($obe, $type);

    $response = $this->actingAs($lecturer)
        ->from(route('dosen.seminar-proposal'))
        ->post(route('dosen.seminar-proposal.decision', $defense), ['decision' => 'pass'] + $payload);

    if ($invalidFields === null) {
        $response->assertRedirect(route('dosen.seminar-proposal'))->assertSessionHasNoErrors();

        expect($examiner->fresh()->decision)->toBe('pass')
            ->and($examiner->fresh()->score === null ? null : (float) $examiner->fresh()->score)
            ->toBe(array_key_exists('score', $payload) ? (float) $payload['score'] : null);

        return;
    }

    $response->assertSessionHasErrors($invalidFields);
    expect($examiner->fresh()->decision)->toBe('pending');
})->with([
    'OBE sempro with score and comment' => [true, 'sempro', ['score' => 85, 'decision_notes' => 'Bagus.'], null],
    'OBE sempro without score' => [true, 'sempro', ['decision_notes' => 'Bagus.'], ['score']],
    'OBE sempro without comment' => [true, 'sempro', ['score' => 85], ['decision_notes']],
    'OBE sempro score out of range' => [true, 'sempro', ['score' => 101, 'decision_notes' => 'Bagus.'], ['score']],
    'non-OBE sempro without score' => [false, 'sempro', [], null],
    'non-OBE sempro with optional comment' => [false, 'sempro', ['decision_notes' => 'Bagus.'], null],
    'non-OBE sempro with score' => [false, 'sempro', ['score' => 85], ['score']],
    'OBE sidang with score' => [true, 'sidang', ['score' => 85], null],
    'OBE sidang without score' => [true, 'sidang', [], ['score']],
    'non-OBE sidang with score' => [false, 'sidang', ['score' => 85], null],
    'non-OBE sidang without score' => [false, 'sidang', [], ['score']],
]);

test('dosen page tells the form whether a score is required', function (bool $obe, string $type, bool $expected): void {
    [$defense, $lecturer] = gradingDefense($obe, $type);

    $page = $this->actingAs($lecturer)
        ->get(route('dosen.seminar-proposal'))
        ->assertOk()
        ->viewData('page');

    expect(data_get($page, 'props.defenses.0.scoreRequired'))->toBe($expected)
        ->and(data_get($page, 'props.defenses.0.terminology.proposalExamShort'))->toBe($obe ? 'Sinopsis' : 'Sempro');
})->with([
    'OBE sempro' => [true, 'sempro', true],
    'non-OBE sempro' => [false, 'sempro', false],
    'OBE sidang' => [true, 'sidang', true],
    'non-OBE sidang' => [false, 'sidang', true],
]);

test('public follow-up status only waits for scores the exam requires', function (bool $obe, string $type, ?float $score, ?string $expectedStatus): void {
    gradingDefense($obe, $type, status: 'completed', decision: 'pass', score: $score);

    $page = $this->get('/jadwal')->assertOk()->viewData('page');
    $items = data_get($page, 'props.followUpSchedules');

    if ($expectedStatus === null) {
        expect($items)->toHaveCount(0);

        return;
    }

    expect($items)->toHaveCount(1)
        ->and(data_get($items, '0.statusLabel'))->toBe($expectedStatus);
})->with([
    'non-OBE sempro without score is complete' => [false, 'sempro', null, null],
    'non-OBE sempro with legacy score is complete' => [false, 'sempro', 80.0, null],
    'OBE sempro without score waits for grades' => [true, 'sempro', null, 'Nilai Belum Lengkap'],
    'OBE sempro with score is complete' => [true, 'sempro', 80.0, null],
    'sidang without score waits for grades' => [false, 'sidang', null, 'Nilai Belum Lengkap'],
    'sidang with score is complete' => [false, 'sidang', 80.0, null],
]);

test('kaprodi exam rows expose whether a score is required', function (): void {
    [$defense] = gradingDefense(false, 'sempro', status: 'completed', decision: 'pass');

    expect($defense->requiresScore())->toBeFalse();

    $obe = gradingDefense(true, 'sempro')[0];
    $sidang = gradingDefense(false, 'sidang')[0];

    expect($obe->requiresScore())->toBeTrue()
        ->and($sidang->requiresScore())->toBeTrue();
});

test('student labels follow the curriculum flag', function (bool $obe, string $proposalExam, string $proposalExamShort): void {
    $student = User::factory()->asMahasiswa()->create();
    $profile = MahasiswaProfile::factory();
    ($obe ? $profile->obeCurriculum() : $profile)->create(['user_id' => $student->id, 'degree_level' => 's2']);

    $terms = AcademicTerminology::forStudent($student->fresh());

    expect($terms)
        ->proposalExam->toBe($proposalExam)
        ->proposalExamShort->toBe($proposalExamShort)
        ->finalWork->toBe('Tesis')
        ->and(AcademicTerminology::defenseLabel($terms, 'sempro'))->toBe($proposalExam);
})->with([
    'OBE' => [true, 'Ujian Sinopsis', 'Sinopsis'],
    'non-OBE' => [false, 'Seminar Proposal', 'Sempro'],
]);
