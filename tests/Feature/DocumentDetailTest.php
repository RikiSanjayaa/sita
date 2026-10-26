<?php

use App\Enums\AdvisorType;
use App\Enums\AppRole;
use App\Models\KaprodiAssignment;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipDocument;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\RealtimeNotificationService;
use App\Services\ThesisRevisionRoundService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function documentDetailScenario(): array
{
    Storage::fake('public');
    $student = createUserWithRole(AppRole::Mahasiswa->value);
    MahasiswaProfile::factory()->create(['user_id' => $student->id, 'is_active' => true]);
    $first = createUserWithRole(AppRole::Dosen->value);
    $second = createUserWithRole(AppRole::Dosen->value);
    syncThesisSupervisor($student, $first, $first, AdvisorType::Primary->value);
    syncThesisSupervisor($student, $second, $first, AdvisorType::Secondary->value);
    $project = ThesisProject::query()->where('student_user_id', $student->id)->firstOrFail();
    Storage::disk('public')->put('details/upload.pdf', 'exact upload bytes');
    $copies = collect([$first, $second])->map(fn (User $reviewer): MentorshipDocument => MentorshipDocument::factory()->create([
        'student_user_id' => $student->id, 'lecturer_user_id' => $reviewer->id,
        'uploaded_by_user_id' => $student->id, 'uploaded_by_role' => 'mahasiswa',
        'document_group' => $student->id.':proposal', 'version_number' => 2,
        'storage_disk' => 'public', 'storage_path' => 'details/upload.pdf',
        'file_name' => 'upload-v2.pdf', 'status' => 'submitted',
    ]));

    return [$student, $first, $second, $project, $copies[0], $copies[1]];
}

it('shows an exact reviewer copy with shared version reviews and denies foreign direct ids', function (): void {
    [$student, $first, $second, $project, $copy, $other] = documentDetailScenario();
    $other->update(['status' => 'needs_revision', 'revision_notes' => 'Perbaiki tabel.']);
    $unrelated = MentorshipDocument::factory()->create(['student_user_id' => $student->id, 'lecturer_user_id' => $first->id]);
    $before = MentorshipChatThread::query()->count();
    $this->actingAs($first)->get(route('dosen.dokumen-revisi.show', $copy))->assertInertia(fn (Assert $page) => $page
        ->component('documents/show')->where('role', 'dosen')->where('document.id', $copy->id)
        ->where('document.version', 2)->where('document.canReview', true)->where('document.status', 'submitted')
        ->has('document.reviews', 2)->where('document.reviews.1.notes', 'Perbaiki tabel.')
        ->has('document.versions', 1)->where('document.versions.0.url', route('dosen.dokumen-revisi.show', $copy))
        ->where('navigation.chatUrl', null));
    expect(MentorshipChatThread::query()->count())->toBe($before);
    $this->get(route('dosen.dokumen-revisi.show', $other))->assertForbidden();
    $this->get(route('files.documents.download', $other))->assertForbidden();
    $this->actingAs($second)->get(route('dosen.dokumen-revisi.show', $unrelated))->assertForbidden();
    $this->actingAs(createUserWithRole('mahasiswa'))->get(route('mahasiswa.upload-dokumen.show', $copy))->assertForbidden();
    $this->actingAs($student)->get(route('mahasiswa.upload-dokumen.show', $copy))->assertInertia(fn (Assert $page) => $page
        ->where('document.status', 'needs_revision')->where('document.statusLabel', 'Perlu Revisi')
        ->where('document.canReview', false)->where('document.reviewUrl', null)->where('document.reviewNotes', ''));
});

