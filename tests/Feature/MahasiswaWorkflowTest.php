<?php

use App\Enums\AdvisorType;
use App\Enums\AppRole;
use App\Enums\AssignmentStatus;
use App\Models\AdminProfile;
use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipAssignment;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipDocument;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\ThesisProjectStudentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function createUserWithRole(string $role): User
{
    $user = User::factory()->create(['last_active_role' => $role]);
    $roleModel = Role::query()->firstOrCreate(['name' => $role]);
    $user->roles()->sync([$roleModel->id]);

    if ($role === AppRole::Dosen->value) {
        DosenProfile::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);
    }

    return $user;
}

function ensureActiveThesisProject(User $student): ThesisProject
{
    $profile = $student->mahasiswaProfile;
    $programStudiId = $profile?->program_studi_id ?? ProgramStudi::factory()->create()->id;

    return ThesisProject::query()->firstOrCreate(
        [
            'student_user_id' => $student->id,
            'state' => 'active',
        ],
        [
            'program_studi_id' => $programStudiId,
            'phase' => 'research',
            'started_at' => now()->subWeek(),
            'created_by' => $student->id,
        ],
    );
}

function syncThesisSupervisor(User $student, User $lecturer, User $admin, string $role): void
{
    $project = ensureActiveThesisProject($student);

    ThesisSupervisorAssignment::query()->updateOrCreate(
        [
            'project_id' => $project->id,
            'role' => $role,
            'status' => 'active',
        ],
        [
            'lecturer_user_id' => $lecturer->id,
            'assigned_by' => $admin->id,
            'started_at' => now(),
        ],
    );
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
    syncThesisSupervisor($student, $dosen1, $admin, AdvisorType::Primary->value);

    MentorshipAssignment::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $dosen2->id,
        'advisor_type' => AdvisorType::Secondary->value,
        'status' => AssignmentStatus::Active->value,
        'assigned_by' => $admin->id,
    ]);
    syncThesisSupervisor($student, $dosen2, $admin, AdvisorType::Secondary->value);

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
    syncThesisSupervisor($student, $activeDosen, $admin, AdvisorType::Primary->value);

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
    syncThesisSupervisor($student, $dosen1, $admin, AdvisorType::Primary->value);

    MentorshipAssignment::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $dosen2->id,
        'advisor_type' => AdvisorType::Secondary->value,
        'status' => AssignmentStatus::Active->value,
        'assigned_by' => $admin->id,
    ]);
    syncThesisSupervisor($student, $dosen2, $admin, AdvisorType::Secondary->value);

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

