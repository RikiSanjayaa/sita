<?php

use App\Enums\AdvisorType;
use App\Enums\AppRole;
use App\Enums\AssignmentStatus;
use App\Enums\SemproStatus;
use App\Enums\ThesisSubmissionStatus;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipAssignment;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\Sempro;
use App\Models\SemproExaminer;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisSubmission;
use App\Models\User;
use App\Services\LegacyThesisProjectBackfillService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function createUserWithRole(string $role): User
{
    $user = User::factory()->create(['last_active_role' => $role]);
    $roleModel = Role::query()->firstOrCreate(['name' => $role]);
    $user->roles()->sync([$roleModel->id]);

    return $user;
}

test('mahasiswa can request schedule to selected active advisor', function () {
    $admin = createUserWithRole(AppRole::Admin->value);
    $student = createUserWithRole(AppRole::Mahasiswa->value);
    $dosen1 = createUserWithRole(AppRole::Dosen->value);
    $dosen2 = createUserWithRole(AppRole::Dosen->value);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'is_active' => true,
    ]);

    MentorshipAssignment::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $dosen1->id,
        'advisor_type' => AdvisorType::Primary->value,
        'status' => AssignmentStatus::Active->value,
        'assigned_by' => $admin->id,
    ]);
    MentorshipAssignment::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $dosen2->id,
        'advisor_type' => AdvisorType::Secondary->value,
        'status' => AssignmentStatus::Active->value,
        'assigned_by' => $admin->id,
    ]);

    $this->actingAs($student)
        ->post('/mahasiswa/jadwal-bimbingan', [
            'topic' => 'Review Bab 3',
            'lecturer_user_id' => $dosen1->id,
            'requested_for' => now()->addDay()->format('Y-m-d H:i:s'),
            'meeting_type' => 'online',
            'student_note' => 'Mohon jadwalkan siang hari.',
        ])
        ->assertRedirect('/mahasiswa/jadwal-bimbingan');

    $this->assertDatabaseCount('mentorship_schedules', 1);
    $this->assertDatabaseHas('mentorship_schedules', [
        'student_user_id' => $student->id,
        'lecturer_user_id' => $dosen1->id,
    ]);
});

test('mahasiswa cannot request schedule to lecturer outside active advisors', function () {
    $admin = createUserWithRole(AppRole::Admin->value);
    $student = createUserWithRole(AppRole::Mahasiswa->value);
    $activeDosen = createUserWithRole(AppRole::Dosen->value);
    $foreignDosen = createUserWithRole(AppRole::Dosen->value);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'is_active' => true,
    ]);

    MentorshipAssignment::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $activeDosen->id,
        'advisor_type' => AdvisorType::Primary->value,
        'status' => AssignmentStatus::Active->value,
        'assigned_by' => $admin->id,
    ]);

    $this->actingAs($student)
        ->from('/mahasiswa/jadwal-bimbingan')
        ->post('/mahasiswa/jadwal-bimbingan', [
            'topic' => 'Review Bab 4',
            'lecturer_user_id' => $foreignDosen->id,
            'requested_for' => now()->addDay()->format('Y-m-d H:i:s'),
            'meeting_type' => 'offline',
            'student_note' => 'Mohon konfirmasi.',
        ])
        ->assertRedirect('/mahasiswa/jadwal-bimbingan')
        ->assertSessionHasErrors('lecturer_user_id');

    $this->assertDatabaseCount('mentorship_schedules', 0);
});

test('mahasiswa upload creates document version rows and chat event', function () {
    Storage::fake('public');

    $admin = createUserWithRole(AppRole::Admin->value);
    $student = createUserWithRole(AppRole::Mahasiswa->value);
    $dosen1 = createUserWithRole(AppRole::Dosen->value);
    $dosen2 = createUserWithRole(AppRole::Dosen->value);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'is_active' => true,
    ]);

    MentorshipAssignment::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $dosen1->id,
        'advisor_type' => AdvisorType::Primary->value,
        'status' => AssignmentStatus::Active->value,
        'assigned_by' => $admin->id,
    ]);
    MentorshipAssignment::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $dosen2->id,
        'advisor_type' => AdvisorType::Secondary->value,
        'status' => AssignmentStatus::Active->value,
        'assigned_by' => $admin->id,
    ]);

    $file = UploadedFile::fake()->create('draft-bab3.pdf', 600, 'application/pdf');

    $this->actingAs($student)
        ->post('/mahasiswa/upload-dokumen', [
            'title' => 'Draft Bab 3',
            'category' => 'draft-tugas-akhir',
            'document' => $file,
        ])
        ->assertRedirect('/mahasiswa/upload-dokumen');

    $this->assertDatabaseCount('mentorship_documents', 2);
    $this->assertDatabaseHas('mentorship_documents', [
        'student_user_id' => $student->id,
        'uploaded_by_role' => 'mahasiswa',
        'version_number' => 1,
    ]);
    $this->assertDatabaseHas('mentorship_chat_messages', [
        'message_type' => 'document_event',
        'attachment_name' => 'draft-bab3.pdf',
    ]);
});