it('saves trimmed optional approval notes on only the reviewer copy without approving an exam', function (): void {
    [$student, $first, $second, $project, $copy, $other] = documentDetailScenario();
    $defense = ThesisDefense::query()->create(['project_id' => $project->id, 'type' => 'sempro', 'attempt_no' => 1, 'status' => 'scheduled', 'result' => 'pending']);
    $examiner = ThesisDefenseExaminer::query()->create(['defense_id' => $defense->id, 'lecturer_user_id' => $first->id, 'role' => 'examiner', 'order_no' => 1, 'decision' => 'pending']);
    $this->actingAs($first)->post(route('dosen.dokumen-revisi.review', $copy), ['status' => 'approved', 'revision_notes' => '  Catatan persetujuan.  '])->assertRedirect()->assertSessionHasNoErrors();
    expect($copy->fresh()->revision_notes)->toBe('Catatan persetujuan.')
        ->and($copy->fresh()->reviewed_at)->not->toBeNull()->and($other->fresh()->status)->toBe('submitted')
        ->and($examiner->fresh()->decision)->toBe('pending')->and($defense->fresh()->status)->toBe('scheduled');
    $this->actingAs($student)->get(route('mahasiswa.upload-dokumen.show', $copy))->assertInertia(fn (Assert $page) => $page
        ->where('document.status', 'partially_reviewed')->where('document.reviews.0.notes', 'Catatan persetujuan.'));
    $this->actingAs($second)->post(route('dosen.dokumen-revisi.review', $other), ['status' => 'approved'])->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($student)->get(route('mahasiswa.upload-dokumen.show', $copy))->assertInertia(fn (Assert $page) => $page->where('document.status', 'approved'));
});

it('keeps inactive completed archived expired and ended assignments readable but rejects mutations', function (string $state): void {
    [$student, $first, $second, $project, $copy] = documentDetailScenario();
    match ($state) {
        'expired' => $project->update(['mentorship_valid_until' => now()->subDay()->toDateString()]),
        'completed' => $project->update(['state' => 'completed', 'completed_at' => now()]),
        'archived' => $project->update(['state' => 'completed', 'archived_at' => now()]),
        'inactive' => $student->mahasiswaProfile->update(['is_active' => false]),
        'ended' => ThesisSupervisorAssignment::query()->where('project_id', $project->id)->where('lecturer_user_id', $first->id)->update(['status' => 'ended']),
    };
    $this->actingAs($first)->get(route('dosen.dokumen-revisi.show', $copy))->assertInertia(fn (Assert $page) => $page
        ->where('document.canReview', false)->where('document.reviewUrl', null)->where('document.readOnlyReason', fn ($reason): bool => is_string($reason) && $reason !== ''));
    $this->get(route('files.documents.download', $copy))->assertStreamedContent('exact upload bytes');
    $this->post(route('dosen.dokumen-revisi.review', $copy), ['status' => 'approved'])->assertForbidden();
    expect($copy->fresh()->status)->toBe('submitted');
})->with(['expired', 'completed', 'archived', 'inactive', 'ended']);

it('reads historical own versions without permitting edits and never switches a detail to latest', function (): void {
    [$student, $first, $second, $project, $copy, $other] = documentDetailScenario();
    $newer = $copy->replicate();
    $newer->forceFill(['version_number' => 3, 'file_name' => 'upload-v3.pdf'])->save();
    $this->actingAs($first)->get(route('dosen.dokumen-revisi.show', $copy))->assertInertia(fn (Assert $page) => $page
        ->where('document.id', $copy->id)->where('document.fileName', 'upload-v2.pdf')->where('document.version', 2)
        ->where('document.canReview', false)->has('document.versions', 2)
        ->where('document.versions.0.url', route('dosen.dokumen-revisi.show', $newer))
        ->where('document.versions.1.url', route('dosen.dokumen-revisi.show', $copy)));
    $this->post(route('dosen.dokumen-revisi.review', $copy), ['status' => 'approved'])->assertForbidden();
    $this->get(route('dosen.dokumen-revisi.show', $newer))->assertInertia(fn (Assert $page) => $page->where('document.canReview', true));
});

it('does not combine legacy null groups or let unrelated uploads lock them', function (): void {
    [$student, $first, $second, $project, $copy] = documentDetailScenario();
    $copy->update(['document_group' => null]);
    MentorshipDocument::factory()->create(['student_user_id' => $student->id, 'lecturer_user_id' => $first->id, 'document_group' => null, 'version_number' => 99]);
    $this->actingAs($first)->get(route('dosen.dokumen-revisi.show', $copy))->assertInertia(fn (Assert $page) => $page
        ->has('document.reviews', 1)->has('document.versions', 1)->where('document.canReview', true));
});

