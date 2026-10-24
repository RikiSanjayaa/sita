<?php

use App\Models\MahasiswaProfile;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatRead;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipChatThreadParticipant;
use App\Models\MentorshipDocument;
use App\Models\MentorshipSchedule;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisRevision;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\MentorshipProgressService;
use App\Services\StudentActivityService;
use App\Services\StudentProgressSnapshot;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A completed thesis with every kind of data the archive touches. Files are real entries
 * on the fake public disk.
 *
 * @return array<string, mixed>
 */
function archiveScenario(int $completedDaysAgo = 45, ?User $student = null): array
{
    Storage::fake('public');

    $prodi = ProgramStudi::factory()->create();
    $student ??= User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa Arsip']);
    MahasiswaProfile::factory()->create(['user_id' => $student->id, 'program_studi_id' => $prodi->id, 'is_active' => true]);
    $primary = User::factory()->asDosen()->create(['name' => 'Pembimbing Satu']);
    $secondary = User::factory()->asDosen()->create(['name' => 'Pembimbing Dua']);
    $examiner = User::factory()->asDosen()->create(['name' => 'Penguji Satu']);

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'completed',
        'state' => 'completed',
        'started_at' => now()->subDays(300),
        'completed_at' => now()->subDays($completedDaysAgo),
        'created_by' => $student->id,
    ]);
    $title = ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Judul Arsip',
        'status' => 'approved',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subDays(290),
    ]);
    foreach (['primary' => $primary, 'secondary' => $secondary] as $role => $lecturer) {
        ThesisSupervisorAssignment::query()->create([
            'project_id' => $project->id,
            'lecturer_user_id' => $lecturer->id,
            'role' => $role,
            'status' => 'ended',
            'assigned_by' => $student->id,
            'started_at' => now()->subDays(250),
            'ended_at' => now()->subDays($completedDaysAgo),
        ]);
    }

    $sempro = ThesisDefense::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $title->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'completed',
        'result' => 'pass',
        'scheduled_for' => now()->subDays(200),
    ]);
    $sidang = ThesisDefense::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $title->id,
        'type' => 'sidang',
        'attempt_no' => 1,
        'status' => 'completed',
        'result' => 'pass',
        'scheduled_for' => now()->subDays($completedDaysAgo + 1),
    ]);
    $revision = ThesisRevision::query()->create([
        'project_id' => $project->id,
        'defense_id' => $sempro->id,
        'status' => 'resolved',
        'notes' => 'Perbaiki bab 2',
    ]);

    $threads = [
        'pembimbing' => MentorshipChatThread::factory()->create(['student_user_id' => $student->id, 'type' => 'pembimbing']),
        'sempro' => MentorshipChatThread::factory()->create(['student_user_id' => $student->id, 'type' => 'sempro', 'context_id' => $sempro->id]),
        'sidang' => MentorshipChatThread::factory()->create(['student_user_id' => $student->id, 'type' => 'sidang', 'context_id' => $sidang->id]),
        'private' => MentorshipChatThread::factory()->create(['student_user_id' => $student->id, 'type' => 'private', 'private_key' => $student->id.':'.$primary->id]),
    ];
    foreach ([[$threads['sempro'], $examiner, 'examiner'], [$threads['private'], $primary, 'lecturer'], [$threads['private'], $student, 'student']] as [$thread, $user, $role]) {
        MentorshipChatThreadParticipant::query()->create(['thread_id' => $thread->id, 'user_id' => $user->id, 'role' => $role]);
    }
    foreach ($threads as $thread) {
        MentorshipChatMessage::factory()->count(2)->create([
            'mentorship_chat_thread_id' => $thread->id,
            'sender_user_id' => $student->id,
            'sent_at' => now()->subDays(100),
        ]);
        MentorshipChatRead::query()->create(['mentorship_chat_thread_id' => $thread->id, 'user_id' => $primary->id, 'last_read_at' => now()]);
    }

    Storage::disk('public')->put('chat/private/1/thread/1/lampiran.pdf', 'chat');
    MentorshipChatMessage::factory()->create([
        'mentorship_chat_thread_id' => $threads['private']->id,
        'sender_user_id' => $student->id,
        'attachment_disk' => 'public',
        'attachment_path' => 'chat/private/1/thread/1/lampiran.pdf',
        'attachment_name' => 'lampiran.pdf',
        'sent_at' => now()->subDays(90),
    ]);

    foreach ([60, 50, 40] as $daysAgo) {
        MentorshipSchedule::factory()->create([
            'student_user_id' => $student->id,
            'lecturer_user_id' => $primary->id,
            'status' => 'completed',
            'requested_for' => now()->subDays($daysAgo),
            'scheduled_for' => now()->subDays($daysAgo),
            'created_at' => now()->subDays($daysAgo + 2),
            'updated_at' => now()->subDays($daysAgo),
        ]);
    }
    MentorshipSchedule::factory()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $secondary->id,
        'status' => 'completed',
        'requested_for' => now()->subDays(70),
        'scheduled_for' => now()->subDays(70),
        'created_at' => now()->subDays(72),
    ]);
    MentorshipSchedule::factory()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $primary->id,
        'status' => 'rejected',
        'created_at' => now()->subDays(80),
    ]);

    Storage::disk('public')->put("documents/mahasiswa/{$student->id}/draft-bab-1.pdf", 'draft');
    Storage::disk('public')->put("documents/mahasiswa/{$student->id}/naskah-akhir.pdf", 'final');
    Storage::disk('public')->put("documents/mahasiswa/{$student->id}/naskah-akhir-lama.pdf", 'final-old');
    Storage::disk('public')->put("documents/mahasiswa/{$student->id}/proposal.pdf", 'proposal');

    $drafts = [
        MentorshipDocument::factory()->create([
            'student_user_id' => $student->id,
            'lecturer_user_id' => $primary->id,
            'storage_disk' => 'public',
            'storage_path' => "documents/mahasiswa/{$student->id}/draft-bab-1.pdf",
            'created_at' => now()->subDays(120),
            'updated_at' => now()->subDays(110),
        ]),
        // The student's copy for the second lecturer points at the very same file.
        MentorshipDocument::factory()->create([
            'student_user_id' => $student->id,
            'lecturer_user_id' => $secondary->id,
            'storage_disk' => 'public',
            'storage_path' => "documents/mahasiswa/{$student->id}/draft-bab-1.pdf",
            'created_at' => now()->subDays(120),
            'updated_at' => now()->subDays(115),
        ]),
        // Copy of the final manuscript uploaded through the workspace.
        MentorshipDocument::factory()->create([
            'student_user_id' => $student->id,
            'lecturer_user_id' => $primary->id,
            'storage_disk' => 'public',
            'storage_path' => "documents/mahasiswa/{$student->id}/naskah-akhir.pdf",
            'created_at' => now()->subDays(60),
        ]),
    ];

    $document = static fn (string $kind, int $version, string $path): ThesisDocument => ThesisDocument::query()->create([
        'project_id' => $project->id,
        'defense_id' => $kind === 'proposal' ? $sempro->id : $sidang->id,
        'uploaded_by_user_id' => $student->id,
        'kind' => $kind,
        'version_no' => $version,
        'title' => $kind.' v'.$version,
        'storage_disk' => 'public',
        'storage_path' => $path,
        'file_name' => basename($path),
        'uploaded_at' => now()->subDays(60),
    ]);
    $final = $document('final_manuscript', 2, "documents/mahasiswa/{$student->id}/naskah-akhir.pdf");
    $finalOld = $document('final_manuscript', 1, "documents/mahasiswa/{$student->id}/naskah-akhir-lama.pdf");
    $proposal = $document('proposal', 1, "documents/mahasiswa/{$student->id}/proposal.pdf");

    return compact('prodi', 'student', 'primary', 'secondary', 'examiner', 'project', 'title', 'sempro', 'sidang', 'revision', 'threads', 'drafts', 'final', 'finalOld', 'proposal');
}

