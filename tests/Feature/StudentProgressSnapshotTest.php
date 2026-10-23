<?php

use App\Models\KaprodiAssignment;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipDocument;
use App\Models\MentorshipSchedule;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\StudentProgressSnapshot;
use Illuminate\Support\Facades\DB;

/**
 * @return array{project: ThesisProject, student: User, prodi: ProgramStudi, primary: User, secondary: User}
 */
function snapshotProject(?ProgramStudi $prodi = null, int $startedDaysAgo = 180): array
{
    $prodi ??= ProgramStudi::factory()->create();
    $student = User::factory()->asMahasiswa()->create();
    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'is_active' => true,
    ]);
    $primary = User::factory()->asDosen()->create(['name' => 'Dosen Pembimbing Satu']);
    $secondary = User::factory()->asDosen()->create(['name' => 'Dosen Pembimbing Dua']);

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'research',
        'state' => 'active',
        'started_at' => now()->subDays($startedDaysAgo),
        'created_by' => $student->id,
    ]);

    ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Judul Uji Progres',
        'status' => 'approved',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subDays(180),
    ]);

    foreach (['primary' => $primary, 'secondary' => $secondary] as $role => $lecturer) {
        ThesisSupervisorAssignment::query()->create([
            'project_id' => $project->id,
            'lecturer_user_id' => $lecturer->id,
            'role' => $role,
            'status' => 'active',
            'assigned_by' => $student->id,
            'started_at' => now()->subDays(150),
        ]);
    }

    return compact('project', 'student', 'prodi', 'primary', 'secondary');
}

function snapshotFor(ThesisProject $project, ?User $viewer, bool $detailForAll = false): array
{
    $projects = ThesisProject::query()->whereKey($project->id)->get();

    return app(StudentProgressSnapshot::class)->forProjects($projects, $viewer, $detailForAll)[$project->id];
}

function snapshotSession(User $student, User $lecturer, int $daysAgo): void
{
    MentorshipSchedule::factory()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $lecturer->id,
        'status' => 'completed',
        'requested_for' => now()->subDays($daysAgo),
        'scheduled_for' => now()->subDays($daysAgo),
        'created_at' => now()->subDays($daysAgo + 1),
        'updated_at' => now()->subDays($daysAgo),
    ]);
}

function snapshotChatFrom(User $student, User $sender, int $daysAgo): void
{
    $thread = MentorshipChatThread::factory()->create(['student_user_id' => $student->id, 'type' => 'pembimbing']);

    MentorshipChatMessage::factory()->create([
        'mentorship_chat_thread_id' => $thread->id,
        'sender_user_id' => $sender->id,
        'sent_at' => now()->subDays($daysAgo),
    ]);
}

function snapshotUpload(User $student, User $lecturer, int $daysAgo): void
{
    MentorshipDocument::factory()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $lecturer->id,
        'uploaded_by_user_id' => $student->id,
        'uploaded_by_role' => 'mahasiswa',
        'created_at' => now()->subDays($daysAgo),
        'updated_at' => now()->subDays($daysAgo),
    ]);
}

function snapshotKaprodi(ProgramStudi $prodi): User
{
    $kaprodi = User::factory()->asKaprodi()->create();
    KaprodiAssignment::factory()->create([
        'user_id' => $kaprodi->id,
        'program_studi_id' => $prodi->id,
    ]);

    return $kaprodi;
}

function snapshotDefense(ThesisProject $project, array $attributes): ThesisDefense
{
    $title = ThesisProjectTitle::query()->where('project_id', $project->id)->firstOrFail();

    return ThesisDefense::query()->create(array_merge([
        'project_id' => $project->id,
        'title_version_id' => $title->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'scheduled',
        'result' => 'pending',
        'scheduled_for' => now()->addDays(3)->setTime(13, 0),
        'location' => 'Ruang Seminar A',
        'mode' => 'offline',
    ], $attributes));
}