test('mahasiswa thesis submission notifies super admin and scoped admin', function () {
    Storage::fake('public');

    $matchingProdi = ProgramStudi::factory()->create(['name' => 'Informatika']);
    $otherProdi = ProgramStudi::factory()->create(['name' => 'Sistem Informasi']);

    $student = User::factory()->asMahasiswa()->create();
    $superAdmin = User::factory()->asSuperAdmin()->create();
    $scopedAdmin = User::factory()->asAdmin()->create();
    $otherAdmin = User::factory()->asAdmin()->create();

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $matchingProdi->id,
        'concentration' => ProgramStudi::DEFAULT_GENERAL_CONCENTRATION,
        'is_active' => true,
    ]);

    AdminProfile::factory()->create([
        'user_id' => $scopedAdmin->id,
        'program_studi_id' => $matchingProdi->id,
    ]);

    AdminProfile::factory()->create([
        'user_id' => $otherAdmin->id,
        'program_studi_id' => $otherProdi->id,
    ]);

    app(ThesisProjectStudentService::class)->submit(
        student: $student,
        data: [
            'title_id' => 'Sistem Monitoring Tugas Akhir Adaptif',
            'title_en' => 'Adaptive Thesis Monitoring System',
            'proposal_summary' => 'Ringkasan proposal untuk pengujian notifikasi admin.',
        ],
        proposalFile: UploadedFile::fake()->create('proposal-monitoring.pdf', 512, 'application/pdf'),
    );

    expect($superAdmin->notifications()->count())->toBe(1)
        ->and($scopedAdmin->notifications()->count())->toBe(1)
        ->and($otherAdmin->notifications()->count())->toBe(0);
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
    syncThesisSupervisor($student, $dosen, $admin, AdvisorType::Primary->value);

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

    $proposalPath = 'proposal_files/proposal-awal.pdf';
    Storage::disk('public')->put($proposalPath, 'proposal-content');

    $prodi = ProgramStudi::factory()->create(['name' => 'Informatika']);

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'sempro',
        'state' => 'active',
        'started_at' => now()->subDays(3),
        'created_by' => $student->id,
    ]);

    $title = ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Sistem Monitoring Skripsi',
        'title_en' => 'Thesis Monitoring System',
        'proposal_summary' => 'Ringkasan proposal.',
        'status' => 'approved',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subDays(3),
        'decided_by_user_id' => $admin->id,
        'decided_at' => now()->subDays(2),
    ]);

    ThesisDocument::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $title->id,
        'uploaded_by_user_id' => $student->id,
        'kind' => 'proposal',
        'status' => 'active',
        'version_no' => 1,
        'title' => 'Proposal Skripsi',
        'storage_disk' => 'public',
        'storage_path' => $proposalPath,
        'file_name' => 'proposal-awal.pdf',
        'mime_type' => 'application/pdf',
        'file_size_kb' => 1,
        'uploaded_at' => now()->subDays(3),
    ]);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $project->id,
        'lecturer_user_id' => $pembimbing1->id,
        'role' => AdvisorType::Primary->value,
        'status' => 'active',
        'assigned_by' => $admin->id,
        'started_at' => now()->subDays(2),
    ]);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $project->id,
        'lecturer_user_id' => $pembimbing2->id,
        'role' => AdvisorType::Secondary->value,
        'status' => 'active',
        'assigned_by' => $admin->id,
        'started_at' => now()->subDays(2),
    ]);

    $scheduledAt = now()->addDays(7)->setSecond(0);
    $sempro = ThesisDefense::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $title->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'scheduled',
        'result' => 'pending',
        'scheduled_for' => $scheduledAt,
        'location' => 'Ruang Sidang A',
        'mode' => 'offline',
    ]);

    ThesisDefenseExaminer::query()->create([
        'defense_id' => $sempro->id,
        'lecturer_user_id' => $penguji1->id,
        'role' => 'examiner',
        'order_no' => 1,
        'decision' => 'pending',
    ]);
    ThesisDefenseExaminer::query()->create([
        'defense_id' => $sempro->id,
        'lecturer_user_id' => $penguji2->id,
        'role' => 'examiner',
        'order_no' => 2,
        'decision' => 'pending',
    ]);

    $this->actingAs($student)
        ->get('/mahasiswa/tugas-akhir')
        ->assertInertia(fn(Assert $page) => $page
            ->component('tugas-akhir')
            ->where('submission.workflow.key', 'sempro_scheduled')
            ->where('submission.workflow.can_edit', true)
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

test('mahasiswa can submit thesis proposal into thesis project aggregate', function () {
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

    $project = ThesisProject::query()->firstOrFail();

    expect(ThesisProject::query()->count())->toBe(1)
        ->and(ThesisProjectTitle::query()->count())->toBe(1)
        ->and(ThesisDocument::query()->count())->toBe(1)
        ->and(ThesisProjectTitle::query()->firstOrFail()->title_id)->toBe('Pengajuan Sinkron Snapshot')
        ->and($project->phase)->toBe('title_review')
        ->and($project->legacy_thesis_submission_id)->toBeNull();
});

test('tugas akhir page renders thesis project snapshot data', function () {
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

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'title_review',
        'state' => 'active',
        'started_at' => now()->subHour(),
    ]);

    $title = ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Judul dari Project Snapshot',
        'title_en' => 'Project Snapshot Title',
        'proposal_summary' => 'Ringkasan dari project snapshot.',
        'status' => 'submitted',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subHour(),
    ]);

    ThesisDocument::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $title->id,
        'uploaded_by_user_id' => $student->id,
        'kind' => 'proposal',
        'status' => 'active',
        'version_no' => 1,
        'title' => 'Proposal Skripsi',
        'storage_disk' => 'public',
        'storage_path' => $path,
        'file_name' => 'proposal-project-view.pdf',
        'mime_type' => 'application/pdf',
        'file_size_kb' => 1,
        'uploaded_at' => now()->subHour(),
    ]);

    $this->actingAs($student)
        ->get('/mahasiswa/tugas-akhir')
        ->assertInertia(fn(Assert $page) => $page
            ->component('tugas-akhir')
            ->where('submission.workflow.key', 'title_review_pending')
            ->where('submission.workflow.can_edit', true)
            ->where('submission.title_id', 'Judul dari Project Snapshot')
            ->where('submission.title_en', 'Project Snapshot Title')
            ->where('submission.proposal_summary', 'Ringkasan dari project snapshot.')
            ->where('submission.proposal_file_name', 'proposal-project-view.pdf'));
});