it('keeps the final data and deletes mentorship data and files of a project past the grace period', function (): void {
    ['project' => $project, 'student' => $student, 'final' => $final, 'finalOld' => $finalOld, 'proposal' => $proposal, 'sempro' => $sempro, 'sidang' => $sidang, 'revision' => $revision, 'title' => $title, 'drafts' => $drafts] = archiveScenario();

    $this->artisan('sita:archive-completed-theses')->assertSuccessful();

    $project->refresh();
    expect($project->archived_at)->not->toBeNull();

    // Deleted.
    expect(MentorshipChatThread::query()->where('student_user_id', $student->id)->count())->toBe(0)
        ->and(MentorshipChatMessage::query()->count())->toBe(0)
        ->and(MentorshipChatRead::query()->count())->toBe(0)
        ->and(MentorshipChatThreadParticipant::query()->count())->toBe(0)
        ->and(MentorshipSchedule::query()->where('student_user_id', $student->id)->count())->toBe(0)
        ->and(MentorshipDocument::query()->where('student_user_id', $student->id)->count())->toBe(0)
        ->and(ThesisDocument::query()->whereKey([$finalOld->id, $proposal->id])->count())->toBe(0);

    // Kept.
    expect(ThesisDocument::query()->whereKey($final->id)->exists())->toBeTrue()
        ->and(ThesisProjectTitle::query()->whereKey($title->id)->exists())->toBeTrue()
        ->and(ThesisDefense::query()->whereKey([$sempro->id, $sidang->id])->count())->toBe(2)
        ->and(ThesisRevision::query()->whereKey($revision->id)->exists())->toBeTrue()
        ->and(ThesisSupervisorAssignment::query()->where('project_id', $project->id)->count())->toBe(2);

    Storage::disk('public')->assertExists($final->storage_path);
    Storage::disk('public')->assertMissing("documents/mahasiswa/{$student->id}/draft-bab-1.pdf");
    Storage::disk('public')->assertMissing($finalOld->storage_path);
    Storage::disk('public')->assertMissing($proposal->storage_path);
    Storage::disk('public')->assertMissing('chat/private/1/thread/1/lampiran.pdf');
    expect($drafts)->toHaveCount(3);
});