test('mahasiswa chat attachment creates document event and appears as versioned upload', function () {
    Storage::fake('public');

    $admin = createUserWithRole(AppRole::Admin->value);
    $student = createUserWithRole(AppRole::Mahasiswa->value);
    $dosen = createUserWithRole(AppRole::Dosen->value);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'is_active' => true,
    ]);

    MentorshipAssignment::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $dosen->id,
        'advisor_type' => AdvisorType::Primary->value,
        'status' => AssignmentStatus::Active->value,
        'assigned_by' => $admin->id,
    ]);

    // Create the pembimbing thread first
    $thread = MentorshipChatThread::query()->create([
        'student_user_id' => $student->id,
        'type' => 'pembimbing',
    ]);

    $this->actingAs($student)
        ->post("/mahasiswa/pesan/{$thread->id}/messages", [
            'message' => 'Lampiran pertama',
            'attachment' => UploadedFile::fake()->create('lampiran-v1.pdf', 250, 'application/pdf'),
        ])
        ->assertRedirect();

    $this->actingAs($student)
        ->post("/mahasiswa/pesan/{$thread->id}/messages", [
            'message' => 'Lampiran kedua',
            'attachment' => UploadedFile::fake()->create('lampiran-v2.pdf', 300, 'application/pdf'),
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('mentorship_documents', [
        'student_user_id' => $student->id,
        'category' => 'lampiran-chat',
        'version_number' => 1,
    ]);

    $this->assertDatabaseHas('mentorship_documents', [
        'student_user_id' => $student->id,
        'category' => 'lampiran-chat',
        'version_number' => 2,
    ]);

    $this->assertDatabaseCount('mentorship_chat_messages', 2);
    $this->assertDatabaseHas('mentorship_chat_messages', [
        'message_type' => 'document_event',
        'message' => 'Mahasiswa mengunggah dokumen lampiran chat versi v1.',
    ]);
    $this->assertDatabaseHas('mentorship_chat_messages', [
        'message_type' => 'document_event',
        'message' => 'Mahasiswa mengunggah dokumen lampiran chat versi v2.',
    ]);

    $this->actingAs($student)
        ->get('/mahasiswa/upload-dokumen')
        ->assertInertia(fn(Assert $page) => $page
            ->component('upload-dokumen')
            ->has('uploadedDocuments', 2));
});

