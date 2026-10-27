<?php

use App\Models\AdminProfile;
use App\Models\KaprodiAssignment;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipChatThreadParticipant;
use App\Models\MentorshipDocument;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\ThesisRevision;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Support\WitaDateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function defenseDocumentsExam(string $type = 'sempro', ?ThesisProject $project = null, int $attempt = 1): array
{
    $project ??= ThesisProject::factory()->create(['phase' => $type]);
    MahasiswaProfile::factory()->create([
        'user_id' => $project->student_user_id,
        'program_studi_id' => $project->program_studi_id,
        'is_active' => true,
    ]);
    $defense = ThesisDefense::query()->create([
        'project_id' => $project->id,
        'type' => $type,
        'attempt_no' => $attempt,
        'status' => 'scheduled',
        'result' => 'pending',
    ]);
    $examiner = User::factory()->asDosen()->create();
    ThesisDefenseExaminer::query()->create([
        'defense_id' => $defense->id,
        'lecturer_user_id' => $examiner->id,
        'role' => 'examiner',
        'order_no' => 1,
        'decision' => 'pending',
    ]);

    return [$defense, $project->student, $examiner, $project];
}

function defenseDocumentsWorkspace(User $student, int $version, string $name, string $contents): MentorshipDocument
{
    $path = 'documents/'.$student->id.'/'.$name;
    Storage::disk('public')->put($path, $contents);

    return MentorshipDocument::factory()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => null,
        'uploaded_by_user_id' => $student->id,
        'uploaded_by_role' => 'mahasiswa',
        'document_group' => $student->id.':manuscript',
        'version_number' => $version,
        'title' => 'Dokumen '.$version,
        'file_name' => $name,
        'storage_disk' => 'public',
        'storage_path' => $path,
        'created_at' => '2026-01-03 04:05:06',
    ]);
}

function defenseDocumentsSnapshot(ThesisDefense $defense, array $attributes = []): ThesisDocument
{
    $document = ThesisDocument::query()->create(array_merge([
        'project_id' => $defense->project_id,
        'defense_id' => $defense->id,
        'uploaded_by_user_id' => $defense->project->student_user_id,
        'kind' => $defense->type === 'sidang' ? 'final_manuscript' : 'proposal',
        'status' => 'active',
        'version_no' => 1,
        'title' => 'Naskah ujian',
        'storage_disk' => 'public',
        'storage_path' => 'thesis/'.$defense->id.'/snapshot-'.ThesisDocument::query()->count().'.pdf',
        'file_name' => 'snapshot.pdf',
        'mime_type' => 'application/pdf',
        'uploaded_at' => '2026-01-04 05:06:07',
    ], $attributes));
    Storage::disk('public')->put($document->storage_path, 'snapshot contents');

    return $document;
}