it('removes each kind of data', function (string $kind, Closure $count): void {
    $scenario = archiveScenario();
    $before = $count($scenario);
    expect($before)->toBeGreaterThan(0);

    $this->artisan('sita:archive-completed-theses')->assertSuccessful();

    expect($count($scenario))->toBe(0);
})->with([
    'chat threads' => ['threads', fn (array $s): int => MentorshipChatThread::query()->where('student_user_id', $s['student']->id)->count()],
    'chat messages' => ['messages', fn (array $s): int => MentorshipChatMessage::query()->count()],
    'read markers' => ['reads', fn (array $s): int => MentorshipChatRead::query()->count()],
    'participants' => ['participants', fn (array $s): int => MentorshipChatThreadParticipant::query()->count()],
    'schedules' => ['schedules', fn (array $s): int => MentorshipSchedule::query()->where('student_user_id', $s['student']->id)->count()],
    'draft documents' => ['drafts', fn (array $s): int => MentorshipDocument::query()->where('student_user_id', $s['student']->id)->count()],
    'non-final thesis documents' => ['thesis documents', fn (array $s): int => ThesisDocument::query()->where('project_id', $s['project']->id)->where('id', '!=', $s['final']->id)->count()],
]);

it('keeps the latest final manuscript version and its file', function (): void {
    ['final' => $final, 'finalOld' => $finalOld] = archiveScenario();

    $this->artisan('sita:archive-completed-theses')->assertSuccessful();

    expect(ThesisDocument::query()->where('kind', 'final_manuscript')->pluck('id')->all())->toBe([$final->id]);
    Storage::disk('public')->assertExists($final->storage_path);
    Storage::disk('public')->assertMissing($finalOld->storage_path);
});

it('keeps a file that another remaining row still references', function (string $holder): void {
    ['student' => $student] = $scenario = archiveScenario();
    $path = "documents/mahasiswa/{$student->id}/draft-bab-1.pdf";

    match ($holder) {
        // The same upload recorded for another student's workspace.
        'a document of another student' => MentorshipDocument::factory()->create(['storage_disk' => 'public', 'storage_path' => $path]),
        'a thesis document of another project' => ThesisDocument::query()->create([
            'project_id' => ThesisProject::factory()->create()->id,
            'kind' => 'proposal',
            'title' => 'Naskah lain',
            'storage_disk' => 'public',
            'storage_path' => $path,
            'file_name' => 'x.pdf',
        ]),
        'a chat attachment in a thread that stays' => MentorshipChatMessage::factory()->create([
            'attachment_disk' => 'public',
            'attachment_path' => $path,
        ]),
    };

    $this->artisan('sita:archive-completed-theses')->assertSuccessful();

    Storage::disk('public')->assertExists($path);
    expect(MentorshipDocument::query()->where('student_user_id', $student->id)->count())->toBe(0);
})->with([
    'a document of another student',
    'a thesis document of another project',
    'a chat attachment in a thread that stays',
]);