test('tugas akhir page includes proposal file, dosen assignments, and sempro schedule', function () {
    Storage::fake('public');

    $admin = createUserWithRole(AppRole::Admin->value);
    $student = createUserWithRole(AppRole::Mahasiswa->value);
    $pembimbing1 = createUserWithRole(AppRole::Dosen->value);
    $pembimbing2 = createUserWithRole(AppRole::Dosen->value);
    $penguji1 = createUserWithRole(AppRole::Dosen->value);
    $penguji2 = createUserWithRole(AppRole::Dosen->value);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'is_active' => true,
    ]);

    MentorshipAssignment::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $pembimbing1->id,
        'advisor_type' => AdvisorType::Primary->value,
        'status' => AssignmentStatus::Active->value,
        'assigned_by' => $admin->id,
    ]);
    MentorshipAssignment::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $pembimbing2->id,
        'advisor_type' => AdvisorType::Secondary->value,
        'status' => AssignmentStatus::Active->value,
        'assigned_by' => $admin->id,
    ]);

    $proposalPath = 'proposal_files/proposal-awal.pdf';
    Storage::disk('public')->put($proposalPath, 'proposal-content');

    $prodi = ProgramStudi::factory()->create(['name' => 'Informatika']);

    $submission = ThesisSubmission::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'title_id' => 'Sistem Monitoring Skripsi',
        'title_en' => 'Thesis Monitoring System',
        'proposal_summary' => 'Ringkasan proposal.',
        'proposal_file_path' => $proposalPath,
        'status' => ThesisSubmissionStatus::MenungguPersetujuan->value,
        'is_active' => true,
        'submitted_at' => now(),
    ]);

    $scheduledAt = now()->addDays(7)->setSecond(0);
    $sempro = Sempro::query()->create([
        'thesis_submission_id' => $submission->id,
        'status' => SemproStatus::Scheduled->value,
        'scheduled_for' => $scheduledAt,
        'location' => 'Ruang Sidang A',
        'mode' => 'offline',
    ]);

    SemproExaminer::query()->create([
        'sempro_id' => $sempro->id,
        'examiner_user_id' => $penguji1->id,
        'examiner_order' => 1,
    ]);
    SemproExaminer::query()->create([
        'sempro_id' => $sempro->id,
        'examiner_user_id' => $penguji2->id,
        'examiner_order' => 2,
    ]);

    $this->actingAs($student)
        ->get('/mahasiswa/tugas-akhir')
        ->assertInertia(fn(Assert $page) => $page
            ->component('tugas-akhir')
            ->where('submission.proposal_file_name', 'proposal-awal.pdf')
            ->where(
                'submission.proposal_file_view_url',
                route('files.thesis-documents.download', [
                    'document' => ThesisDocument::query()->firstOrFail()->id,
                    'inline' => 1,
                ]),
            )
            ->where(
                'submission.proposal_file_download_url',
                route('files.thesis-documents.download', [
                    'document' => ThesisDocument::query()->firstOrFail()->id,
                ]),
            )
            ->where('assignedLecturers.pembimbing1', $pembimbing1->name)
            ->where('assignedLecturers.pembimbing2', $pembimbing2->name)
            ->where('assignedLecturers.penguji1', $penguji1->name)
            ->where('assignedLecturers.penguji2', $penguji2->name)
            ->where('semproDate', $scheduledAt->locale('id')->translatedFormat('d F Y, H:i'))
            ->where('sidangDate', null));
});

test('mahasiswa can submit thesis proposal and sync project snapshot', function () {
    Storage::fake('public');

    $student = createUserWithRole(AppRole::Mahasiswa->value);
    $prodi = ProgramStudi::factory()->create(['name' => 'Informatika']);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'is_active' => true,
    ]);

    $this->actingAs($student)
        ->post('/mahasiswa/tugas-akhir', [
            'title_id' => 'Pengajuan Sinkron Snapshot',
            'title_en' => 'Snapshot Sync Submission',
            'proposal_summary' => 'Ringkasan proposal sinkron.',
            'proposal_file' => UploadedFile::fake()->create('proposal-snapshot.pdf', 700, 'application/pdf'),
        ])
        ->assertRedirect();

    expect(ThesisSubmission::query()->count())->toBe(1)
        ->and(ThesisProject::query()->count())->toBe(1)
        ->and(ThesisProjectTitle::query()->count())->toBe(1)
        ->and(ThesisDocument::query()->count())->toBe(1)
        ->and(ThesisProjectTitle::query()->firstOrFail()->title_id)->toBe('Pengajuan Sinkron Snapshot')
        ->and(ThesisProject::query()->firstOrFail()->phase)->toBe('title_review');
});

