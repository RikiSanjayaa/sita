<?php

use App\Enums\AdvisorType;
use App\Enums\ThesisNoteVisibility;
use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipChatThreadParticipant;
use App\Models\MentorshipDocument;
use App\Models\MentorshipSchedule;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisProjectNote;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisRevision;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use App\Services\ChatMessagePayloadBuilder;
use App\Services\ThesisDefenseExaminerDecisionService;
use App\Services\ThesisProjectAdminService;
use App\Services\ThesisProjectNoteService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

const SECRET_EXAMINER_NAME = 'Penguji Rahasia Satu';
const SECRET_EXAMINER_NOTES = 'Catatan rahasia dari penguji tambahan';

/**
 * A mahasiswa with two supervisors, a passed sempro (visible examiner) and a scheduled sidang
 * whose panel is both supervisors plus one additional examiner.
 *
 * @return array{admin: User, student: User, other: User, primary: User, secondary: User, examiner: User, semproExaminer: User, project: ThesisProject, defense: ThesisDefense, thread: MentorshipChatThread}
 */
function secretSidangScenario(): array
{
    Notification::fake();

    $prodi = ProgramStudi::factory()->create();
    $admin = User::factory()->asAdmin()->create();
    $student = User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa Pemilik']);
    $other = User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa Lain']);
    $primary = User::factory()->asDosen()->create(['name' => 'Pembimbing Utama']);
    $secondary = User::factory()->asDosen()->create(['name' => 'Pembimbing Kedua']);
    $examiner = User::factory()->asDosen()->create(['name' => SECRET_EXAMINER_NAME]);
    $semproExaminer = User::factory()->asDosen()->create(['name' => 'Penguji Sempro Terlihat']);

    foreach ([$student, $other] as $mahasiswa) {
        MahasiswaProfile::factory()->create([
            'user_id' => $mahasiswa->id,
            'program_studi_id' => $prodi->id,
            'is_active' => true,
        ]);
    }

    foreach ([$primary, $secondary, $examiner, $semproExaminer] as $lecturer) {
        DosenProfile::factory()->create([
            'user_id' => $lecturer->id,
            'program_studi_id' => $prodi->id,
            'is_active' => true,
        ]);
    }

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'research',
        'state' => 'active',
        'started_at' => now()->subMonths(3),
        'created_by' => $student->id,
    ]);

    $title = ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Analisis Rahasia Penguji',
        'status' => 'approved',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subMonths(3),
        'decided_by_user_id' => $admin->id,
        'decided_at' => now()->subMonths(3),
    ]);

    foreach ([[$primary, AdvisorType::Primary], [$secondary, AdvisorType::Secondary]] as [$lecturer, $type]) {
        ThesisSupervisorAssignment::query()->create([
            'project_id' => $project->id,
            'lecturer_user_id' => $lecturer->id,
            'role' => $type->value,
            'status' => 'active',
            'assigned_by' => $admin->id,
            'started_at' => now()->subMonths(2),
        ]);
    }

    $sempro = ThesisDefense::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $title->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'completed',
        'result' => 'pass',
        'scheduled_for' => now()->subMonths(2),
        'location' => 'Ruang Sempro',
        'mode' => 'offline',
        'created_by' => $admin->id,
    ]);
    ThesisDefenseExaminer::query()->create([
        'defense_id' => $sempro->id,
        'lecturer_user_id' => $semproExaminer->id,
        'role' => 'examiner',
        'order_no' => 1,
        'decision' => 'pass',
        'assigned_by' => $admin->id,
    ]);

    $defense = app(ThesisProjectAdminService::class)->scheduleSidang(
        project: $project,
        createdBy: $admin->id,
        scheduledFor: now()->addDays(10)->setTime(9, 0)->format('Y-m-d H:i:s'),
        location: 'Ruang Sidang Rahasia',
        mode: 'offline',
        panelUserIds: [$primary->id, $secondary->id, $examiner->id],
    );

    $thread = MentorshipChatThread::query()
        ->where('student_user_id', $student->id)
        ->where('type', 'sidang')
        ->where('context_id', $defense->id)
        ->firstOrFail();

    return compact('admin', 'student', 'other', 'primary', 'secondary', 'examiner', 'semproExaminer', 'project', 'defense', 'thread');
}