it('shows and serves the older workspace version explicitly selected through the student endpoint', function (string $type): void {
    Storage::fake('public');
    [$defense, $student, $examiner, $project] = defenseDocumentsExam($type);
    $selected = defenseDocumentsWorkspace($student, 2, 'selected-v2.pdf', 'selected old manuscript');
    defenseDocumentsWorkspace($student, 9, 'latest-v9.pdf', 'unselected latest manuscript');
    $support = defenseDocumentsWorkspace($student, 4, 'appendix-v4.pdf', 'selected appendix');

    $this->actingAs($student)->patch('/mahasiswa/tugas-akhir/'.$project->id.'/'.$type.'-documents', [
        'workspace_document_id' => $selected->id,
        ...($type === 'sidang' ? ['supporting_document_ids' => [$support->id]] : []),
    ])->assertRedirect()->assertSessionHasNoErrors();

    $snapshot = ThesisDocument::query()->where('defense_id', $defense->id)
        ->where('kind', $type === 'sidang' ? 'final_manuscript' : 'proposal')->sole();
    Storage::disk('public')->put($selected->storage_path, 'workspace changed after selection');

    $this->actingAs($examiner)->get('/dosen/seminar-proposal')
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('dosen/seminar-proposal')
        ->has('defenses', 1)
        ->has('defenses.0.documents', $type === 'sidang' ? 2 : 1)
        ->where('defenses.0.documents.0', [
            'id' => $snapshot->id,
            'detailUrl' => route('dosen.berkas-ujian.show', $snapshot),
            'kind' => $type === 'sidang' ? 'final_manuscript' : 'proposal',
            'kindLabel' => $type === 'sidang' ? 'Naskah Akhir' : 'Naskah Proposal',
            'title' => $snapshot->title,
            'fileName' => 'selected-v2.pdf',
            'version' => 2,
            'uploadedAt' => WitaDateTime::formatOrNull($selected->created_at),
            'viewUrl' => route('files.thesis-documents.download', ['document' => $snapshot->id, 'inline' => 1]),
            'downloadUrl' => route('files.thesis-documents.download', $snapshot),
        ])
        ->where('defenses.0.chatUrl', '/dosen/pesan?recipient='.$student->id)
        ->where('defenses.0.chatLabel', 'Chat pribadi mahasiswa'));

    $this->get(route('files.thesis-documents.download', $snapshot))
        ->assertOk()->assertDownload('selected-v2.pdf')->assertStreamedContent('selected old manuscript');
    $this->get(route('files.thesis-documents.download', ['document' => $snapshot->id, 'inline' => 1]))
        ->assertOk()->assertHeader('Content-Disposition', 'inline; filename="selected-v2.pdf"')
        ->assertStreamedContent('selected old manuscript');

    if ($type === 'sidang') {
        $appendix = ThesisDocument::query()->where('defense_id', $defense->id)->where('kind', 'supporting_document')->sole();
        $this->get('/dosen/seminar-proposal')->assertInertia(fn (Assert $page) => $page
            ->where('defenses.0.documents.1.fileName', 'appendix-v4.pdf')
            ->where('defenses.0.documents.1.version', 4)
            ->where('defenses.0.documents.1.kindLabel', 'Lampiran'));
        $this->get(route('files.thesis-documents.download', $appendix))->assertStreamedContent('selected appendix');
    }
})->with(['sempro', 'sidang']);

it('keeps each attempt separate and never substitutes project documents or revision manuscripts', function (): void {
    Storage::fake('public');
    [$older, $student, $examiner, $project] = defenseDocumentsExam();
    $oldSource = defenseDocumentsWorkspace($student, 3, 'attempt-one.pdf', 'first attempt');
    $this->actingAs($student)->patch('/mahasiswa/tugas-akhir/'.$project->id.'/sempro-documents', [
        'workspace_document_id' => $oldSource->id,
    ])->assertSessionHasNoErrors();
    $older->update(['status' => 'completed', 'result' => 'fail']);
    $newer = ThesisDefense::query()->create([
        'project_id' => $project->id, 'type' => 'sempro', 'attempt_no' => 2, 'status' => 'scheduled', 'result' => 'pending',
    ]);
    ThesisDefenseExaminer::query()->create([
        'defense_id' => $newer->id, 'lecturer_user_id' => $examiner->id, 'role' => 'examiner', 'order_no' => 1, 'decision' => 'pending',
    ]);
    $newSource = defenseDocumentsWorkspace($student, 5, 'attempt-two.pdf', 'second attempt');
    $this->actingAs($student)->patch('/mahasiswa/tugas-akhir/'.$project->id.'/sempro-documents', [
        'workspace_document_id' => $newSource->id,
    ])->assertSessionHasNoErrors();
    $oldSnapshot = $older->documents()->sole();
    $newSnapshot = $newer->documents()->sole();
    defenseDocumentsSnapshot($newer, ['defense_id' => null, 'file_name' => 'project-proposal.pdf']);
    defenseDocumentsSnapshot($newer, ['kind' => 'revision_response', 'file_name' => 'revision.pdf']);
    defenseDocumentsSnapshot($newer, ['kind' => 'revision_annotation', 'file_name' => 'annotation.pdf']);
    defenseDocumentsSnapshot($newer, ['kind' => 'final_manuscript', 'file_name' => 'wrong-kind.pdf']);
    defenseDocumentsSnapshot($newer, ['project_id' => ThesisProject::factory()->create()->id, 'file_name' => 'other-project.pdf']);

    $this->actingAs($examiner)->get('/dosen/seminar-proposal')->assertInertia(fn (Assert $page) => $page
        ->has('defenses', 2)
        ->where('defenses.0.defenseId', $newer->id)
        ->has('defenses.0.documents', 1)
        ->where('defenses.0.documents.0.fileName', 'attempt-two.pdf')
        ->where('defenses.0.documents.0.version', 5)
        ->where('defenses.1.defenseId', $older->id)
        ->has('defenses.1.documents', 1)
        ->where('defenses.1.documents.0.fileName', 'attempt-one.pdf')
        ->where('defenses.1.documents.0.version', 3));
    $this->get(route('files.thesis-documents.download', $oldSnapshot))->assertStreamedContent('first attempt');
    $this->get(route('files.thesis-documents.download', $newSnapshot))->assertStreamedContent('second attempt');
});