test('tugas akhir page prefers thesis project snapshot over legacy submission fields', function () {
    Storage::fake('public');

    $student = createUserWithRole(AppRole::Mahasiswa->value);
    $prodi = ProgramStudi::factory()->create(['name' => 'Teknik Informatika']);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'is_active' => true,
    ]);

    $path = 'proposal_files/proposal-project-view.pdf';
    Storage::disk('public')->put($path, 'proposal-content');

    $submission = ThesisSubmission::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'title_id' => 'Judul Legacy',
        'title_en' => 'Legacy Title',
        'proposal_summary' => 'Ringkasan legacy.',
        'proposal_file_path' => $path,
        'status' => ThesisSubmissionStatus::MenungguPersetujuan->value,
        'is_active' => true,
        'submitted_at' => now(),
    ]);

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'legacy_thesis_submission_id' => $submission->id,
        'phase' => 'title_review',
        'state' => 'active',
        'started_at' => now()->subHour(),
    ]);

    ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Judul dari Project Snapshot',
        'title_en' => 'Project Snapshot Title',
        'proposal_summary' => 'Ringkasan dari project snapshot.',
        'status' => 'submitted',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subHour(),
    ]);

    $this->actingAs($student)
        ->get('/mahasiswa/tugas-akhir')
        ->assertInertia(fn(Assert $page) => $page
            ->component('tugas-akhir')
            ->where('submission.title_id', 'Judul dari Project Snapshot')
            ->where('submission.title_en', 'Project Snapshot Title')
            ->where('submission.proposal_summary', 'Ringkasan dari project snapshot.')
            ->where('submission.proposal_file_name', 'proposal-project-view.pdf'));
});

test('mahasiswa can update pending thesis submission and replace proposal file', function () {
    Storage::fake('public');

    $student = createUserWithRole(AppRole::Mahasiswa->value);
    $prodi = ProgramStudi::factory()->create(['name' => 'Sistem Informasi']);
    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'is_active' => true,
    ]);

    $oldPath = 'proposal_files/proposal-lama.pdf';
    Storage::disk('public')->put($oldPath, 'old-content');

    $submission = ThesisSubmission::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'title_id' => 'Judul Lama',
        'title_en' => 'Old Title',
        'proposal_summary' => 'Ringkasan lama.',
        'proposal_file_path' => $oldPath,
        'status' => ThesisSubmissionStatus::MenungguPersetujuan->value,
        'is_active' => true,
        'submitted_at' => now(),
    ]);

    $this->actingAs($student)
        ->from('/mahasiswa/tugas-akhir')
        ->patch("/mahasiswa/tugas-akhir/{$submission->id}", [
            'title_id' => 'Judul Revisi',
            'title_en' => 'Revised Title',
            'proposal_summary' => 'Ringkasan revisi.',
            'proposal_file' => UploadedFile::fake()->create('proposal-revisi.pdf', 600, 'application/pdf'),
        ])
        ->assertRedirect('/mahasiswa/tugas-akhir');

    $updated = $submission->fresh();
    $project = ThesisProject::query()->firstOrFail();
    $title = ThesisProjectTitle::query()->where('project_id', $project->id)->firstOrFail();
    $document = ThesisDocument::query()->where('project_id', $project->id)->firstOrFail();

    expect($updated)->not()->toBeNull()
        ->and($updated?->program_studi_id)->toBe($prodi->id)
        ->and($updated?->title_id)->toBe('Judul Revisi')
        ->and($updated?->title_en)->toBe('Revised Title')
        ->and($updated?->proposal_summary)->toBe('Ringkasan revisi.')
        ->and($updated?->proposal_file_path)->not()->toBeNull()
        ->and($title->title_id)->toBe('Judul Revisi')
        ->and($title->title_en)->toBe('Revised Title')
        ->and($document->file_name)->toBe(basename((string) $updated?->proposal_file_path));

    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists((string) $updated?->proposal_file_path);
});