it('separates kaprodi viewing and downloading even when the user also holds lecturer role', function (): void {
    [$student, $first, $second, $project, $copy] = documentDetailScenario();
    $kaprodi = createUserWithRole('kaprodi');
    $assignment = KaprodiAssignment::factory()->create(['user_id' => $kaprodi->id, 'program_studi_id' => $project->program_studi_id, 'capabilities' => [KaprodiAssignment::CAPABILITY_VIEW_DOCUMENTS]]);
    $this->actingAs($kaprodi)->get(route('kaprodi.dokumen.workspace.show', $copy))->assertInertia(fn (Assert $page) => $page
        ->where('role', 'kaprodi')->where('document.canReview', false)->where('document.downloadUrl', null)->where('document.viewUrl', null));
    $this->get(route('files.documents.download', $copy))->assertForbidden();
    $this->get(route('dosen.dokumen-revisi.show', $copy))->assertForbidden();
    $assignment->update(['capabilities' => [KaprodiAssignment::CAPABILITY_DOWNLOAD_DOCUMENTS]]);
    $this->actingAs($kaprodi->fresh());
    $this->get(route('kaprodi.dokumen.workspace.show', $copy))->assertForbidden();
    $this->get(route('files.documents.download', $copy))->assertStreamedContent('exact upload bytes');
    $foreign = createUserWithRole('kaprodi');
    KaprodiAssignment::factory()->create(['user_id' => $foreign->id, 'program_studi_id' => ProgramStudi::factory()->create()->id]);
    $this->actingAs($foreign)->get(route('kaprodi.dokumen.workspace.show', $copy))->assertForbidden();
    $this->get(route('files.documents.download', $copy))->assertForbidden();
});

it('links only authorized existing chat threads and never creates one on get', function (): void {
    [$student, $first, $second, $project, $copy] = documentDetailScenario();
    $unavailable = MentorshipChatThread::factory()->create(['student_user_id' => $student->id, 'type' => 'private']);
    $allowed = MentorshipChatThread::factory()->create(['student_user_id' => $student->id, 'type' => 'pembimbing']);
    foreach ([$unavailable, $allowed] as $thread) {
        MentorshipChatMessage::factory()->create(['mentorship_chat_thread_id' => $thread->id, 'sender_user_id' => $student->id, 'related_document_id' => $copy->id, 'message_type' => 'document_event']);
    }
    $this->actingAs($first)->get(route('dosen.dokumen-revisi.show', $copy))->assertInertia(fn (Assert $page) => $page->where('navigation.chatUrl', '/dosen/pesan?thread='.$allowed->id));
    expect(MentorshipChatThread::query()->count())->toBe(2);
});

it('deep links review notifications to the exact historical version', function (): void {
    [$student, $first, $second, $project, $copy] = documentDetailScenario();
    $this->mock(RealtimeNotificationService::class)->shouldReceive('notifyUser')->once()
        ->withArgs(fn (User $recipient, string $key, array $payload): bool => $recipient->id === $student->id
            && $payload['url'] === route('mahasiswa.upload-dokumen.show', $copy));
    $this->actingAs($first)->post(route('dosen.dokumen-revisi.review', $copy), ['status' => 'needs_revision', 'revision_notes' => '  Revisi tabel.  '])->assertRedirect();
    expect($copy->fresh()->revision_notes)->toBe('Revisi tabel.');
});