test('tugas akhir page shows completed sempro and sidang results', function () {
    $student = createUserWithRole(AppRole::Mahasiswa->value);
    $admin = createUserWithRole(AppRole::Admin->value);
    $prodi = ProgramStudi::factory()->create(['name' => 'Teknik Informatika']);
    $pengujiSempro = createUserWithRole(AppRole::Dosen->value);
    $ketuaSidang = createUserWithRole(AppRole::Dosen->value);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'is_active' => true,
    ]);

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'completed',
        'state' => 'active',
        'started_at' => now()->subMonth(),
        'created_by' => $student->id,
    ]);

    $title = ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Prediksi Kelulusan Berbasis Machine Learning',
        'title_en' => 'Graduation Prediction Based on Machine Learning',
        'proposal_summary' => 'Ringkasan proposal akhir.',
        'status' => 'approved',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subMonth(),
    ]);

    $sempro = ThesisDefense::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $title->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'completed',
        'result' => 'pass_with_revision',
        'scheduled_for' => now()->subWeeks(2),
        'location' => 'Ruang Sempro 1',
        'mode' => 'offline',
    ]);

    $sidang = ThesisDefense::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $title->id,
        'type' => 'sidang',
        'attempt_no' => 1,
        'status' => 'completed',
        'result' => 'pass',
        'scheduled_for' => now()->subWeek(),
        'location' => 'Ruang Sidang Utama',
        'mode' => 'offline',
    ]);

    ThesisDefenseExaminer::query()->create([
        'defense_id' => $sempro->id,
        'lecturer_user_id' => $pengujiSempro->id,
        'role' => 'examiner',
        'order_no' => 1,
        'decision' => 'pass_with_revision',
        'score' => 78,
        'notes' => 'Perbaiki penjelasan metodologi.',
        'assigned_by' => $admin->id,
    ]);

    ThesisDefenseExaminer::query()->create([
        'defense_id' => $sidang->id,
        'lecturer_user_id' => $ketuaSidang->id,
        'role' => 'primary_supervisor',
        'order_no' => 1,
        'decision' => 'pass',
        'score' => 85,
        'notes' => 'Presentasi baik dan argumentasi kuat.',
        'assigned_by' => $admin->id,
    ]);

    $this->actingAs($student)
        ->get('/mahasiswa/tugas-akhir')
        ->assertInertia(fn(Assert $page) => $page
            ->component('tugas-akhir')
            ->where('semproResult.label', 'Seminar Proposal')
            ->where('semproResult.resultLabel', 'Lulus dengan Revisi')
            ->where('semproResult.examiners.0.name', $pengujiSempro->name)
            ->where('semproResult.examiners.0.score', '78.00')
            ->where('sidangResult.label', 'Sidang Skripsi')
            ->where('sidangResult.resultLabel', 'Lulus')
            ->where('sidangResult.examiners.0.name', $ketuaSidang->name)
            ->where('sidangResult.examiners.0.roleLabel', 'Pembimbing 1')
            ->where('sidangResult.examiners.0.score', '85.00')
            ->where('defenseHistory.sempro.0.attemptNo', 1)
            ->where('defenseHistory.sidang.0.attemptNo', 1));
});