/**
 * Props of an Inertia page as one JSON string, so a leaked name or id fails whichever key carries it.
 * `$except` drops props that list every user by design (the private chat directory).
 *
 * @param  array<int, string>  $except
 */
function inertiaPropsJson(User $viewer, string $routeName, array $parameters = [], array $except = []): string
{
    $response = test()->actingAs($viewer)->get(route($routeName, $parameters))->assertOk();

    return (string) json_encode(
        collect($response->viewData('page')['props'])->except($except)->all(),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
    );
}

function expectNoSecretExaminer(string $json, User $examiner): void
{
    expect($json)
        ->not->toContain($examiner->name)
        ->not->toContain('"'.$examiner->email.'"')
        ->not->toContain('/profil/'.$examiner->id.'"')
        ->not->toContain('"senderUserId":'.$examiner->id.',');
}

/**
 * Adds everything an examiner can leave behind: a score, notes, a legacy thread participant and message,
 * a document review copy, a student-visible note and a lecturer-made schedule.
 */
function leaveTracesOfSecretExaminer(array $scenario): void
{
    ['student' => $student, 'examiner' => $examiner, 'defense' => $defense, 'thread' => $thread, 'project' => $project] = $scenario;

    $defense->examiners()->where('lecturer_user_id', $examiner->id)->update([
        'decision' => 'pass',
        'score' => 91.5,
        'notes' => SECRET_EXAMINER_NOTES,
    ]);

    // Rows left over from before the rule: the examiner already sat in the thread and wrote in it.
    MentorshipChatThreadParticipant::query()->create(['thread_id' => $thread->id, 'user_id' => $examiner->id, 'role' => 'examiner']);
    MentorshipChatMessage::query()->create([
        'mentorship_chat_thread_id' => $thread->id,
        'sender_user_id' => $examiner->id,
        'message_type' => 'text',
        'message' => 'Halo dari penguji lama',
        'sent_at' => now(),
    ]);

    MentorshipDocument::factory()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $examiner->id,
        'uploaded_by_user_id' => $student->id,
        'status' => 'needs_revision',
        'revision_notes' => 'Perbaiki bab 3.',
    ]);

    ThesisProjectNote::factory()->forStudent()->create([
        'project_id' => $project->id,
        'author_user_id' => $examiner->id,
        'body' => 'Catatan untuk mahasiswa.',
    ]);

    MentorshipSchedule::factory()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $examiner->id,
        'status' => 'approved',
        'created_by_user_id' => $examiner->id,
    ]);
}

it('decides who a mahasiswa sees on a panel in one place', function (string $type, string $status, bool $visible): void {
    $defense = new ThesisDefense(['type' => $type, 'status' => $status]);

    expect($defense->examinersVisibleToStudent())->toBe($visible);
})->with([
    'sempro scheduled' => ['sempro', 'scheduled', true],
    'sempro awaiting finalization' => ['sempro', 'awaiting_finalization', true],
    'sidang draft' => ['sidang', 'draft', false],
    'sidang scheduled' => ['sidang', 'scheduled', false],
    'sidang awaiting finalization' => ['sidang', 'awaiting_finalization', false],
    'sidang cancelled' => ['sidang', 'cancelled', false],
    'sidang completed' => ['sidang', 'completed', true],
]);

it('keeps supervisors and hides additional examiners until the sidang is completed', function (): void {
    $scenario = secretSidangScenario();
    $defense = $scenario['defense']->load('examiners');

    expect($defense->examinersForStudent()->pluck('lecturer_user_id')->all())
        ->toBe([$scenario['primary']->id, $scenario['secondary']->id]);

    $defense->forceFill(['status' => 'completed'])->save();

    expect($defense->fresh('examiners')->examinersForStudent()->pluck('lecturer_user_id')->all())
        ->toBe([$scenario['primary']->id, $scenario['secondary']->id, $scenario['examiner']->id]);
});