it('deep links every upload notification to that recipients exact copy', function (): void {
    [$student, $first, $second] = documentDetailScenario();
    $seen = [];
    $this->mock(RealtimeNotificationService::class)->shouldReceive('notifyUser')->twice()->withArgs(function (User $recipient, string $key, array $payload) use (&$seen, $student): bool {
        $document = MentorshipDocument::query()->where('student_user_id', $student->id)->where('lecturer_user_id', $recipient->id)->where('category', 'proposal')->latest('id')->first();
        $seen[] = $recipient->id;

        return $document !== null && $payload['url'] === route('dosen.dokumen-revisi.show', $document);
    });
    $this->actingAs($student)->post(route('mahasiswa.upload-dokumen.store'), ['title' => 'Proposal baru', 'category' => 'proposal', 'document' => UploadedFile::fake()->create('next.pdf', 10, 'application/pdf')])->assertRedirect()->assertSessionHasNoErrors();
    expect($seen)->toContain($first->id, $second->id);
});

function documentDetailSnapshot(ThesisProject $project, MentorshipDocument $source): array
{
    $defense = ThesisDefense::query()->create(['project_id' => $project->id, 'type' => 'sidang', 'attempt_no' => 2, 'status' => 'scheduled', 'result' => 'pending']);
    $snapshot = ThesisDocument::query()->create([
        'project_id' => $project->id, 'defense_id' => $defense->id, 'source_workspace_document_id' => $source->id,
        'uploaded_by_user_id' => $project->student_user_id, 'kind' => 'final_manuscript', 'status' => 'active',
        'version_no' => 1, 'title' => 'Berkas terpilih', 'storage_disk' => 'public', 'storage_path' => 'details/snapshot.pdf',
        'file_name' => 'snapshot-v2.pdf', 'mime_type' => 'application/pdf', 'uploaded_at' => now(),
    ]);
    Storage::disk('public')->put($snapshot->storage_path, 'snapshot bytes');

    return [$defense, $snapshot];
}

it('shows an immutable snapshot and resolves its source version to the viewers own review copy', function (): void {
    [$student, $first, $second, $project, $copy, $other] = documentDetailScenario();
    [$defense, $snapshot] = documentDetailSnapshot($project, $other);
    $newer = $copy->replicate();
    $newer->forceFill(['version_number' => 9])->save();
    $this->actingAs($first)->get(route('dosen.berkas-ujian.show', $snapshot))->assertInertia(fn (Assert $page) => $page
        ->component('documents/show')->where('document.source', 'thesis')->where('document.fileName', 'snapshot-v2.pdf')
        ->where('document.version', 2)->where('document.canReview', false)->where('document.reviewUrl', null)
        ->where('document.reviewDetailUrl', route('dosen.dokumen-revisi.show', $copy))
        ->where('navigation.listUrl', '/dosen/seminar-proposal?defense='.$defense->id));
    $this->get(route('files.thesis-documents.download', $snapshot))->assertStreamedContent('snapshot bytes');
    $outsider = createUserWithRole('dosen');
    $this->actingAs($outsider)->get(route('dosen.berkas-ujian.show', $snapshot))->assertForbidden();
    $this->get(route('files.thesis-documents.download', $snapshot))->assertForbidden();
    $examiner = ThesisDefenseExaminer::query()->create(['defense_id' => $defense->id, 'lecturer_user_id' => $outsider->id, 'role' => 'examiner', 'order_no' => 1, 'decision' => 'pending']);
    $this->get(route('dosen.berkas-ujian.show', $snapshot))->assertInertia(fn (Assert $page) => $page
        ->where('document.reviewDetailUrl', null)->has('document.reviews', 0));
    expect($examiner->fresh()->decision)->toBe('pending');
});