test('tugas akhir page keeps previous sempro attempts visible after reschedule', function () {
    $student = createUserWithRole(AppRole::Mahasiswa->value);
    $admin = createUserWithRole(AppRole::Admin->value);
    $examiner = createUserWithRole(AppRole::Dosen->value);
    $prodi = ProgramStudi::factory()->create(['name' => 'Teknik Informatika']);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'is_active' => true,
    ]);

    DosenProfile::query()->where('user_id', $examiner->id)->update([
        'program_studi_id' => $prodi->id,
        'is_active' => true,
    ]);

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'sempro',
        'state' => 'active',
        'started_at' => now()->subMonth(),
        'created_by' => $student->id,
    ]);

    $title = ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Analisis Riwayat Sempro',
        'status' => 'approved',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subMonth(),
        'decided_by_user_id' => $admin->id,
        'decided_at' => now()->subWeeks(3),
    ]);

    $firstAttempt = ThesisDefense::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $title->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'completed',
        'result' => 'fail',
        'scheduled_for' => now()->subWeeks(2),
        'location' => 'Ruang Lama',
        'mode' => 'offline',
        'created_by' => $admin->id,
        'decided_by' => $admin->id,
        'decision_at' => now()->subWeeks(2),
        'notes' => 'Perlu sempro ulang.',
    ]);

    ThesisDefenseExaminer::query()->create([
        'defense_id' => $firstAttempt->id,
        'lecturer_user_id' => $examiner->id,
        'role' => 'examiner',
        'order_no' => 1,
        'decision' => 'fail',
        'assigned_by' => $admin->id,
    ]);

    ThesisDefense::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $title->id,
        'type' => 'sempro',
        'attempt_no' => 2,
        'status' => 'scheduled',
        'result' => 'pending',
        'scheduled_for' => now()->addWeek(),
        'location' => 'Ruang Baru',
        'mode' => 'offline',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($student)
        ->get('/mahasiswa/tugas-akhir')
        ->assertInertia(fn(Assert $page) => $page
            ->component('tugas-akhir')
            ->where('submission.workflow.key', 'sempro_scheduled')
            ->where('submission.workflow.can_edit', true)
            ->where('semproResult', null)
            ->where('defenseHistory.sempro.0.attemptNo', 2)
            ->where('defenseHistory.sempro.0.statusLabel', 'Dijadwalkan')
            ->where('defenseHistory.sempro.1.attemptNo', 1)
            ->where('defenseHistory.sempro.1.resultLabel', 'Tidak Lulus')
            ->where('defenseHistory.sempro.1.officialNotes', 'Perlu sempro ulang.'));
});

test('mahasiswa can update pending thesis project and replace proposal file', function () {
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

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'title_review',
        'state' => 'active',
        'started_at' => now()->subDay(),
        'created_by' => $student->id,
    ]);

    $title = ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Judul Lama',
        'title_en' => 'Old Title',
        'proposal_summary' => 'Ringkasan lama.',
        'status' => 'submitted',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subDay(),
    ]);

    ThesisDocument::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $title->id,
        'uploaded_by_user_id' => $student->id,
        'kind' => 'proposal',
        'status' => 'active',
        'version_no' => 1,
        'title' => 'Proposal Skripsi',
        'storage_disk' => 'public',
        'storage_path' => $oldPath,
        'file_name' => 'proposal-lama.pdf',
        'mime_type' => 'application/pdf',
        'file_size_kb' => 1,
        'uploaded_at' => now()->subDay(),
    ]);

    $this->actingAs($student)
        ->from('/mahasiswa/tugas-akhir')
        ->patch("/mahasiswa/tugas-akhir/{$project->id}", [
            'title_id' => 'Judul Revisi',
            'title_en' => 'Revised Title',
            'proposal_summary' => 'Ringkasan revisi.',
            'proposal_file' => UploadedFile::fake()->create('proposal-revisi.pdf', 600, 'application/pdf'),
        ])
        ->assertRedirect('/mahasiswa/tugas-akhir');

    $updatedProject = $project->fresh();
    $updatedTitle = ThesisProjectTitle::query()->where('project_id', $project->id)->firstOrFail();
    $document = ThesisDocument::query()->where('project_id', $project->id)->firstOrFail();

    expect($updatedProject)->not()->toBeNull()
        ->and($updatedProject?->program_studi_id)->toBe($prodi->id)
        ->and($updatedTitle->title_id)->toBe('Judul Revisi')
        ->and($updatedTitle->title_en)->toBe('Revised Title')
        ->and($updatedTitle->proposal_summary)->toBe('Ringkasan revisi.')
        ->and($document->storage_path)->not()->toBeNull()
        ->and($document->file_name)->toBe('proposal-revisi.pdf')
        ->and($document->stored_file_name)->toBe(basename((string) $document->storage_path));

    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists((string) $document->storage_path);
});

