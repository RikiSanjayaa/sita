<?php

use App\Enums\AppRole;
use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisRevision;
use App\Models\ThesisRevisionRound;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use App\Services\ThesisDefenseRevisionService;
use App\Services\ThesisProjectAdminService;
use App\Services\ThesisRevisionRoundService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

function structuredRevisionExam(string $type = 'sidang'): array
{
    $programStudi = ProgramStudi::query()->firstOrCreate(
        ['slug' => 'ilmu-komputer-revisi'],
        ['name' => 'Ilmu Komputer Revisi', 'concentrations' => [ProgramStudi::DEFAULT_GENERAL_CONCENTRATION]],
    );

    $student = createUserWithRole(AppRole::Mahasiswa->value);
    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $programStudi->id,
        'concentration' => ProgramStudi::DEFAULT_GENERAL_CONCENTRATION,
        'is_active' => true,
    ]);

    $lecturers = User::factory()->asDosen()->count(2)->create();
    foreach ($lecturers as $lecturer) {
        DosenProfile::factory()->create([
            'user_id' => $lecturer->id,
            'program_studi_id' => $programStudi->id,
            'concentration' => ProgramStudi::DEFAULT_GENERAL_CONCENTRATION,
            'is_active' => true,
        ]);
    }

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
        'title_id' => 'Sistem Revisi Terstruktur',
        'title_en' => 'Structured Revision System',
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
        'status' => 'scheduled',
        'result' => 'pending',
        'scheduled_for' => now()->addDays(2),
        'location' => 'Ruang Uji',
        'mode' => 'offline',
    ]);

    $examiners = $lecturers->values()->map(fn (User $lecturer, int $index): ThesisDefenseExaminer => ThesisDefenseExaminer::query()->create([
        'defense_id' => $defense->id,
        'lecturer_user_id' => $lecturer->id,
        'role' => 'examiner',
        'order_no' => $index + 1,
        'decision' => 'pending',
        'assigned_by' => $lecturer->id,
    ]));

    $admin = User::factory()->asAdmin()->create();

    return [$defense, $lecturers->all(), $examiners->all(), $project, $admin];
}

function structuredRevisionItems(): array
{
    return [
        ['location' => 'Bab 3, hlm. 42', 'description' => 'Perjelas metode sampling.', 'priority' => 'major'],
        ['location' => '', 'description' => 'Rapikan daftar pustaka.', 'priority' => ''],
    ];
}

function postDecision($test, User $lecturer, ThesisDefense $defense, array $payload)
{
    return $test->actingAs($lecturer)
        ->from(route('dosen.seminar-proposal'))
        ->post(route('dosen.seminar-proposal.decision', $defense), $payload);
}

test('sidang pass_with_revision needs a note or at least one item', function (array $payload, array|string|null $errors): void {
    [$defense, [$lecturer]] = structuredRevisionExam();

    $response = postDecision($this, $lecturer, $defense, ['decision' => 'pass_with_revision', 'score' => 80] + $payload);

    if ($errors === null) {
        $response->assertSessionHasNoErrors();

        return;
    }

    $response->assertSessionHasErrors($errors);
})->with([
    'nothing to revise' => [[], 'revision_notes'],
    'note only' => [['revision_notes' => 'Perbaiki bab 3.'], null],
    'items only' => [['revision_items' => [['description' => 'Perjelas metode.']]], null],
    'item without description' => [['revision_items' => [['location' => 'Bab 1', 'description' => '']]], ['revision_items.0.description']],
    'unknown priority' => [['revision_items' => [['description' => 'x', 'priority' => 'urgent']]], ['revision_items.0.priority']],
    'location too long' => [['revision_items' => [['description' => 'x', 'location' => str_repeat('a', 256)]]], ['revision_items.0.location']],
    'too many items' => [['revision_items' => array_fill(0, 51, ['description' => 'x'])], ['revision_items']],
]);