it('counts completed bimbingan per supervisor', function (): void {
    ['project' => $project, 'student' => $student, 'primary' => $primary, 'secondary' => $secondary] = snapshotProject();

    snapshotSession($student, $primary, 10);
    snapshotSession($student, $primary, 20);
    snapshotSession($student, $secondary, 5);

    expect(snapshotFor($project, $student)['detail']['supervisors'])->toBe([
        ['name' => 'Dosen Pembimbing Satu', 'roleLabel' => 'Pembimbing 1', 'count' => 2],
        ['name' => 'Dosen Pembimbing Dua', 'roleLabel' => 'Pembimbing 2', 'count' => 1],
    ]);
});

it('takes the last activity from student-side records only', function (string $source, ?int $daysAgo): void {
    ['project' => $project, 'student' => $student, 'primary' => $primary] = snapshotProject();

    match ($source) {
        'completed bimbingan' => snapshotSession($student, $primary, $daysAgo),
        'student chat message' => snapshotChatFrom($student, $student, $daysAgo),
        'student document upload' => snapshotUpload($student, $primary, $daysAgo),
        'lecturer chat message' => snapshotChatFrom($student, $primary, 1),
    };

    $expected = $daysAgo === null ? null : now()->subDays($daysAgo)->format('d M Y');

    expect(snapshotFor($project, $student)['detail']['lastActivityAt'])->toBe($expected);
})->with([
    'completed bimbingan' => ['completed bimbingan', 6],
    'student chat message' => ['student chat message', 4],
    'student document upload' => ['student document upload', 3],
    'lecturer chat message is not student activity' => ['lecturer chat message', null],
]);

it('uses the most recent of the student-side activities', function (): void {
    ['project' => $project, 'student' => $student, 'primary' => $primary] = snapshotProject();

    snapshotSession($student, $primary, 10);
    snapshotUpload($student, $primary, 6);
    snapshotChatFrom($student, $student, 2);

    expect(snapshotFor($project, $student)['detail']['lastActivityAt'])->toBe(now()->subDays(2)->format('d M Y'));
});

it('flags students inactive past the configured threshold', function (?int $activityDaysAgo, int $projectAgeDays, bool $expected): void {
    config(['sita.inactive_after_days' => 30]);

    ['project' => $project, 'student' => $student, 'primary' => $primary] = snapshotProject(startedDaysAgo: $projectAgeDays);

    if ($activityDaysAgo !== null) {
        snapshotSession($student, $primary, $activityDaysAgo);
    }

    expect(snapshotFor($project, $student)['detail']['isInactive'])->toBe($expected);
})->with([
    'recent session' => [2, 180, false],
    'session older than threshold' => [45, 180, true],
    'no activity, project older than threshold' => [null, 45, true],
    'no activity, new project' => [null, 2, false],
]);

it('shows supervision detail only to viewers allowed to see the project', function (string $viewer, bool $visible): void {
    $prodi = ProgramStudi::factory()->create();
    $otherProdi = ProgramStudi::factory()->create();
    ['project' => $project, 'student' => $student, 'primary' => $primary] = snapshotProject($prodi);

    $viewerUser = match ($viewer) {
        'student' => $student,
        'supervisor' => $primary,
        'admin' => User::factory()->asAdmin()->create(),
        'kaprodi of the prodi' => snapshotKaprodi($prodi),
        'kaprodi of another prodi' => snapshotKaprodi($otherProdi),
        'unrelated dosen' => User::factory()->asDosen()->create(),
        'unrelated mahasiswa' => User::factory()->asMahasiswa()->create(),
    };

    $snapshot = snapshotFor($project, $viewerUser);

    expect($snapshot['phase'])->toBe('research')
        ->and($snapshot['phaseLabel'])->not->toBe('')
        ->and($snapshot['detail'] === null)->toBe(! $visible);
})->with([
    'student' => ['student', true],
    'supervisor' => ['supervisor', true],
    'admin' => ['admin', true],
    'kaprodi of the prodi' => ['kaprodi of the prodi', true],
    'kaprodi of another prodi' => ['kaprodi of another prodi', false],
    'unrelated dosen' => ['unrelated dosen', false],
    'unrelated mahasiswa' => ['unrelated mahasiswa', false],
]);