test('mahasiswa can update sempro revision proposal without resetting thesis project workflow', function () {
    Storage::fake('public');

    $admin = createUserWithRole(AppRole::Admin->value);
    $student = createUserWithRole(AppRole::Mahasiswa->value);
    $examiner = createUserWithRole(AppRole::Dosen->value);
    $prodi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'is_active' => true,
    ]);

    $oldPath = 'proposal_files/proposal-sempro-revisi.pdf';
    Storage::disk('public')->put($oldPath, 'old-sempro-content');

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'sempro',
        'state' => 'active',
        'started_at' => now()->subDays(5),
        'created_by' => $student->id,
    ]);

    $title = ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Judul Sebelum Revisi Sempro',
        'title_en' => 'Before Sempro Revision',
        'proposal_summary' => 'Ringkasan sebelum revisi sempro.',
        'status' => 'approved',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subDays(5),
        'decided_by_user_id' => $admin->id,
        'decided_at' => now()->subDays(4),
    ]);

    ThesisDocument::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $title->id,
        'uploaded_by_user_id' => $student->id,
        'kind' => 'proposal',
        'status' => 'active',
        'version_no' => 1,
        'title' => 'Proposal Skripsi',
        'storage_disk' => 'public',
        'storage_path' => $oldPath,
        'file_name' => 'proposal-sempro-revisi.pdf',
        'stored_file_name' => basename($oldPath),
        'mime_type' => 'application/pdf',
        'file_size_kb' => 1,
        'uploaded_at' => now()->subDays(5),
    ]);

    $sempro = ThesisDefense::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $title->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'completed',
        'result' => 'pass_with_revision',
        'scheduled_for' => now()->subDays(2),
        'location' => 'Ruang Seminar B',
        'mode' => 'offline',
        'created_by' => $admin->id,
        'decided_by' => $admin->id,
        'decision_at' => now()->subDays(2),
    ]);

    ThesisDefenseExaminer::query()->create([
        'defense_id' => $sempro->id,
        'lecturer_user_id' => $examiner->id,
        'role' => 'examiner',
        'order_no' => 1,
        'decision' => 'pass_with_revision',
    ]);

    \App\Models\ThesisRevision::query()->create([
        'project_id' => $project->id,
        'defense_id' => $sempro->id,
        'requested_by_user_id' => $examiner->id,
        'status' => 'open',
        'notes' => 'Perbaiki judul dan proposal.',
        'due_at' => now()->addWeek(),
    ]);

    $this->actingAs($student)
        ->from('/mahasiswa/tugas-akhir')
        ->patch("/mahasiswa/tugas-akhir/{$project->id}", [
            'title_id' => 'Judul Sesudah Revisi Sempro',
            'title_en' => 'After Sempro Revision',
            'proposal_summary' => 'Ringkasan setelah revisi sempro.',
            'proposal_file' => UploadedFile::fake()->create('proposal-sempro-final.pdf', 700, 'application/pdf'),
        ])
        ->assertRedirect('/mahasiswa/tugas-akhir');

    $updatedProject = $project->fresh();
    $updatedTitle = $title->fresh();
    $updatedDocument = ThesisDocument::query()->where('project_id', $project->id)->firstOrFail();

    expect($updatedProject)->not()->toBeNull()
        ->and($updatedProject?->phase)->toBe('sempro')
        ->and($updatedTitle?->title_id)->toBe('Judul Sesudah Revisi Sempro')
        ->and($updatedTitle?->status)->toBe('approved')
        ->and($updatedDocument->file_name)->toBe('proposal-sempro-final.pdf');

    $this->assertDatabaseHas('thesis_revisions', [
        'project_id' => $project->id,
        'defense_id' => $sempro->id,
        'status' => 'submitted',
    ]);

    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists((string) $updatedDocument->storage_path);
});