test('annotated manuscript must be a pdf within the size limit', function (UploadedFile $file, bool $valid): void {
    Storage::fake('public');
    [$defense, [$lecturer]] = structuredRevisionExam();

    $response = postDecision($this, $lecturer, $defense, [
        'decision' => 'pass_with_revision',
        'score' => 80,
        'revision_notes' => 'Lihat naskah.',
        'revision_annotation' => $file,
    ]);

    if ($valid) {
        $response->assertSessionHasNoErrors();
    } else {
        $response->assertSessionHasErrors('revision_annotation');
    }
})->with([
    'pdf' => fn () => [UploadedFile::fake()->create('naskah.pdf', 200, 'application/pdf'), true],
    'word file' => fn () => [UploadedFile::fake()->create('naskah.docx', 200), false],
    'oversized pdf' => fn () => [UploadedFile::fake()->create('besar.pdf', 21000, 'application/pdf'), false],
]);

test('sempro pass with revision items becomes pass_with_revision', function (): void {
    [$defense, [$lecturer], [$examiner]] = structuredRevisionExam('sempro');

    postDecision($this, $lecturer, $defense, [
        'decision' => 'pass',
        'revision_items' => structuredRevisionItems(),
    ])->assertSessionHasNoErrors();

    expect($examiner->fresh()->decision)->toBe('pass_with_revision')
        ->and($examiner->fresh()->revision_items)->toHaveCount(2)
        ->and($examiner->fresh()->revision_items[1])->toMatchArray(['location' => null, 'priority' => null]);
});