it('keeps the kept final manuscript file even when a deleted draft points at it', function (): void {
    ['final' => $final, 'student' => $student] = archiveScenario();

    // The scenario already links a deleted workspace copy to the kept final file.
    expect(MentorshipDocument::query()->where('storage_path', $final->storage_path)->exists())->toBeTrue();

    $this->artisan('sita:archive-completed-theses')->assertSuccessful();

    expect(MentorshipDocument::query()->where('student_user_id', $student->id)->count())->toBe(0);
    Storage::disk('public')->assertExists($final->storage_path);
});

it('leaves projects inside the grace period untouched', function (): void {
    ['project' => $project, 'student' => $student] = archiveScenario(completedDaysAgo: 10);
    $rows = fn (): array => [
        MentorshipChatMessage::query()->count(),
        MentorshipSchedule::query()->where('student_user_id', $student->id)->count(),
        MentorshipDocument::query()->where('student_user_id', $student->id)->count(),
        ThesisDocument::query()->count(),
    ];
    $before = $rows();

    $this->artisan('sita:archive-completed-theses')->assertSuccessful();

    expect($rows())->toBe($before)
        ->and($project->refresh()->archived_at)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->not->toBeEmpty();
});

it('never archives cancelled or active projects', function (string $state): void {
    ['project' => $project] = archiveScenario();
    $project->forceFill($state === 'cancelled'
        ? ['state' => 'cancelled', 'cancelled_at' => now()->subDays(60)]
        : ['state' => 'active', 'phase' => 'research', 'completed_at' => null])->save();
    $messages = MentorshipChatMessage::query()->count();

    $this->artisan('sita:archive-completed-theses')->assertSuccessful();

    expect($project->refresh()->archived_at)->toBeNull()
        ->and(MentorshipChatMessage::query()->count())->toBe($messages);
})->with(['cancelled', 'active']);

it('only touches rows inside this project window when the student has another project', function (): void {
    ['project' => $project, 'student' => $student, 'threads' => $threads] = archiveScenario();

    $next = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $project->program_studi_id,
        'phase' => 'research',
        'state' => 'active',
        'started_at' => now()->subDays(20),
        'created_by' => $student->id,
    ]);
    $newSchedule = MentorshipSchedule::factory()->create([
        'student_user_id' => $student->id,
        'status' => 'completed',
        'created_at' => now()->subDays(5),
    ]);
    $newDocument = MentorshipDocument::factory()->create([
        'student_user_id' => $student->id,
        'created_at' => now()->subDays(4),
    ]);
    $newMessage = MentorshipChatMessage::factory()->create([
        'mentorship_chat_thread_id' => $threads['pembimbing']->id,
        'sender_user_id' => $student->id,
        'sent_at' => now()->subDays(3),
    ]);

    $this->artisan('sita:archive-completed-theses')->assertSuccessful();

    expect($project->refresh()->archived_at)->not->toBeNull()
        ->and($next->refresh()->archived_at)->toBeNull()
        ->and(MentorshipSchedule::query()->whereKey($newSchedule->id)->exists())->toBeTrue()
        ->and(MentorshipDocument::query()->whereKey($newDocument->id)->exists())->toBeTrue()
        ->and(MentorshipChatMessage::query()->pluck('id')->all())->toBe([$newMessage->id])
        // The bimbingan thread survives because it still holds the new project's message.
        ->and(MentorshipChatThread::query()->pluck('id')->all())->toBe([$threads['pembimbing']->id]);
});