test('mahasiswa upload dokumen works without dosbing and notifies sempro thread', function () {
    Storage::fake('public');

    $student = createUserWithRole(AppRole::Mahasiswa->value);
    $examinerOne = createUserWithRole(AppRole::Dosen->value);
    $examinerTwo = createUserWithRole(AppRole::Dosen->value);
    $prodi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'is_active' => true,
    ]);

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'sempro',
        'state' => 'active',
        'started_at' => now()->subDays(4),
        'created_by' => $student->id,
    ]);

    $title = ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Judul Sempro Aktif',
        'title_en' => 'Active Sempro Title',
        'proposal_summary' => 'Proposal untuk sempro aktif.',
        'status' => 'approved',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subDays(4),
    ]);

    $sempro = ThesisDefense::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $title->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'scheduled',
        'result' => 'pending',
        'scheduled_for' => now()->addDays(2),
        'location' => 'Ruang Seminar C',
        'mode' => 'offline',
    ]);

    ThesisDefenseExaminer::query()->create([
        'defense_id' => $sempro->id,
        'lecturer_user_id' => $examinerOne->id,
        'role' => 'examiner',
        'order_no' => 1,
        'decision' => 'pending',
    ]);
    ThesisDefenseExaminer::query()->create([
        'defense_id' => $sempro->id,
        'lecturer_user_id' => $examinerTwo->id,
        'role' => 'examiner',
        'order_no' => 2,
        'decision' => 'pending',
    ]);

    $semproThread = MentorshipChatThread::query()->create([
        'student_user_id' => $student->id,
        'type' => 'sempro',
        'context_id' => $sempro->id,
        'label' => 'Sempro',
    ]);

    \App\Models\MentorshipChatThreadParticipant::query()->create([
        'thread_id' => $semproThread->id,
        'user_id' => $student->id,
        'role' => 'student',
    ]);
    \App\Models\MentorshipChatThreadParticipant::query()->create([
        'thread_id' => $semproThread->id,
        'user_id' => $examinerOne->id,
        'role' => 'examiner',
    ]);
    \App\Models\MentorshipChatThreadParticipant::query()->create([
        'thread_id' => $semproThread->id,
        'user_id' => $examinerTwo->id,
        'role' => 'examiner',
    ]);

    $this->actingAs($student)
        ->post('/mahasiswa/upload-dokumen', [
            'title' => 'Proposal Revisi Sempro',
            'category' => 'revisi-sempro',
            'document' => UploadedFile::fake()->create('proposal-revisi-sempro.pdf', 500, 'application/pdf'),
        ])
        ->assertRedirect('/mahasiswa/upload-dokumen');

    $this->assertDatabaseCount('mentorship_documents', 2);
    $this->assertDatabaseHas('mentorship_documents', [
        'student_user_id' => $student->id,
        'lecturer_user_id' => $examinerOne->id,
        'category' => 'revisi-sempro',
    ]);
    $this->assertDatabaseHas('mentorship_documents', [
        'student_user_id' => $student->id,
        'lecturer_user_id' => $examinerTwo->id,
        'category' => 'revisi-sempro',
    ]);
    $this->assertDatabaseHas('mentorship_chat_messages', [
        'mentorship_chat_thread_id' => $semproThread->id,
        'message_type' => 'document_event',
        'attachment_name' => 'proposal-revisi-sempro.pdf',
    ]);
});