test('proposal file access is restricted to owning mahasiswa', function () {
    Storage::fake('public');

    $owner = createUserWithRole(AppRole::Mahasiswa->value);
    $otherStudent = createUserWithRole(AppRole::Mahasiswa->value);
    $admin = createUserWithRole(AppRole::Admin->value);

    $path = 'proposal_files/proposal-owner.pdf';
    Storage::disk('public')->put($path, 'owner-content');

    $prodi = ProgramStudi::factory()->create();

    $submission = ThesisSubmission::query()->create([
        'student_user_id' => $owner->id,
        'program_studi_id' => $prodi->id,
        'title_id' => 'Judul Owner',
        'title_en' => 'Owner Title',
        'proposal_summary' => 'Ringkasan owner.',
        'proposal_file_path' => $path,
        'status' => ThesisSubmissionStatus::MenungguPersetujuan->value,
        'is_active' => true,
        'submitted_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(route('files.thesis-proposals', ['submission' => $submission->id]))
        ->assertOk();

    $this->actingAs($otherStudent)
        ->get(route('files.thesis-proposals', ['submission' => $submission->id]))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('files.thesis-proposals', ['submission' => $submission->id]))
        ->assertOk();
});

test('project proposal document download is restricted by thesis project access', function () {
    Storage::fake('public');

    $owner = createUserWithRole(AppRole::Mahasiswa->value);
    $otherStudent = createUserWithRole(AppRole::Mahasiswa->value);
    $admin = createUserWithRole(AppRole::Admin->value);
    $otherAdmin = createUserWithRole(AppRole::Admin->value);
    $prodi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $otherProdi = ProgramStudi::factory()->create(['name' => 'Sistem Informasi']);

    MahasiswaProfile::factory()->create([
        'user_id' => $owner->id,
        'program_studi_id' => $prodi->id,
        'is_active' => true,
    ]);

    MahasiswaProfile::factory()->create([
        'user_id' => $otherStudent->id,
        'program_studi_id' => $otherProdi->id,
        'is_active' => true,
    ]);

    \App\Models\AdminProfile::query()->create([
        'user_id' => $admin->id,
        'program_studi_id' => $prodi->id,
    ]);

    \App\Models\AdminProfile::query()->create([
        'user_id' => $otherAdmin->id,
        'program_studi_id' => $otherProdi->id,
    ]);

    $path = 'proposal_files/proposal-project-native.pdf';
    Storage::disk('public')->put($path, 'proposal-content');

    ThesisSubmission::query()->create([
        'student_user_id' => $owner->id,
        'program_studi_id' => $prodi->id,
        'title_id' => 'Judul Project Native',
        'title_en' => 'Project Native Title',
        'proposal_summary' => 'Ringkasan native.',
        'proposal_file_path' => $path,
        'status' => ThesisSubmissionStatus::MenungguPersetujuan->value,
        'is_active' => true,
        'submitted_at' => now(),
    ]);

    app(LegacyThesisProjectBackfillService::class)->backfill($owner->id);

    $document = ThesisDocument::query()->firstOrFail();

    $this->actingAs($owner)
        ->get(route('files.thesis-documents.download', ['document' => $document->id]))
        ->assertOk();

    $this->actingAs($otherStudent)
        ->get(route('files.thesis-documents.download', ['document' => $document->id]))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('files.thesis-documents.download', ['document' => $document->id]))
        ->assertOk();

    $this->actingAs($otherAdmin)
        ->get(route('files.thesis-documents.download', ['document' => $document->id]))
        ->assertForbidden();
});

test('download permissions enforce ownership and escalation rules', function () {
    Storage::fake('public');

    $admin = createUserWithRole(AppRole::Admin->value);
    $student = createUserWithRole(AppRole::Mahasiswa->value);
    $otherStudent = createUserWithRole(AppRole::Mahasiswa->value);
    $dosen = createUserWithRole(AppRole::Dosen->value);

    MahasiswaProfile::factory()->create(['user_id' => $student->id, 'is_active' => true]);
    MahasiswaProfile::factory()->create(['user_id' => $otherStudent->id, 'is_active' => true]);

    MentorshipAssignment::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $dosen->id,
        'advisor_type' => AdvisorType::Primary->value,
        'status' => AssignmentStatus::Active->value,
        'assigned_by' => $admin->id,
    ]);

    Storage::disk('public')->put('chat/revisi/revisi-v1.pdf', 'revision-file');

    $thread = MentorshipChatThread::query()->create([
        'student_user_id' => $student->id,
        'is_escalated' => false,
    ]);

    $message = MentorshipChatMessage::query()->create([
        'mentorship_chat_thread_id' => $thread->id,
        'sender_user_id' => $dosen->id,
        'attachment_disk' => 'public',
        'attachment_path' => 'chat/revisi/revisi-v1.pdf',
        'attachment_name' => 'revisi-v1.pdf',
        'attachment_mime' => 'application/pdf',
        'attachment_size_kb' => 12,
        'message_type' => 'revision_suggestion',
        'message' => 'Silakan cek revisi.',
        'sent_at' => now(),
    ]);

    $this->actingAs($student)
        ->get(route('files.chat-attachments.download', ['message' => $message->id]))
        ->assertOk();

    $this->actingAs($otherStudent)
        ->get(route('files.chat-attachments.download', ['message' => $message->id]))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('files.chat-attachments.download', ['message' => $message->id]))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('files.chat-attachments.download', ['message' => $message->id, 'escalated' => 1]))
        ->assertOk();
});