test('decision stores items and the annotated manuscript, and fail discards them', function (): void {
    Storage::fake('public');
    [$defense, [$first, $second], [$firstExaminer, $secondExaminer]] = structuredRevisionExam();

    postDecision($this, $first, $defense, [
        'decision' => 'pass_with_revision',
        'score' => 80,
        'revision_items' => structuredRevisionItems(),
        'revision_annotation' => UploadedFile::fake()->create('naskah.pdf', 200, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    postDecision($this, $second, $defense, [
        'decision' => 'fail',
        'score' => 40,
        'revision_items' => structuredRevisionItems(),
        'revision_annotation' => UploadedFile::fake()->create('naskah.pdf', 200, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $document = ThesisDocument::query()->where('kind', 'revision_annotation')->sole();

    expect($firstExaminer->fresh()->revision_items)->toHaveCount(2)
        ->and($secondExaminer->fresh()->revision_items)->toBeNull()
        ->and($document->uploaded_by_user_id)->toBe($first->id)
        ->and($document->defense_id)->toBe($defense->id)
        ->and($document->revision_id)->toBeNull();
    Storage::disk('public')->assertExists($document->storage_path);
});

test('finalising the exam creates revision, round one, items and links the annotation', function (): void {
    Storage::fake('public');
    [$defense, [$first, $second], , $project, $admin] = structuredRevisionExam();

    postDecision($this, $first, $defense, [
        'decision' => 'pass_with_revision',
        'score' => 80,
        'revision_notes' => 'Ringkasan: perbaiki metode.',
        'revision_items' => structuredRevisionItems(),
        'revision_annotation' => UploadedFile::fake()->create('naskah.pdf', 200, 'application/pdf'),
    ]);
    postDecision($this, $second, $defense, ['decision' => 'pass_with_revision', 'score' => 78, 'revision_notes' => 'Cek ejaan.']);

    app(ThesisProjectAdminService::class)->completeSidang(
        $project,
        $admin->id,
        'pass_with_revision',
        'Lulus dengan syarat.',
        'Perbaiki sesuai penguji.',
        now()->addWeeks(2)->toDateString(),
    );

    $revisions = ThesisRevision::query()->where('defense_id', $defense->id)->orderBy('id')->get();
    expect($revisions)->toHaveCount(2);

    [$mine, $other] = [$revisions[0], $revisions[1]];

    expect($mine->requested_by_user_id)->toBe($first->id)
        ->and($mine->status)->toBe('open')
        ->and($mine->notes)->toBe('Ringkasan: perbaiki metode.')
        ->and($mine->rounds)->toHaveCount(1)
        ->and($mine->rounds[0])->round_no->toBe(1)->status->toBe('awaiting_student')
        ->and($mine->items->pluck('description')->all())->toBe(['Perjelas metode sampling.', 'Rapikan daftar pustaka.'])
        ->and($mine->items->pluck('priority')->all())->toBe(['major', null])
        ->and($mine->items->pluck('sort_order')->all())->toBe([0, 1])
        ->and($mine->items->pluck('status')->unique()->all())->toBe(['open'])
        ->and($mine->documents)->toHaveCount(1)
        ->and($mine->documents[0])->kind->toBe('revision_annotation')->version_no->toBe(1)
        // An examiner without items still gets one item, built from the note.
        ->and($other->items->pluck('description')->all())->toBe(['Cek ejaan.'])
        ->and($other->documents)->toHaveCount(0);
});

test('refinalising replaces round one instead of duplicating items', function (): void {
    [$defense, [$first]] = structuredRevisionExam();
    $revision = ThesisRevision::factory()->create(['project_id' => $defense->project_id, 'defense_id' => $defense->id, 'requested_by_user_id' => $first->id]);
    $service = app(ThesisRevisionRoundService::class);

    $service->openInitialRound($revision, structuredRevisionItems());
    $service->openInitialRound($revision, [['description' => 'Hanya satu.']]);

    expect($revision->rounds()->count())->toBe(1)
        ->and($revision->items()->pluck('description')->all())->toBe(['Hanya satu.']);
});

test('aggregate revision status follows the latest round', function (string $roundStatus, string $expected): void {
    $revision = ThesisRevision::factory()->create(['status' => 'open']);
    ThesisRevisionRound::factory()->create(['revision_id' => $revision->id, 'status' => $roundStatus]);

    app(ThesisRevisionRoundService::class)->syncStatus($revision);

    expect($revision->fresh()->status)->toBe($expected);
})->with([
    'awaiting student' => ['awaiting_student', 'open'],
    'awaiting review' => ['awaiting_review', 'submitted'],
    'closed' => ['closed', 'resolved'],
]);

test('lecturer verification accepting every item closes the round', function (): void {
    [$defense, [$first], , $project] = structuredRevisionExam();
    $defense->forceFill(['status' => 'completed', 'result' => 'pass_with_revision'])->save();
    ThesisDefenseExaminer::query()->where('defense_id', $defense->id)->where('lecturer_user_id', $first->id)->update(['decision' => 'pass_with_revision']);

    $revision = ThesisRevision::factory()->create(['project_id' => $project->id, 'defense_id' => $defense->id, 'requested_by_user_id' => $first->id]);
    app(ThesisRevisionRoundService::class)->openInitialRound($revision, structuredRevisionItems());

    $service = app(ThesisRevisionRoundService::class);
    $round = $service->currentRound($revision);
    $revision->items()->update(['status' => 'addressed', 'student_response' => 'Sudah.']);
    $round->forceFill(['status' => 'awaiting_review'])->save();

    app(ThesisDefenseRevisionService::class)->verify(
        $first,
        $revision,
        $revision->items()->pluck('id')->mapWithKeys(fn (int $id): array => [$id => ['decision' => 'accepted']])->all(),
    );

    expect($revision->fresh()->status)->toBe('resolved')
        ->and($revision->rounds()->sole()->status)->toBe('closed')
        ->and($revision->rounds()->sole()->reviewed_at)->not->toBeNull()
        ->and($revision->items()->pluck('status')->unique()->all())->toBe(['accepted'])
        ->and($revision->items()->first()->verified_by_user_id)->toBe($first->id);
});

test('admin closing defense revisions closes rounds and items too', function (): void {
    [$defense, [$first], , $project, $admin] = structuredRevisionExam('sempro');
    $defense->forceFill(['status' => 'awaiting_finalization'])->save();

    $revision = ThesisRevision::factory()->create(['project_id' => $project->id, 'defense_id' => $defense->id, 'requested_by_user_id' => $first->id]);
    app(ThesisRevisionRoundService::class)->openInitialRound($revision, structuredRevisionItems());

    app(ThesisProjectAdminService::class)->finalizeSempro($project, $admin->id, 'pass', 'Tanpa revisi.');

    expect($revision->fresh()->status)->toBe('resolved')
        ->and($revision->rounds()->sole()->status)->toBe('closed')
        ->and($revision->items()->pluck('status')->unique()->all())->toBe(['accepted']);
});

test('student submitting the revised proposal moves the round to awaiting review', function (): void {
    [$defense, [$first], , $project] = structuredRevisionExam('sempro');
    $revision = ThesisRevision::factory()->create(['project_id' => $project->id, 'defense_id' => $defense->id, 'requested_by_user_id' => $first->id]);
    app(ThesisRevisionRoundService::class)->openInitialRound($revision, structuredRevisionItems());

    app(ThesisRevisionRoundService::class)->markSubmitted($revision, now());

    expect($revision->fresh()->status)->toBe('submitted')
        ->and($revision->rounds()->sole())->status->toBe('awaiting_review')->student_submitted_at->not->toBeNull();
});

test('backfill gives every existing revision one round and one item from its notes', function (): void {
    $project = ThesisProject::factory()->create();
    $reviewer = User::factory()->asDosen()->create();
    $resolvedAt = now()->subDay();

    $open = ThesisRevision::factory()->create(['project_id' => $project->id, 'status' => 'open', 'notes' => 'Catatan terbuka.']);
    $submitted = ThesisRevision::factory()->create(['project_id' => $project->id, 'status' => 'submitted', 'notes' => 'Catatan dikirim.', 'submitted_at' => now()->subHours(2)]);
    $resolved = ThesisRevision::factory()->create([
        'project_id' => $project->id,
        'status' => 'resolved',
        'notes' => 'Catatan selesai.',
        'resolved_at' => $resolvedAt,
        'resolved_by_user_id' => $reviewer->id,
    ]);
    // Already structured: must be left alone.
    $structured = ThesisRevision::factory()->create(['project_id' => $project->id, 'notes' => 'Sudah terstruktur.']);
    app(ThesisRevisionRoundService::class)->openInitialRound($structured, [['description' => 'A'], ['description' => 'B']]);

    $migration = require database_path('migrations/2026_10_26_090100_backfill_thesis_revision_rounds_and_items.php');
    $migration->up();
    $migration->up();

    $expectations = [
        [$open, 'awaiting_student', 'open', 'Catatan terbuka.'],
        [$submitted, 'awaiting_review', 'addressed', 'Catatan dikirim.'],
        [$resolved, 'closed', 'accepted', 'Catatan selesai.'],
    ];

    foreach ($expectations as [$revision, $roundStatus, $itemStatus, $notes]) {
        $round = $revision->rounds()->sole();
        $item = $revision->items()->sole();

        expect($round->round_no)->toBe(1)
            ->and($round->status)->toBe($roundStatus)
            ->and($item->description)->toBe($notes)
            ->and($item->status)->toBe($itemStatus)
            ->and($item->round_opened)->toBe(1)
            ->and($revision->fresh()->notes)->toBe($notes);
    }

    expect($submitted->rounds()->sole()->student_submitted_at)->not->toBeNull()
        ->and($resolved->rounds()->sole()->reviewed_at->timestamp)->toBe($resolvedAt->timestamp)
        ->and($resolved->items()->sole()->verified_by_user_id)->toBe($reviewer->id)
        ->and($structured->items()->count())->toBe(2);
});

/**
 * A completed exam with one revision (two items) requested by the first
 * examiner; the second examiner passed without revision.
 *
 * @return array{0: ThesisRevision, 1: User, 2: User, 3: ThesisProject, 4: ThesisDefense}
 */
function revisionAwaitingStudent(string $type = 'sempro'): array
{
    [$defense, [$first, $second], , $project] = structuredRevisionExam($type);

    $defense->forceFill(['status' => 'completed', 'result' => 'pass_with_revision'])->save();
    ThesisDefenseExaminer::query()->where('defense_id', $defense->id)->where('lecturer_user_id', $first->id)->update(['decision' => 'pass_with_revision']);
    ThesisDefenseExaminer::query()->where('defense_id', $defense->id)->where('lecturer_user_id', $second->id)->update(['decision' => 'pass']);

    $revision = ThesisRevision::factory()->create([
        'project_id' => $project->id,
        'defense_id' => $defense->id,
        'requested_by_user_id' => $first->id,
    ]);
    app(ThesisRevisionRoundService::class)->openInitialRound($revision, structuredRevisionItems());

    return [$revision, $first, $project->student, $project, $defense];
}

function respondToRevision($test, User $student, ThesisRevision $revision, array $overrides = [])
{
    $responses = $revision->items()
        ->whereIn('status', ['open', 'reopened'])
        ->pluck('id')
        ->mapWithKeys(fn (int $id): array => [$id => ['addressed' => '1', 'response' => "Tanggapan {$id}.", 'location' => 'Bab 3, hlm. 50']])
        ->all();

    return $test->actingAs($student)->post(
        route('mahasiswa.tugas-akhir.revisions.respond', $revision),
        array_replace([
            'responses' => $responses,
            'manuscript' => UploadedFile::fake()->create('perbaikan.pdf', 200, 'application/pdf'),
        ], $overrides),
    );
}

function verifyRevisionItems($test, User $lecturer, ThesisRevision $revision, array $decisions, array $extra = [])
{
    $ids = $revision->items()->where('status', 'addressed')->pluck('id')->values();

    return $test->actingAs($lecturer)->post(
        route('dosen.seminar-proposal.revisions.verify', $revision),
        ['verdicts' => $ids->mapWithKeys(fn (int $id, int $index): array => [$id => $decisions[$index]])->all()] + $extra,
    );
}

test('a full two-round cycle resolves the revision and moves the project on', function (): void {
    Storage::fake('public');
    Notification::fake();
    [$revision, $lecturer, $student, $project] = revisionAwaitingStudent();

    // Round 1: the student answers both items and uploads the corrected manuscript.
    respondToRevision($this, $student, $revision)->assertSessionHasNoErrors()->assertRedirect();

    $round = $revision->rounds()->sole();
    expect($round->status)->toBe('awaiting_review')
        ->and($round->student_submitted_at)->not->toBeNull()
        ->and($round->studentDocument)->not->toBeNull()
        ->and($round->studentDocument->kind)->toBe('revision_response')
        ->and($round->studentDocument->revision_id)->toBe($revision->id)
        ->and($revision->fresh()->status)->toBe('submitted')
        ->and($revision->items()->pluck('status')->unique()->all())->toBe(['addressed'])
        ->and($revision->items()->first()->student_response_location)->toBe('Bab 3, hlm. 50');
    Notification::assertSentTo($lecturer, RealtimeNotification::class);

    // Verdict: accept the first item, reject the second and add a new one.
    verifyRevisionItems($this, $lecturer, $revision, [
        ['decision' => 'accepted'],
        ['decision' => 'reopened', 'comment' => 'Metode sampling masih belum jelas.'],
    ], ['new_items' => [['location' => 'Bab 4', 'description' => 'Tambahkan uji reliabilitas.', 'priority' => 'minor']]])
        ->assertSessionHasNoErrors();

    $revision->refresh();
    expect($revision->status)->toBe('open')
        ->and($revision->rounds()->pluck('status', 'round_no')->all())->toBe([1 => 'closed', 2 => 'awaiting_student'])
        ->and($revision->items()->pluck('status')->all())->toBe(['accepted', 'reopened', 'open'])
        ->and($revision->items()->where('status', 'reopened')->sole()->verification_comment)->toBe('Metode sampling masih belum jelas.')
        ->and($revision->items()->where('status', 'open')->sole()->round_opened)->toBe(2)
        ->and($project->fresh()->phase)->toBe('sempro');

    // Round 2: only the reopened and the new item need an answer.
    $secondResponses = $revision->items()->whereIn('status', ['open', 'reopened'])->pluck('id')
        ->mapWithKeys(fn (int $id): array => [$id => ['addressed' => '1', 'response' => 'Tanggapan putaran kedua.', 'location' => 'Bab 4, halaman 60']])->all();
    respondToRevision($this, $student, $revision, ['responses' => $secondResponses])->assertSessionHasNoErrors();
    expect($revision->rounds()->where('round_no', 2)->sole()->status)->toBe('awaiting_review');

    verifyRevisionItems($this, $lecturer, $revision, [['decision' => 'accepted'], ['decision' => 'accepted']])
        ->assertSessionHasNoErrors();

    $revision->refresh();
    expect($revision->status)->toBe('resolved')
        ->and($revision->resolved_by_user_id)->toBe($lecturer->id)
        ->and($revision->rounds()->pluck('status')->unique()->all())->toBe(['closed'])
        ->and($revision->items()->pluck('status')->unique()->all())->toBe(['accepted'])
        ->and($project->fresh()->phase)->toBe('research');

    $recordedFirstRound = $revision->rounds()->where('round_no', 1)->sole()->item_snapshot;
    $recordedSecondRound = $revision->rounds()->where('round_no', 2)->sole()->item_snapshot;
    expect($recordedFirstRound[1]['status'])->toBe('reopened')
        ->and($recordedFirstRound[1]['verification_comment'])->toBe('Metode sampling masih belum jelas.')
        ->and($recordedFirstRound[1]['student_response_location'])->toBe('Bab 3, hlm. 50')
        ->and($recordedSecondRound[1]['status'])->toBe('accepted')
        ->and($recordedSecondRound[1]['student_response'])->toBe('Tanggapan putaran kedua.')
        ->and($recordedSecondRound[1]['student_response_location'])->toBe('Bab 4, halaman 60');

    expect(ThesisProjectEvent::query()->where('project_id', $project->id)->pluck('event_type')->all())
        ->toContain('revision_submitted', 'revision_round_opened', 'revision_resolved');

    Notification::assertSentTo($student, RealtimeNotification::class, fn (RealtimeNotification $notification): bool => str_contains($notification->toArray($student)['title'], 'Putaran revisi 2'));
    Notification::assertSentTo($student, RealtimeNotification::class, fn (RealtimeNotification $notification): bool => $notification->toArray($student)['title'] === 'Revisi Sempro disetujui');
});

test('the student cannot send a revision while an item is unanswered', function (): void {
    Storage::fake('public');
    [$revision, , $student] = revisionAwaitingStudent();
    $itemIds = $revision->items()->pluck('id');

    respondToRevision($this, $student, $revision, [
        'responses' => [$itemIds[0] => ['addressed' => '1', 'response' => 'Sudah.']],
    ])->assertSessionHasErrors("responses.{$itemIds[1]}.response");

    respondToRevision($this, $student, $revision, [
        'responses' => [
            $itemIds[0] => ['addressed' => '1', 'response' => 'Sudah.'],
            $itemIds[1] => ['addressed' => '1', 'response' => ''],
        ],
    ])->assertSessionHasErrors("responses.{$itemIds[1]}.response");

    respondToRevision($this, $student, $revision, [
        'responses' => [
            $itemIds[0] => ['addressed' => '1', 'response' => 'Sudah.'],
            $itemIds[1] => ['addressed' => '0', 'response' => 'Belum.'],
        ],
    ])->assertSessionHasErrors("responses.{$itemIds[1]}.addressed");

    respondToRevision($this, $student, $revision, ['manuscript' => null])->assertSessionHasErrors('manuscript');

    expect($revision->rounds()->sole()->status)->toBe('awaiting_student')
        ->and($revision->items()->pluck('status')->unique()->all())->toBe(['open'])
        ->and(ThesisDocument::query()->where('kind', 'revision_response')->count())->toBe(0);
});

test('only the owning student can respond and only the requesting examiner can verify', function (): void {
    Storage::fake('public');
    [$revision, $lecturer, $student, $project, $defense] = revisionAwaitingStudent();
    $otherStudent = createUserWithRole(AppRole::Mahasiswa->value);
    $otherLecturer = ThesisDefenseExaminer::query()->where('defense_id', $defense->id)->where('lecturer_user_id', '!=', $lecturer->id)->firstOrFail()->lecturer;

    respondToRevision($this, $otherStudent, $revision)->assertForbidden();
    respondToRevision($this, $student, $revision)->assertSessionHasNoErrors();

    verifyRevisionItems($this, $otherLecturer, $revision, [['decision' => 'accepted'], ['decision' => 'accepted']])->assertForbidden();
    verifyRevisionItems($this, $student, $revision, [['decision' => 'accepted'], ['decision' => 'accepted']])->assertForbidden();

    expect($revision->fresh()->status)->toBe('submitted');
});

test('verification needs a verdict for every answered item and a comment on rejections', function (): void {
    Storage::fake('public');
    [$revision, $lecturer, $student] = revisionAwaitingStudent();
    respondToRevision($this, $student, $revision)->assertSessionHasNoErrors();
    $itemIds = $revision->items()->pluck('id');

    $this->actingAs($lecturer)
        ->post(route('dosen.seminar-proposal.revisions.verify', $revision), ['verdicts' => [$itemIds[0] => ['decision' => 'accepted']]])
        ->assertSessionHasErrors("verdicts.{$itemIds[1]}.decision");

    verifyRevisionItems($this, $lecturer, $revision, [['decision' => 'accepted'], ['decision' => 'reopened', 'comment' => '']])
        ->assertSessionHasErrors("verdicts.{$itemIds[1]}.comment");

    verifyRevisionItems($this, $lecturer, $revision, [['decision' => 'accepted'], ['decision' => 'accepted']], ['new_items' => [['description' => '']]])
        ->assertSessionHasErrors('new_items.0.description');

    expect($revision->fresh()->status)->toBe('submitted')
        ->and($revision->rounds()->sole()->status)->toBe('awaiting_review');
});

test('round transitions only run in the matching state', function (string $roundStatus, string $action, bool $allowed): void {
    Storage::fake('public');
    [$revision, $lecturer, $student] = revisionAwaitingStudent();
    $revision->items()->update(['status' => 'addressed', 'student_response' => 'Sudah.']);
    $revision->rounds()->sole()->forceFill(['status' => $roundStatus])->save();
    $service = app(ThesisDefenseRevisionService::class);

    $run = fn () => $action === 'respond'
        ? $service->submitResponses(
            $student,
            $revision,
            $revision->items()->pluck('id')->mapWithKeys(fn (int $id): array => [$id => ['response' => 'Lagi.']])->all(),
            UploadedFile::fake()->create('perbaikan.pdf', 10, 'application/pdf'),
        )
        : $service->verify(
            $lecturer,
            $revision,
            $revision->items()->pluck('id')->mapWithKeys(fn (int $id): array => [$id => ['decision' => 'accepted']])->all(),
        );

    if ($allowed) {
        // The student path needs unanswered items; reopen them first.
        if ($action === 'respond') {
            $revision->items()->update(['status' => 'open']);
        }
        expect($run())->not->toBeNull();

        return;
    }

    expect($run)->toThrow(RuntimeException::class);
})->with([
    'respond while awaiting student' => ['awaiting_student', 'respond', true],
    'respond while awaiting review' => ['awaiting_review', 'respond', false],
    'respond after closing' => ['closed', 'respond', false],
    'verify while awaiting review' => ['awaiting_review', 'verify', true],
    'verify while awaiting student' => ['awaiting_student', 'verify', false],
    'verify after closing' => ['closed', 'verify', false],
]);

test('round submission switches the available action from student to requesting examiner', function (): void {
    Storage::fake('public');
    [$revision, $lecturer, $student] = revisionAwaitingStudent();

    $studentActions = fn () => data_get($this->actingAs($student)->get('/mahasiswa/tugas-akhir')->assertOk()->viewData('page'), 'props.defenseHistory.sempro.0.respondableRevisionIds');
    $lecturerActions = fn () => data_get($this->actingAs($lecturer)->get(route('dosen.seminar-proposal'))->assertOk()->viewData('page'), 'props.defenses.0.verifiableRevisionIds');

    expect($studentActions())->toBe([$revision->id])
        ->and($lecturerActions())->toBe([]);

    respondToRevision($this, $student, $revision)->assertSessionHasNoErrors();

    expect($studentActions())->toBe([])
        ->and($lecturerActions())->toBe([$revision->id]);
});