test('mahasiswa upload dokumen uses valid mentorship assignment id for advisor recipients', function () {
    Storage::fake('public');

    $admin = createUserWithRole(AppRole::Admin->value);
    $student = createUserWithRole(AppRole::Mahasiswa->value);
    $advisor = createUserWithRole(AppRole::Dosen->value);
    $otherStudent = createUserWithRole(AppRole::Mahasiswa->value);
    $otherAdvisor = createUserWithRole(AppRole::Dosen->value);
    $prodi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'is_active' => true,
    ]);

    MahasiswaProfile::factory()->create([
        'user_id' => $otherStudent->id,
        'program_studi_id' => $prodi->id,
        'is_active' => true,
    ]);

    MentorshipAssignment::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $advisor->id,
        'advisor_type' => AdvisorType::Primary->value,
        'status' => AssignmentStatus::Active->value,
        'assigned_by' => $admin->id,
    ]);

    $otherProject = ensureActiveThesisProject($otherStudent);
    ThesisSupervisorAssignment::query()->create([
        'project_id' => $otherProject->id,
        'lecturer_user_id' => $otherAdvisor->id,
        'role' => AdvisorType::Primary->value,
        'status' => 'active',
        'assigned_by' => $admin->id,
        'started_at' => now(),
    ]);

    syncThesisSupervisor($student, $advisor, $admin, AdvisorType::Primary->value);

    $mentorAssignment = MentorshipAssignment::query()
        ->where('student_user_id', $student->id)
        ->where('lecturer_user_id', $advisor->id)
        ->firstOrFail();

    $thesisAssignment = ThesisSupervisorAssignment::query()
        ->whereHas('project', fn($query) => $query
            ->where('student_user_id', $student->id)
            ->where('state', 'active'))
        ->where('lecturer_user_id', $advisor->id)
        ->where('status', 'active')
        ->firstOrFail();

    expect($thesisAssignment->id)->not->toBe($mentorAssignment->id);

    $this->actingAs($student)
        ->post('/mahasiswa/upload-dokumen', [
            'title' => 'Draft Proposal Bimbingan',
            'category' => 'proposal',
            'document' => UploadedFile::fake()->create('proposal-bimbingan.pdf', 400, 'application/pdf'),
        ])
        ->assertRedirect('/mahasiswa/upload-dokumen');

    $document = MentorshipDocument::query()
        ->where('student_user_id', $student->id)
        ->where('lecturer_user_id', $advisor->id)
        ->firstOrFail();

    expect($document->mentorship_assignment_id)->toBe($mentorAssignment->id)
        ->and($document->mentorship_assignment_id)->not->toBe($thesisAssignment->id);
});