it('enforces thesis snapshot prodi view and download capabilities separately for dual role kaprodi', function (): void {
    [$student, $first, $second, $project, $copy] = documentDetailScenario();
    [$defense, $snapshot] = documentDetailSnapshot($project, $copy);
    $kaprodi = createUserWithRole('kaprodi');
    $assignment = KaprodiAssignment::factory()->create(['user_id' => $kaprodi->id, 'program_studi_id' => $project->program_studi_id, 'capabilities' => [KaprodiAssignment::CAPABILITY_VIEW_DOCUMENTS]]);
    $this->actingAs($kaprodi)->get(route('kaprodi.dokumen.thesis.show', $snapshot))->assertInertia(fn (Assert $page) => $page
        ->where('document.downloadUrl', null)->where('document.viewUrl', null)->where('document.canReview', false)
        ->where('document.reviewDetailUrl', route('kaprodi.dokumen.workspace.show', $copy)));
    $this->get(route('files.thesis-documents.download', $snapshot))->assertForbidden();
    $this->get(route('dosen.berkas-ujian.show', $snapshot))->assertForbidden();
    $assignment->update(['capabilities' => [KaprodiAssignment::CAPABILITY_VIEW_DOCUMENTS, KaprodiAssignment::CAPABILITY_DOWNLOAD_DOCUMENTS]]);
    $this->actingAs($kaprodi->fresh());
    $this->get(route('kaprodi.dokumen.thesis.show', $snapshot))->assertInertia(fn (Assert $page) => $page
        ->where('document.downloadUrl', route('files.thesis-documents.download', ['document' => $snapshot->id, 'portal' => 'kaprodi'])));
    $this->get(route('files.thesis-documents.download', $snapshot))->assertStreamedContent('snapshot bytes');
    $foreign = createUserWithRole('kaprodi');
    KaprodiAssignment::factory()->create(['user_id' => $foreign->id, 'program_studi_id' => ProgramStudi::factory()->create()->id]);
    $this->actingAs($foreign)->get(route('kaprodi.dokumen.thesis.show', $snapshot))->assertForbidden();
    $this->get(route('files.thesis-documents.download', $snapshot))->assertForbidden();
});

it('does not expose private examiner annotation snapshots to another lecturer or the student', function (): void {
    [$student, $first, $second, $project, $copy] = documentDetailScenario();
    [$defense, $snapshot] = documentDetailSnapshot($project, $copy);
    $examiner = createUserWithRole('dosen');
    ThesisDefenseExaminer::query()->create(['defense_id' => $defense->id, 'lecturer_user_id' => $examiner->id, 'role' => 'examiner', 'order_no' => 1, 'decision' => 'pending']);
    $snapshot->update(['kind' => ThesisRevisionRoundService::ANNOTATION_KIND, 'revision_id' => null, 'uploaded_by_user_id' => $examiner->id]);
    $this->actingAs($first)->get(route('dosen.berkas-ujian.show', $snapshot))->assertForbidden();
    $this->get(route('files.thesis-documents.download', $snapshot))->assertForbidden();
    $this->actingAs($student)->get(route('files.thesis-documents.download', $snapshot))->assertForbidden();
    $this->actingAs($examiner)->get(route('dosen.berkas-ujian.show', $snapshot))->assertOk();
    $this->get(route('files.thesis-documents.download', $snapshot))->assertStreamedContent('snapshot bytes');
});

it('anonymizes secret sidang reviewers without exposing their user ids profile links or review copy links', function (): void {
    [$student, $first, $second, $project, $copy] = documentDetailScenario();
    [$defense, $snapshot] = documentDetailSnapshot($project, $copy);
    $secret = createUserWithRole('dosen');
    $secret->update(['name' => 'Penguji Rahasia Dokumen']);
    ThesisDefenseExaminer::query()->create(['defense_id' => $defense->id, 'lecturer_user_id' => $secret->id, 'role' => 'examiner', 'order_no' => 1, 'decision' => 'pending']);
    $secretCopy = $copy->replicate();
    $secretCopy->forceFill(['lecturer_user_id' => $secret->id, 'status' => 'approved', 'revision_notes' => 'Catatan penguji.'])->save();
    $response = $this->actingAs($student)->get(route('mahasiswa.upload-dokumen.show', $copy))->assertInertia(fn (Assert $page) => $page
        ->has('document.reviews', 3)->where('document.reviews.2.reviewer', 'Penguji Sidang')
        ->where('document.reviews.2.role', 'Penguji Sidang')->where('document.reviews.2.id', 3));
    $payload = json_encode($response->viewData('page')['props']['document']);
    expect($payload)->not->toContain($secret->name)->not->toContain('users/'.$secret->id)
        ->not->toContain('lecturer_user_id')->not->toContain('dokumen-revisi/'.$secretCopy->id)
        ->not->toContain('upload-dokumen/'.$secretCopy->id);
});