it('keeps a private chat that includes another student', function (): void {
    ['student' => $student, 'primary' => $primary] = archiveScenario();
    $friend = User::factory()->asMahasiswa()->create();
    $shared = MentorshipChatThread::factory()->create(['student_user_id' => $student->id, 'type' => 'private', 'private_key' => 'peer']);
    foreach ([$student, $friend] as $user) {
        MentorshipChatThreadParticipant::query()->create(['thread_id' => $shared->id, 'user_id' => $user->id, 'role' => 'student']);
    }
    MentorshipChatMessage::factory()->create(['mentorship_chat_thread_id' => $shared->id, 'sender_user_id' => $friend->id]);

    $this->artisan('sita:archive-completed-theses')->assertSuccessful();

    expect(MentorshipChatThread::query()->pluck('id')->all())->toBe([$shared->id])
        ->and(MentorshipChatMessage::query()->where('mentorship_chat_thread_id', $shared->id)->count())->toBe(1);
});

it('is idempotent', function (): void {
    ['project' => $project] = archiveScenario();

    $this->artisan('sita:archive-completed-theses')->assertSuccessful();
    $archivedAt = $project->refresh()->archived_at;
    $summary = $project->archive_summary;
    $files = Storage::disk('public')->allFiles();
    $events = ThesisProjectEvent::query()->where('event_type', 'project_archived')->count();

    $this->artisan('sita:archive-completed-theses')->assertSuccessful();

    $project->refresh();
    expect($project->archived_at->equalTo($archivedAt))->toBeTrue()
        ->and($project->archive_summary)->toBe($summary)
        ->and(Storage::disk('public')->allFiles())->toBe($files)
        ->and(ThesisProjectEvent::query()->where('event_type', 'project_archived')->count())->toBe($events)
        ->and($events)->toBe(1);
});

it('does nothing in --dry-run and prints exactly what a real run deletes', function (): void {
    ['student' => $student] = archiveScenario();
    $filesBefore = Storage::disk('public')->allFiles();
    $messagesBefore = MentorshipChatMessage::query()->count();

    Artisan::call('sita:archive-completed-theses', ['--dry-run' => true]);
    $dryRun = Artisan::output();

    expect(Storage::disk('public')->allFiles())->toBe($filesBefore)
        ->and(MentorshipChatMessage::query()->count())->toBe($messagesBefore)
        ->and(ThesisProject::query()->whereNotNull('archived_at')->count())->toBe(0)
        ->and($dryRun)->toContain('DRY RUN')
        ->and($dryRun)->toContain('would delete chat_threads')
        ->and($dryRun)->toContain('would delete chat_messages')
        ->and($dryRun)->toContain("public:documents/mahasiswa/{$student->id}/draft-bab-1.pdf")
        ->and($dryRun)->toContain('keeps file (still referenced) public:documents/mahasiswa/'.$student->id.'/naskah-akhir.pdf');

    // The files the dry run named are exactly the ones a real run removes.
    preg_match_all('/would delete file public:(\S+)/', $dryRun, $named);
    Artisan::call('sita:archive-completed-theses');

    expect(array_values(array_diff($filesBefore, Storage::disk('public')->allFiles())))->toEqualCanonicalizing($named[1]);
});

it('records the project_archived event with the deleted counts and the summary', function (): void {
    ['project' => $project] = archiveScenario();

    $this->artisan('sita:archive-completed-theses')->assertSuccessful();

    $event = ThesisProjectEvent::query()->where('project_id', $project->id)->where('event_type', 'project_archived')->sole();
    expect($event->payload['deleted'])->toMatchArray([
        'chat_threads' => 4,
        'chat_messages' => 9,
        'mentorship_schedules' => 5,
        'mentorship_documents' => 3,
        'thesis_documents' => 2,
        'files_deleted' => 4,
        'files_kept_shared' => 1,
    ])->and($event->payload['summary'])->toBe($project->refresh()->archive_summary);
});