it('leaves missing selection empty and does not write documents files events or chats on GET', function (): void {
    Storage::fake('public');
    [$defense, $student, $examiner] = defenseDocumentsExam();
    defenseDocumentsWorkspace($student, 8, 'not-selected.pdf', 'workspace only');
    defenseDocumentsSnapshot($defense, ['defense_id' => null]);
    $files = Storage::disk('public')->allFiles();
    $writes = [];
    DB::listen(function ($query) use (&$writes): void {
        if (preg_match('/^\s*(insert|update|delete)\b/i', $query->sql)
            && preg_match('/thesis_documents|thesis_project_events|mentorship_chat_threads|mentorship_chat_thread_participants/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });

    $this->actingAs($examiner)->get('/dosen/seminar-proposal')->assertInertia(fn (Assert $page) => $page
        ->where('defenses.0.documents', [])
        ->where('defenses.0.chatUrl', '/dosen/pesan?recipient='.$student->id));
    $this->get('/dosen/seminar-proposal')->assertOk();

    expect($writes)->toBe([])->and(Storage::disk('public')->allFiles())->toBe($files);
});

it('uses recorded snapshot metadata when no source workspace version exists', function (): void {
    Storage::fake('public');
    [$defense, , $examiner] = defenseDocumentsExam('sidang');
    $snapshot = defenseDocumentsSnapshot($defense, ['version_no' => 7, 'uploaded_at' => null]);
    $appendix = defenseDocumentsSnapshot($defense, ['kind' => 'supporting_document', 'version_no' => 11]);

    $this->actingAs($examiner)->get('/dosen/seminar-proposal')->assertInertia(fn (Assert $page) => $page
        ->where('defenses.0.documents.0.id', $snapshot->id)
        ->where('defenses.0.documents.0.version', 7)
        ->where('defenses.0.documents.0.uploadedAt', null)
        ->where('defenses.0.documents.1.version', 11)
        ->where('defenses.0.documents.1.uploadedAt', WitaDateTime::formatOrNull($appendix->uploaded_at)));
});

it('restricts examiner downloads to their exact assigned attempt and project', function (string $target, bool $allowed): void {
    Storage::fake('public');
    [$defense, , $examiner, $project] = defenseDocumentsExam();
    $document = defenseDocumentsSnapshot($defense);
    if ($target === 'other attempt') {
        $other = ThesisDefense::query()->create([
            'project_id' => $project->id, 'type' => 'sempro', 'attempt_no' => 2, 'status' => 'scheduled', 'result' => 'pending',
        ]);
        $document = defenseDocumentsSnapshot($other);
    } elseif ($target === 'other project') {
        [$other] = defenseDocumentsExam();
        $document = defenseDocumentsSnapshot($other);
    } elseif ($target === 'no attempt') {
        $document->update(['defense_id' => null]);
    } elseif ($target === 'mismatched project') {
        $document->update(['project_id' => ThesisProject::factory()->create()->id]);
    }

    foreach ([[], ['inline' => 1]] as $query) {
        $response = $this->actingAs($examiner)->get(route('files.thesis-documents.download', ['document' => $document->id, ...$query]));
        $allowed ? $response->assertOk() : $response->assertForbidden();
    }
})->with([
    'assigned attempt' => ['assigned attempt', true],
    'different attempt' => ['other attempt', false],
    'different project' => ['other project', false],
    'project-only file' => ['no attempt', false],
    'inconsistent project' => ['mismatched project', false],
]);

it('preserves scoped student supervisor admin and kaprodi download rights', function (string $role, bool $sameProdi, bool $capability, bool $allowed): void {
    Storage::fake('public');
    [$defense, $student, , $project] = defenseDocumentsExam();
    $document = defenseDocumentsSnapshot($defense);
    $prodiId = $sameProdi ? $project->program_studi_id : ProgramStudi::factory()->create()->id;
    $user = match ($role) {
        'owner' => $student,
        'student' => User::factory()->asMahasiswa()->create(),
        'supervisor', 'dosen' => User::factory()->asDosen()->create(),
        'admin' => User::factory()->asAdmin()->create(),
        'super_admin' => User::factory()->asSuperAdmin()->create(),
        'kaprodi' => User::factory()->asKaprodi()->create(),
    };
    if ($role === 'supervisor') {
        ThesisSupervisorAssignment::query()->create([
            'project_id' => $project->id, 'lecturer_user_id' => $user->id,
            'role' => 'primary', 'status' => 'active', 'started_at' => now(),
        ]);
    } elseif ($role === 'admin') {
        AdminProfile::factory()->create(['user_id' => $user->id, 'program_studi_id' => $prodiId]);
    } elseif ($role === 'kaprodi') {
        $user->roles()->syncWithoutDetaching([\App\Models\Role::query()->firstOrCreate(['name' => 'dosen'])->id]);
        KaprodiAssignment::factory()->create([
            'user_id' => $user->id, 'program_studi_id' => $prodiId,
            'capabilities' => [KaprodiAssignment::CAPABILITY_DOWNLOAD_DOCUMENTS => $capability],
        ]);
    }
    foreach ([[], ['inline' => 1]] as $query) {
        $response = $this->actingAs($user->refresh())->get(route('files.thesis-documents.download', ['document' => $document->id, ...$query]));
        $allowed ? $response->assertOk() : $response->assertForbidden();
    }
})->with([
    'owner' => ['owner', true, false, true],
    'other student' => ['student', true, false, false],
    'supervisor' => ['supervisor', true, false, true],
    'unrelated lecturer' => ['dosen', true, false, false],
    'scoped admin' => ['admin', true, false, true],
    'other prodi admin' => ['admin', false, false, false],
    'super admin' => ['super_admin', false, false, true],
    'capable kaprodi' => ['kaprodi', true, true, true],
    'other prodi kaprodi' => ['kaprodi', false, true, false],
    'kaprodi without capability' => ['kaprodi', true, false, false],
]);

it('requires authentication for snapshot download and preview', function (): void {
    Storage::fake('public');
    [$defense] = defenseDocumentsExam();
    $document = defenseDocumentsSnapshot($defense);
    $this->get(route('files.thesis-documents.download', $document))->assertRedirect('/login');
    $this->get(route('files.thesis-documents.download', ['document' => $document->id, 'inline' => 1]))->assertRedirect('/login');
});

it('keeps unpublished annotation files private to their examiner and protects secret Sidang identities', function (bool $published, string $status, string $viewer, bool $allowed, bool $supervisorAuthor = false): void {
    Storage::fake('public');
    [$defense, $student, $author, $project] = defenseDocumentsExam('sidang');
    $defense->update(['status' => $status]);
    if ($supervisorAuthor) {
        ThesisDefenseExaminer::query()->where('defense_id', $defense->id)->where('lecturer_user_id', $author->id)
            ->update(['role' => 'primary_supervisor']);
    }
    $peer = User::factory()->asDosen()->create();
    ThesisDefenseExaminer::query()->create([
        'defense_id' => $defense->id, 'lecturer_user_id' => $peer->id, 'role' => 'examiner', 'order_no' => 2, 'decision' => 'pending',
    ]);
    $revision = $published ? ThesisRevision::factory()->create([
        'project_id' => $project->id, 'defense_id' => $defense->id, 'requested_by_user_id' => $author->id,
    ]) : null;
    $document = defenseDocumentsSnapshot($defense, [
        'kind' => 'revision_annotation', 'uploaded_by_user_id' => $author->id, 'revision_id' => $revision?->id,
    ]);
    $user = match ($viewer) {
        'author' => $author, 'peer' => $peer, 'student' => $student
    };
    foreach ([[], ['inline' => 1]] as $query) {
        $response = $this->actingAs($user)->get(route('files.thesis-documents.download', ['document' => $document->id, ...$query]));
        $allowed ? $response->assertOk() : $response->assertForbidden();
    }
})->with([
    'author draft' => [false, 'scheduled', 'author', true],
    'peer draft' => [false, 'scheduled', 'peer', false],
    'student draft' => [false, 'scheduled', 'student', false],
    'peer published revision' => [true, 'awaiting_finalization', 'peer', true],
    'secret examiner published revision' => [true, 'awaiting_finalization', 'student', false],
    'completed examiner published revision' => [true, 'completed', 'student', true],
    'visible supervisor published revision' => [true, 'awaiting_finalization', 'student', true, true],
]);

it('links only an existing visible exact-attempt exam chat or a private student destination without creating chats', function (string $visibility): void {
    Storage::fake('public');
    [$defense, $student, $examiner] = defenseDocumentsExam('sidang');
    $thread = MentorshipChatThread::query()->create([
        'student_user_id' => $student->id,
        'type' => $visibility === 'wrong type' ? 'sempro' : 'sidang',
        'context_id' => $visibility === 'wrong attempt' ? $defense->id + 100 : $defense->id,
    ]);
    if ($visibility !== 'not participant') {
        $thread->participants()->create(['user_id' => $examiner->id, 'role' => 'examiner']);
    }
    $private = null;
    if ($visibility === 'existing private') {
        $private = MentorshipChatThread::query()->create(['type' => 'private', 'private_key' => $student->id.':'.$examiner->id]);
        $private->participants()->create(['user_id' => $examiner->id, 'role' => 'dosen']);
        $private->participants()->create(['user_id' => $student->id, 'role' => 'mahasiswa']);
        $thread->participants()->delete();
    }
    $threadCount = MentorshipChatThread::query()->count();
    $participantCount = MentorshipChatThreadParticipant::query()->count();
    $isGroup = $visibility === 'visible';
    $expectedUrl = $isGroup ? '/dosen/pesan?thread='.$thread->id
        : '/dosen/pesan?'.($private !== null ? 'thread='.$private->id : 'recipient='.$student->id);

    $this->actingAs($examiner)->get('/dosen/seminar-proposal')->assertInertia(fn (Assert $page) => $page
        ->where('defenses.0.chatUrl', $expectedUrl)
        ->where('defenses.0.chatLabel', $isGroup ? 'Chat grup ujian' : 'Chat pribadi mahasiswa'));
    expect(MentorshipChatThread::query()->count())->toBe($threadCount)
        ->and(MentorshipChatThreadParticipant::query()->count())->toBe($participantCount);
})->with(['visible', 'not participant', 'wrong attempt', 'wrong type', 'existing private']);

it('does not rebuild a missing snapshot from the still available workspace file on GET', function (): void {
    Storage::fake('public');
    [$defense, $student, $examiner, $project] = defenseDocumentsExam();
    $source = defenseDocumentsWorkspace($student, 2, 'available-source.pdf', 'workspace original');
    $this->actingAs($student)->patch('/mahasiswa/tugas-akhir/'.$project->id.'/sempro-documents', [
        'workspace_document_id' => $source->id,
    ])->assertSessionHasNoErrors();
    $snapshot = $defense->documents()->sole();
    Storage::disk('public')->delete($snapshot->storage_path);

    $this->actingAs($examiner)->get(route('files.thesis-documents.download', $snapshot))->assertNotFound();
    $this->get(route('files.thesis-documents.download', ['document' => $snapshot->id, 'inline' => 1]))->assertNotFound();
    Storage::disk('public')->assertMissing($snapshot->storage_path);
    Storage::disk('public')->assertExists($source->storage_path);
});