it('requires authenticated access to detail pages', function (): void {
    [$student, $first, $second, $project, $copy] = documentDetailScenario();
    $this->get(route('dosen.dokumen-revisi.show', $copy))->assertRedirect(route('login'));
    $this->get(route('mahasiswa.upload-dokumen.show', $copy))->assertRedirect(route('login'));
    $this->get(route('kaprodi.dokumen.workspace.show', $copy))->assertRedirect(route('login'));
});

it('keeps kaprodi portal download permission separate from the same users lecturer copy', function (): void {
    [$student, $first, $second, $project, $copy] = documentDetailScenario();
    KaprodiAssignment::factory()->create(['user_id' => $first->id, 'program_studi_id' => $project->program_studi_id, 'capabilities' => [KaprodiAssignment::CAPABILITY_VIEW_DOCUMENTS]]);
    $this->actingAs($first)->get(route('kaprodi.dokumen.workspace.show', $copy))->assertInertia(fn (Assert $page) => $page
        ->where('document.canReview', false)->where('document.downloadUrl', null));
    $this->get(route('files.documents.download', ['document' => $copy->id, 'portal' => 'kaprodi']))->assertForbidden();
    $this->get(route('files.documents.download', $copy))->assertStreamedContent('exact upload bytes');
    $this->get(route('dosen.dokumen-revisi.show', $copy))->assertInertia(fn (Assert $page) => $page->where('document.canReview', true));
});

it('keeps chat review links on the viewers exact upload version rather than the latest version', function (): void {
    [$student, $first, $second, $project, $copy, $other] = documentDetailScenario();
    $newer = $other->replicate();
    $newer->forceFill(['version_number' => 10])->save();
    $thread = MentorshipChatThread::factory()->create(['student_user_id' => $student->id, 'type' => 'pembimbing']);
    $message = MentorshipChatMessage::factory()->create(['mentorship_chat_thread_id' => $thread->id, 'sender_user_id' => $student->id, 'related_document_id' => $copy->id, 'message_type' => 'document_event']);
    $builder = app(\App\Services\ChatMessagePayloadBuilder::class);
    $payload = $builder->build($message, $builder->ownReviewsFor($second, collect([$copy])));
    expect($payload['ownReview'])->toBe(['id' => $other->id, 'status' => 'submitted', 'detailUrl' => route('dosen.dokumen-revisi.show', $other)]);
});

it('does not disclose source workspace metadata from a different student on a thesis snapshot', function (): void {
    [$student, $first, $second, $project, $copy] = documentDetailScenario();
    $foreign = MentorshipDocument::factory()->create(['lecturer_user_id' => $first->id, 'version_number' => 77]);
    [$defense, $snapshot] = documentDetailSnapshot($project, $foreign);
    $this->actingAs($first)->get(route('dosen.berkas-ujian.show', $snapshot))->assertInertia(fn (Assert $page) => $page
        ->where('document.version', 1)->where('document.reviewDetailUrl', null)->has('document.reviews', 0));
});

it('preserves exam filters only for a local return URL for the same attempt', function (): void {
    [$student, $first, $second, $project, $copy] = documentDetailScenario();
    [$defense, $snapshot] = documentDetailSnapshot($project, $copy);
    $returnTo = '/dosen/seminar-proposal?type=sidang&search=Kirana&defense='.$defense->id;
    $this->actingAs($first)->get(route('dosen.berkas-ujian.show', ['document' => $snapshot->id, 'returnTo' => $returnTo]))
        ->assertInertia(fn (Assert $page) => $page->where('navigation.listUrl', $returnTo));
    foreach (['https://example.test'.$returnTo, 'http:'.$returnTo, '//example.test'.$returnTo, '/dosen/seminar-proposal?defense=999999'] as $untrusted) {
        $this->get(route('dosen.berkas-ujian.show', ['document' => $snapshot->id, 'returnTo' => $untrusted]))
            ->assertInertia(fn (Assert $page) => $page->where('navigation.listUrl', '/dosen/seminar-proposal?defense='.$defense->id));
    }
});