it('selects the same secret examiners in SQL as the model rule', function (): void {
    $scenario = secretSidangScenario();

    expect(ThesisDefenseExaminer::secretLecturerIdsForStudent($scenario['student']->id))->toBe([$scenario['examiner']->id])
        ->and(ThesisDefenseExaminer::secretLecturerIdsForStudent($scenario['other']->id))->toBe([]);

    $scenario['defense']->forceFill(['status' => 'completed'])->save();

    expect(ThesisDefenseExaminer::secretLecturerIdsForStudent($scenario['student']->id))->toBe([]);
});

it('puts only the student and supervisors in the sidang thread and survives rescheduling', function (): void {
    $scenario = secretSidangScenario();
    ['thread' => $thread, 'project' => $project, 'admin' => $admin, 'examiner' => $examiner, 'primary' => $primary, 'secondary' => $secondary] = $scenario;

    $participantIds = fn(): array => MentorshipChatThreadParticipant::query()
        ->where('thread_id', $thread->id)
        ->pluck('user_id')
        ->map(fn($id): int => (int) $id)
        ->sort()
        ->values()
        ->all();

    $expected = collect([$scenario['student']->id, $primary->id, $secondary->id])->sort()->values()->all();

    expect($participantIds())->toBe($expected);

    app(ThesisProjectAdminService::class)->rescheduleSidang(
        project: $project->fresh(),
        updatedBy: $admin->id,
        scheduledFor: now()->addDays(12)->format('Y-m-d H:i:s'),
        scheduledUntil: null,
        location: 'Ruang Baru',
        mode: null,
        notes: null,
    );

    expect($participantIds())->toBe($expected);

    app(ThesisProjectAdminService::class)->scheduleSidang(
        project: $project->fresh(),
        createdBy: $admin->id,
        scheduledFor: now()->addDays(14)->format('Y-m-d H:i:s'),
        location: 'Ruang Lagi',
        mode: 'offline',
        panelUserIds: [$primary->id, $secondary->id, $examiner->id],
    );

    expect($participantIds())->toBe($expected)
        ->and(ThesisDefense::query()->where('project_id', $project->id)->where('type', 'sidang')->count())->toBe(1);
});

it('adds the additional examiners to the sidang thread when the sidang is completed', function (): void {
    $scenario = secretSidangScenario();
    ['thread' => $thread, 'project' => $project, 'admin' => $admin, 'examiner' => $examiner, 'defense' => $defense] = $scenario;

    $defense->forceFill(['status' => 'awaiting_finalization'])->save();
    $messagesBefore = $thread->messages()->count();

    app(ThesisProjectAdminService::class)->completeSidang(
        project: $project->fresh(),
        decidedBy: $admin->id,
        result: 'pass',
        notes: 'Lulus.',
    );

    $examinerParticipant = MentorshipChatThreadParticipant::query()
        ->where('thread_id', $thread->id)
        ->where('user_id', $examiner->id)
        ->first();

    expect($examinerParticipant?->role)->toBe('examiner')
        ->and(MentorshipChatThreadParticipant::query()->where('thread_id', $thread->id)->where('role', 'examiner')->count())->toBe(3)
        ->and($thread->messages()->count())->toBe($messagesBefore + 1)
        ->and($thread->messages()->latest('id')->value('message'))->toBe('Penguji Sidang ditambahkan untuk koordinasi revisi.');
});