it('shows supervision detail to a lecturer examining the project', function (): void {
    ['project' => $project] = snapshotProject();
    $examiner = User::factory()->asDosen()->create();

    $defense = snapshotDefense($project, []);
    ThesisDefenseExaminer::query()->create([
        'defense_id' => $defense->id,
        'lecturer_user_id' => $examiner->id,
        'role' => 'examiner',
        'order_no' => 1,
    ]);

    expect(snapshotFor($project, $examiner)['detail'])->not->toBeNull();
});

it('describes an upcoming defense in the status line', function (): void {
    ['project' => $project, 'student' => $student] = snapshotProject();

    snapshotDefense($project, [
        'type' => 'sidang',
        'scheduled_for' => now()->addDays(5)->setTime(13, 0),
    ]);

    expect(snapshotFor($project, $student)['detail']['statusLabel'])->toContain('dijadwalkan');
});

it('describes a required revision after a defense', function (): void {
    ['project' => $project, 'student' => $student] = snapshotProject();

    snapshotDefense($project, [
        'status' => 'completed',
        'result' => 'pass_with_revision',
        'scheduled_for' => now()->subDays(4)->setTime(13, 0),
    ]);

    expect(snapshotFor($project, $student)['detail']['statusLabel'])->toStartWith('Revisi ');
});

it('shows the mentorship ticket state only inside the detail', function (): void {
    ['project' => $project, 'student' => $student] = snapshotProject();
    ThesisProject::query()->whereKey($project->id)->update([
        'mentorship_valid_until' => \Illuminate\Support\Carbon::today(\App\Support\WitaDateTime::TIMEZONE)->subDays(3)->toDateString(),
    ]);

    expect(snapshotFor($project, $student)['detail']['ticket'])->toBe([
        'suspended' => true,
        'validUntil' => \Illuminate\Support\Carbon::today(\App\Support\WitaDateTime::TIMEZONE)->subDays(3)->toDateString(),
    ])
        ->and(snapshotFor($project, User::factory()->asDosen()->create())['detail'])->toBeNull();
});

it('reports the ticket as active while its valid-until date has not passed', function (?int $offsetDays): void {
    ['project' => $project, 'student' => $student] = snapshotProject();
    ThesisProject::query()->whereKey($project->id)->update([
        'mentorship_valid_until' => $offsetDays === null
            ? null
            : \Illuminate\Support\Carbon::today(\App\Support\WitaDateTime::TIMEZONE)->addDays($offsetDays)->toDateString(),
    ]);

    expect(snapshotFor($project, $student)['detail']['ticket']['suspended'])->toBeFalse();
})->with([
    'no date set' => [null],
    'valid until today' => [0],
    'valid for 30 more days' => [30],
]);

it('keeps the query count flat as students are added', function (): void {
    $viewer = User::factory()->asAdmin()->create();

    $addStudent = function (): void {
        ['student' => $student, 'primary' => $primary] = snapshotProject();
        snapshotSession($student, $primary, 10);
        snapshotChatFrom($student, $student, 3);
        snapshotUpload($student, $primary, 5);
    };

    $countQueries = function () use ($viewer): int {
        $projects = ThesisProject::query()->get();

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(StudentProgressSnapshot::class)->forProjects($projects, $viewer, true);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    foreach (range(1, 2) as $_) {
        $addStudent();
    }
    // Warm the viewer's lazy relations once so the baseline does not include them.
    $countQueries();
    $small = $countQueries();

    foreach (range(1, 18) as $_) {
        $addStudent();
    }

    expect($countQueries())->toBe($small);
});