it('stores the snapshot before deleting and keeps counts and last activity identical', function (): void {
    ['project' => $project, 'student' => $student, 'primary' => $primary, 'secondary' => $secondary] = archiveScenario();
    $fresh = fn (): ThesisProject => ThesisProject::query()->findOrFail($project->id);
    $viewer = $student;

    $completedFor = fn (ThesisProject $p): array => collect(app(MentorshipProgressService::class)->completedByStudentForLecturer($primary->id, ThesisSupervisorAssignment::query()->with('project')->where('project_id', $p->id)->where('lecturer_user_id', $primary->id)->get()))->all();
    $activity = fn (ThesisProject $p): ?string => app(StudentActivityService::class)->latestByProject(new Illuminate\Support\Collection([$p]))[$p->id]?->toIso8601String();

    $countsBefore = $completedFor($fresh());
    $secondaryBefore = app(MentorshipProgressService::class)->completedByStudentForLecturer($secondary->id, ThesisSupervisorAssignment::query()->with('project')->where('project_id', $project->id)->where('lecturer_user_id', $secondary->id)->get());
    $activityBefore = $activity($fresh());
    $snapshotBefore = app(StudentProgressSnapshot::class)->forProjects(new EloquentCollection([$fresh()]), $viewer)[$project->id];

    expect($countsBefore)->toBe([$student->id => 3])
        ->and($secondaryBefore)->toBe([$student->id => 1])
        ->and($activityBefore)->not->toBeNull();

    $this->artisan('sita:archive-completed-theses')->assertSuccessful();

    $archived = $fresh();
    expect($archived->archive_summary['completed_mentorship'])->toHaveCount(2)
        ->and($archived->archive_summary['schedules_by_status'])->toBe(['completed' => 4, 'rejected' => 1])
        ->and($archived->archive_summary['documents_uploaded'])->toBe(2)
        ->and($archived->archive_summary['messages'])->toBe(9)
        ->and($completedFor($archived))->toBe($countsBefore)
        ->and(app(MentorshipProgressService::class)->completedByStudentForLecturer($secondary->id, ThesisSupervisorAssignment::query()->with('project')->where('project_id', $project->id)->where('lecturer_user_id', $secondary->id)->get()))->toBe($secondaryBefore)
        ->and($activity($archived))->toBe($activityBefore)
        ->and(app(StudentProgressSnapshot::class)->forProjects(new EloquentCollection([$archived]), $viewer)[$project->id]['detail'])
        ->toBe($snapshotBefore['detail']);

    $first = collect($archived->archive_summary['completed_mentorship'])->firstWhere('lecturer_user_id', $primary->id);
    expect($first['completed'])->toBe(3)
        ->and($first['first_at'])->not->toBeNull()
        ->and($first['last_at'])->not->toBeNull();
});

it('rolls a project back when deletion fails and still archives the others', function (): void {
    ['project' => $broken, 'student' => $brokenStudent] = archiveScenario();
    ['project' => $healthy] = archiveScenario();

    // A trigger makes deleting the first student's schedules fail, after chat rows were already deleted.
    DB::unprepared("CREATE TRIGGER archive_test_block BEFORE DELETE ON mentorship_schedules WHEN OLD.student_user_id = {$brokenStudent->id} BEGIN SELECT RAISE(ABORT, 'boom'); END");

    $this->artisan('sita:archive-completed-theses')->assertFailed();

    expect($broken->refresh()->archived_at)->toBeNull()
        ->and($broken->archive_summary)->toBeNull()
        ->and(MentorshipChatThread::query()->where('student_user_id', $brokenStudent->id)->count())->toBe(4)
        ->and($healthy->refresh()->archived_at)->not->toBeNull();
});

it('does not schedule the destructive command unless archive_enabled is on', function (): void {
    expect(config('sita.archive_enabled'))->toBeFalse();

    $commands = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())
        ->map(fn ($event): string => (string) $event->command);

    expect($commands->contains(fn (string $command): bool => str_contains($command, 'sita:archive-completed-theses')))->toBeFalse();
});

it('keeps the history row last activity for an archived supervisor assignment', function (): void {
    ['project' => $project, 'primary' => $primary] = archiveScenario();
    $assignments = fn (): EloquentCollection => ThesisSupervisorAssignment::query()
        ->with('project')
        ->where('project_id', $project->id)
        ->where('lecturer_user_id', $primary->id)
        ->get();
    $before = app(StudentActivityService::class)->latest($assignments(), new EloquentCollection);
    $key = 'supervisor:'.$assignments()->first()->id;

    $this->artisan('sita:archive-completed-theses')->assertSuccessful();

    $after = app(StudentActivityService::class)->latest($assignments(), new EloquentCollection);
    expect($after[$key]->toIso8601String())->toBe($project->refresh()->archive_summary['last_activity_at'])
        ->and($after[$key]->gte($before[$key]))->toBeTrue();
});