it('sends no secret examiner to any mahasiswa page before the sidang is completed', function (): void {
    $scenario = secretSidangScenario();
    leaveTracesOfSecretExaminer($scenario);
    ['student' => $student, 'other' => $other, 'examiner' => $examiner, 'semproExaminer' => $semproExaminer] = $scenario;

    $pages = [
        'tugas-akhir' => ['mahasiswa.tugas-akhir', []],
        'dashboard' => ['mahasiswa.dashboard', []],
        'pesan' => ['mahasiswa.pesan', [], ['privateRecipients']],
        'upload-dokumen' => ['mahasiswa.upload-dokumen', []],
        'jadwal-bimbingan' => ['mahasiswa.jadwal-bimbingan', []],
        'own profile' => ['users.profile.show', ['user' => $student->id]],
        'progress page' => ['progres-mahasiswa', []],
    ];

    foreach ($pages as $label => $page) {
        [$routeName, $parameters] = $page;
        $json = inertiaPropsJson($student, $routeName, $parameters, $page[2] ?? []);
        expectNoSecretExaminer($json, $examiner);
        expect($json)->not->toContain(SECRET_EXAMINER_NOTES)->not->toContain('91.5');
    }

    // Another mahasiswa looking at the student's profile, the progress page, and the examiner's own profile.
    foreach ([
        ['users.profile.show', ['user' => $student->id]],
        ['progres-mahasiswa', []],
    ] as [$routeName, $parameters]) {
        expectNoSecretExaminer(inertiaPropsJson($other, $routeName, $parameters), $examiner);
    }

    // What stays visible: supervisors and the sempro examiner.
    $tugasAkhir = inertiaPropsJson($student, 'mahasiswa.tugas-akhir');
    expect($tugasAkhir)
        ->toContain('Pembimbing Utama')
        ->toContain($semproExaminer->name)
        ->toContain('"sidangExaminersHidden":true');
});

it('describes the hidden panel on the profile and keeps supervisors listed', function (): void {
    $scenario = secretSidangScenario();

    $json = inertiaPropsJson($scenario['other'], 'users.profile.show', ['user' => $scenario['student']->id]);

    expect($json)
        ->toContain('Pembimbing Utama')
        ->toContain('dirahasiakan sampai')
        ->toContain('"Penguji Aktif","value":"3"');
});

it('keeps the secret examiner out of the dosen profile lists a mahasiswa can open', function (): void {
    $scenario = secretSidangScenario();
    ['examiner' => $examiner, 'other' => $other, 'student' => $student] = $scenario;

    $asStudent = inertiaPropsJson($other, 'users.profile.show', ['user' => $examiner->id]);
    $asAdmin = inertiaPropsJson($scenario['admin'], 'users.profile.show', ['user' => $examiner->id]);
    $asLecturer = inertiaPropsJson($scenario['primary'], 'users.profile.show', ['user' => $examiner->id]);

    expect($asStudent)->not->toContain($student->name)
        ->and($asAdmin)->toContain($student->name)
        ->and($asLecturer)->toContain($student->name);
});

it('shows names, scores and notes once the sidang is completed', function (): void {
    $scenario = secretSidangScenario();
    leaveTracesOfSecretExaminer($scenario);
    ['student' => $student, 'examiner' => $examiner, 'project' => $project, 'admin' => $admin, 'defense' => $defense, 'other' => $other] = $scenario;

    $defense->forceFill(['status' => 'awaiting_finalization'])->save();
    app(ThesisProjectAdminService::class)->completeSidang(
        project: $project->fresh(),
        decidedBy: $admin->id,
        result: 'pass',
        notes: 'Lulus.',
    );

    $tugasAkhir = inertiaPropsJson($student, 'mahasiswa.tugas-akhir');

    expect($tugasAkhir)
        ->toContain(SECRET_EXAMINER_NAME)
        ->toContain(SECRET_EXAMINER_NOTES)
        ->toContain('91.5')
        ->toContain('"sidangExaminersHidden":false')
        ->and(inertiaPropsJson($other, 'users.profile.show', ['user' => $student->id]))->toContain(SECRET_EXAMINER_NAME)
        ->and(inertiaPropsJson($student, 'mahasiswa.pesan'))->toContain(SECRET_EXAMINER_NAME);
});