test('mahasiswa chat attachment is mirrored into active sempro thread', function () {
    Storage::fake('public');

    $admin = createUserWithRole(AppRole::Admin->value);
    $student = createUserWithRole(AppRole::Mahasiswa->value);
    $advisor = createUserWithRole(AppRole::Dosen->value);
    $examiner = createUserWithRole(AppRole::Dosen->value);
    $prodi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'is_active' => true,
    ]);

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'sempro',
        'state' => 'active',
        'started_at' => now()->subDays(4),
        'created_by' => $student->id,
    ]);

    $title = ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Judul Dengan Sempro Aktif',
        'title_en' => 'Title With Active Sempro',
        'proposal_summary' => 'Proposal untuk chat lampiran.',
        'status' => 'approved',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subDays(4),
    ]);

    MentorshipAssignment::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $advisor->id,
        'advisor_type' => AdvisorType::Primary->value,
        'status' => AssignmentStatus::Active->value,
        'assigned_by' => $admin->id,
    ]);

    $otherStudent = createUserWithRole(AppRole::Mahasiswa->value);
    $otherAdvisor = createUserWithRole(AppRole::Dosen->value);

    MahasiswaProfile::factory()->create([
        'user_id' => $otherStudent->id,
        'program_studi_id' => $prodi->id,
        'is_active' => true,
    ]);

    $otherProject = ensureActiveThesisProject($otherStudent);
    ThesisSupervisorAssignment::query()->create([
        'project_id' => $otherProject->id,
        'lecturer_user_id' => $otherAdvisor->id,
        'role' => AdvisorType::Primary->value,
        'status' => 'active',
        'assigned_by' => $admin->id,
        'started_at' => now(),
    ]);

    syncThesisSupervisor($student, $advisor, $admin, AdvisorType::Primary->value);

    $mentorAssignment = MentorshipAssignment::query()
        ->where('student_user_id', $student->id)
        ->where('lecturer_user_id', $advisor->id)
        ->firstOrFail();

    $thesisAssignment = ThesisSupervisorAssignment::query()
        ->whereHas('project', fn($query) => $query
            ->where('student_user_id', $student->id)
            ->where('state', 'active'))
        ->where('lecturer_user_id', $advisor->id)
        ->where('status', 'active')
        ->firstOrFail();

    expect($thesisAssignment->id)->not->toBe($mentorAssignment->id);

    $sempro = ThesisDefense::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $title->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'scheduled',
        'result' => 'pending',
        'scheduled_for' => now()->addDays(3),
        'location' => 'Ruang Seminar D',
        'mode' => 'offline',
    ]);

    ThesisDefenseExaminer::query()->create([
        'defense_id' => $sempro->id,
        'lecturer_user_id' => $examiner->id,
        'role' => 'examiner',
        'order_no' => 1,
        'decision' => 'pending',
    ]);

    $pembimbingThread = MentorshipChatThread::query()->create([
        'student_user_id' => $student->id,
        'type' => 'pembimbing',
    ]);

    $semproThread = MentorshipChatThread::query()->create([
        'student_user_id' => $student->id,
        'type' => 'sempro',
        'context_id' => $sempro->id,
        'label' => 'Sempro',
    ]);

    \App\Models\MentorshipChatThreadParticipant::query()->create([
        'thread_id' => $semproThread->id,
        'user_id' => $student->id,
        'role' => 'student',
    ]);
    \App\Models\MentorshipChatThreadParticipant::query()->create([
        'thread_id' => $semproThread->id,
        'user_id' => $examiner->id,
        'role' => 'examiner',
    ]);

    $this->actingAs($student)
        ->post("/mahasiswa/pesan/{$pembimbingThread->id}/messages", [
            'message' => 'Proposal terbaru untuk sempro',
            'attachment' => UploadedFile::fake()->create('proposal-chat-sempro.pdf', 350, 'application/pdf'),
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('mentorship_chat_messages', [
        'mentorship_chat_thread_id' => $pembimbingThread->id,
        'message_type' => 'document_event',
        'attachment_name' => 'proposal-chat-sempro.pdf',
    ]);
    $this->assertDatabaseHas('mentorship_chat_messages', [
        'mentorship_chat_thread_id' => $semproThread->id,
        'message_type' => 'document_event',
        'attachment_name' => 'proposal-chat-sempro.pdf',
    ]);

    $advisorDocument = MentorshipDocument::query()
        ->where('student_user_id', $student->id)
        ->where('lecturer_user_id', $advisor->id)
        ->where('category', 'lampiran-chat')
        ->firstOrFail();

    $examinerDocument = MentorshipDocument::query()
        ->where('student_user_id', $student->id)
        ->where('lecturer_user_id', $examiner->id)
        ->where('category', 'lampiran-chat')
        ->firstOrFail();

    expect($advisorDocument->mentorship_assignment_id)->toBe($mentorAssignment->id)
        ->and($advisorDocument->mentorship_assignment_id)->not->toBe($thesisAssignment->id)
        ->and($examinerDocument->mentorship_assignment_id)->toBeNull();
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

    $project = ThesisProject::query()->create([
        'student_user_id' => $owner->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'title_review',
        'state' => 'active',
        'started_at' => now(),
        'created_by' => $owner->id,
    ]);

    $title = ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Judul Project Native',
        'title_en' => 'Project Native Title',
        'proposal_summary' => 'Ringkasan native.',
        'status' => 'submitted',
        'submitted_by_user_id' => $owner->id,
        'submitted_at' => now(),
    ]);

    ThesisDocument::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $title->id,
        'uploaded_by_user_id' => $owner->id,
        'kind' => 'proposal',
        'status' => 'active',
        'version_no' => 1,
        'title' => 'Proposal Skripsi',
        'storage_disk' => 'public',
        'storage_path' => $path,
        'file_name' => 'proposal-project-native.pdf',
        'mime_type' => 'application/pdf',
        'file_size_kb' => 1,
        'uploaded_at' => now(),
    ]);

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