it('names a secret examiner by role in document reviews, chat messages and notes', function (): void {
    $scenario = secretSidangScenario();
    leaveTracesOfSecretExaminer($scenario);
    ['student' => $student, 'examiner' => $examiner, 'thread' => $thread, 'project' => $project] = $scenario;

    $legacy = MentorshipChatMessage::query()->where('sender_user_id', $examiner->id)->firstOrFail();
    $payload = app(ChatMessagePayloadBuilder::class)->build($legacy);

    expect($payload['author'])->toBe('Penguji Sidang')
        ->and($payload['senderUserId'])->toBeNull()
        ->and($payload['authorProfileUrl'])->toBeNull();

    expect(inertiaPropsJson($student, 'mahasiswa.upload-dokumen'))->toContain('"lecturerName":"Penguji Sidang"');

    $notes = app(ThesisProjectNoteService::class)->studentPayload($student, $project->fresh());
    expect($notes)->toHaveCount(1)
        ->and($notes[0]['authorName'])->toBe('Penguji Sidang')
        ->and($notes[0]['authorRole'])->toBe('Penguji Sidang');
});

it('does not name a secret examiner in notifications to the mahasiswa', function (): void {
    $scenario = secretSidangScenario();
    ['student' => $student, 'examiner' => $examiner, 'primary' => $primary, 'project' => $project, 'defense' => $defense] = $scenario;

    app(ThesisDefenseExaminerDecisionService::class)->submit($examiner, $defense->fresh(), [
        'decision' => 'pass',
        'score' => 88,
        'decision_notes' => 'Baik.',
        'revision_notes' => null,
    ]);
    app(ThesisDefenseExaminerDecisionService::class)->submit($primary, $defense->fresh(), [
        'decision' => 'pass',
        'score' => 80,
        'decision_notes' => 'Baik.',
        'revision_notes' => null,
    ]);

    app(ThesisProjectNoteService::class)->create($examiner, $project, ThesisNoteVisibility::Student, 'Catatan');

    $descriptions = Notification::sent($student, RealtimeNotification::class)
        ->map(fn(RealtimeNotification $notification): string => (string) json_encode($notification->toArray($student)))
        ->implode(' ');

    expect($descriptions)
        ->not->toContain(SECRET_EXAMINER_NAME)
        ->toContain('Penguji Sidang sudah mengirim nilai dan keputusan')
        ->toContain('Pembimbing Utama sudah mengirim nilai dan keputusan')
        ->toContain('Penguji Sidang menambahkan catatan');
});

it('announces a document review by a secret examiner by role only', function (): void {
    $scenario = secretSidangScenario();
    ['student' => $student, 'examiner' => $examiner, 'thread' => $thread] = $scenario;

    $copy = MentorshipDocument::factory()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $examiner->id,
        'uploaded_by_user_id' => $student->id,
        'document_group' => 'grup-uji',
        'status' => 'submitted',
    ]);
    MentorshipChatMessage::query()->create([
        'mentorship_chat_thread_id' => $thread->id,
        'sender_user_id' => $student->id,
        'related_document_id' => $copy->id,
        'message_type' => 'document_event',
        'message' => 'Mahasiswa mengunggah dokumen.',
        'sent_at' => now(),
    ]);

    $this->actingAs($examiner)
        ->post(route('dosen.dokumen-revisi.review', ['document' => $copy->id]), ['status' => 'approved'])
        ->assertRedirect();

    $announcement = MentorshipChatMessage::query()->where('sender_user_id', $examiner->id)->latest('id')->firstOrFail();

    expect($announcement->message)->not->toContain(SECRET_EXAMINER_NAME)->toContain('Penguji Sidang menandai')
        ->and(Notification::sent($student, RealtimeNotification::class)->map(
            fn(RealtimeNotification $notification): string => (string) json_encode($notification->toArray($student)),
        )->implode(' '))->not->toContain(SECRET_EXAMINER_NAME);

    expectNoSecretExaminer(inertiaPropsJson($student, 'mahasiswa.pesan', except: ['privateRecipients']), $examiner);
});

it('still notifies the secret examiner and shows the student in the dosen portal', function (): void {
    $scenario = secretSidangScenario();
    ['examiner' => $examiner, 'student' => $student] = $scenario;

    Notification::assertSentTo($examiner, RealtimeNotification::class, fn(RealtimeNotification $notification): bool => str_contains(
        (string) json_encode($notification->toArray($examiner)),
        'mahasiswa bimbingan Anda dijadwalkan',
    ));

    expect(inertiaPropsJson($examiner, 'dosen.seminar-proposal'))->toContain($student->name);
});

it('removes additional examiners from unfinished sidang threads in the migration and keeps messages', function (): void {
    $scenario = secretSidangScenario();
    leaveTracesOfSecretExaminer($scenario);
    ['thread' => $thread, 'examiner' => $examiner, 'primary' => $primary, 'student' => $student, 'defense' => $defense] = $scenario;

    // A finished sidang whose examiner must stay in its thread.
    $finished = ThesisDefense::query()->create([
        'project_id' => $defense->project_id,
        'type' => 'sidang',
        'attempt_no' => 0,
        'status' => 'completed',
        'result' => 'fail',
        'created_by' => $scenario['admin']->id,
    ]);
    ThesisDefenseExaminer::query()->create(['defense_id' => $finished->id, 'lecturer_user_id' => $examiner->id, 'role' => 'examiner', 'order_no' => 1, 'decision' => 'fail']);
    $finishedThread = MentorshipChatThread::query()->create(['student_user_id' => $student->id, 'type' => 'sidang', 'context_id' => $finished->id, 'label' => 'Sidang']);
    MentorshipChatThreadParticipant::query()->create(['thread_id' => $finishedThread->id, 'user_id' => $examiner->id, 'role' => 'examiner']);

    $migration = require database_path('migrations/2026_10_28_090000_remove_secret_sidang_examiners_from_chat_threads.php');
    $migration->up();

    $inThread = fn(MentorshipChatThread $target, User $user): bool => MentorshipChatThreadParticipant::query()
        ->where('thread_id', $target->id)
        ->where('user_id', $user->id)
        ->exists();

    expect($inThread($thread, $examiner))->toBeFalse()
        ->and($inThread($thread, $primary))->toBeTrue()
        ->and($inThread($thread, $student))->toBeTrue()
        ->and($inThread($finishedThread, $examiner))->toBeTrue()
        ->and(MentorshipChatMessage::query()->where('mentorship_chat_thread_id', $thread->id)->where('sender_user_id', $examiner->id)->exists())->toBeTrue();
});

it('keeps revision authors secret on an unfinished sidang only', function (): void {
    $scenario = secretSidangScenario();
    ['student' => $student, 'examiner' => $examiner, 'project' => $project, 'defense' => $defense] = $scenario;

    ThesisRevision::query()->create([
        'project_id' => $project->id,
        'defense_id' => $defense->id,
        'requested_by_user_id' => $examiner->id,
        'status' => 'open',
        'notes' => 'Revisi bab 4.',
    ]);

    $json = inertiaPropsJson($student, 'mahasiswa.tugas-akhir');

    expect($json)->not->toContain(SECRET_EXAMINER_NAME);
});

it('queries the defense panel once per request, not per message', function (): void {
    $scenario = secretSidangScenario();
    leaveTracesOfSecretExaminer($scenario);

    DB::enableQueryLog();
    inertiaPropsJson($scenario['student'], 'mahasiswa.pesan');
    $queries = collect(DB::getQueryLog())->pluck('query')->filter(fn(string $sql): bool => str_contains($sql, 'thesis_defense_examiners'));
    DB::disableQueryLog();

    expect($queries->count())->toBeLessThanOrEqual(6);
});
