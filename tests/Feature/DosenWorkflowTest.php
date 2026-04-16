<?php

use App\Enums\AdvisorType;
use App\Enums\AssignmentStatus;
use App\Models\AdminProfile;
use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipAssignment;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipChatThreadParticipant;
use App\Models\MentorshipDocument;
use App\Models\MentorshipSchedule;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisRevision;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use App\Services\MentorshipAccessService;
use App\Services\ThesisDefenseExaminerDecisionService;
use Filament\Notifications\DatabaseNotification as FilamentDatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function createDosenUser(): User
{
    $programStudi = ProgramStudi::query()->firstOrCreate([
        'slug' => 'ilmu-komputer-test',
    ], [
        'name' => 'Ilmu Komputer Test',
        'concentrations' => [ProgramStudi::DEFAULT_GENERAL_CONCENTRATION],
    ]);

    $lecturer = User::factory()->asDosen()->create();

    DosenProfile::factory()->create([
        'user_id' => $lecturer->id,
        'program_studi_id' => $programStudi->id,
        'concentration' => ProgramStudi::DEFAULT_GENERAL_CONCENTRATION,
        'is_active' => true,
    ]);

    return $lecturer;
}

function createMahasiswaUser(string $nim): User
{
    $programStudi = ProgramStudi::query()->firstOrCreate([
        'slug' => 'ilmu-komputer-test',
    ], [
        'name' => 'Ilmu Komputer Test',
        'concentrations' => [ProgramStudi::DEFAULT_GENERAL_CONCENTRATION],
    ]);

    $student = User::factory()->asMahasiswa()->create();

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'nim' => $nim,
        'program_studi_id' => $programStudi->id,
        'concentration' => ProgramStudi::DEFAULT_GENERAL_CONCENTRATION,
        'is_active' => true,
    ]);

    return $student;
}

function assignStudentToLecturer(User $student, User $lecturer, User $admin): MentorshipAssignment
{
    $assignment = MentorshipAssignment::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $lecturer->id,
        'advisor_type' => AdvisorType::Primary->value,
        'status' => AssignmentStatus::Active->value,
        'assigned_by' => $admin->id,
    ]);

    $project = ThesisProject::query()
        ->where('student_user_id', $student->id)
        ->where('state', 'active')
        ->latest('id')
        ->first();

    if (! $project instanceof ThesisProject) {
        $project = createThesisProjectForStudent($student);
    }

    ThesisSupervisorAssignment::query()->updateOrCreate(
        [
            'project_id' => $project->id,
            'role' => AdvisorType::Primary->value,
            'status' => 'active',
        ],
        [
            'lecturer_user_id' => $lecturer->id,
            'assigned_by' => $admin->id,
            'started_at' => now(),
        ],
    );

    return $assignment;
}

function createThesisProjectForStudent(User $student): ThesisProject
{
    $profile = $student->mahasiswaProfile;

    if ($profile === null) {
        throw new RuntimeException('Mahasiswa profile is required for thesis project test setup.');
    }

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $profile->program_studi_id,
        'phase' => 'sempro',
        'state' => 'active',
        'started_at' => now()->subWeek(),
    ]);

    ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Sistem Informasi Akademik Adaptif',
        'title_en' => 'Adaptive Academic Information System',
        'proposal_summary' => 'Ringkasan pengajuan tugas akhir untuk pengujian dosen.',
        'status' => 'approved',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subDays(6),
        'decided_at' => now()->subDays(5),
    ]);

    return $project;
}

function assignDefenseExaminer(ThesisDefense $defense, User $lecturer, string $role, int $order): ThesisDefenseExaminer
{
    return ThesisDefenseExaminer::query()->create([
        'defense_id' => $defense->id,
        'lecturer_user_id' => $lecturer->id,
        'role' => $role,
        'order_no' => $order,
        'decision' => 'pending',
        'assigned_by' => $lecturer->id,
    ]);
}

test('dosen pages only show data from assigned mahasiswa', function () {
    $admin = User::factory()->asAdmin()->create();
    $dosen = createDosenUser();
    $otherDosen = createDosenUser();
    $coAdvisor = createDosenUser();

    $assignedStudent = createMahasiswaUser('2210519991');
    $otherStudent = createMahasiswaUser('2210519992');

    $assigned = assignStudentToLecturer($assignedStudent, $dosen, $admin);
    $other = assignStudentToLecturer($otherStudent, $otherDosen, $admin);

    MentorshipSchedule::factory()->create([
        'student_user_id' => $assignedStudent->id,
        'lecturer_user_id' => $dosen->id,
        'mentorship_assignment_id' => $assigned->id,
        'topic' => 'Topik Assigned',
        'status' => 'pending',
        'created_by_user_id' => $assignedStudent->id,
    ]);
    MentorshipSchedule::factory()->create([
        'student_user_id' => $otherStudent->id,
        'lecturer_user_id' => $otherDosen->id,
        'mentorship_assignment_id' => $other->id,
        'topic' => 'Topik Other',
        'status' => 'pending',
        'created_by_user_id' => $otherStudent->id,
    ]);

    MentorshipDocument::factory()->create([
        'student_user_id' => $assignedStudent->id,
        'lecturer_user_id' => $dosen->id,
        'mentorship_assignment_id' => $assigned->id,
        'title' => 'Dokumen Assigned',
    ]);
    MentorshipDocument::factory()->create([
        'student_user_id' => $otherStudent->id,
        'lecturer_user_id' => $otherDosen->id,
        'mentorship_assignment_id' => $other->id,
        'title' => 'Dokumen Other',
    ]);

    $assignedThread = MentorshipChatThread::factory()->create([
        'student_user_id' => $assignedStudent->id,
    ]);
    MentorshipChatThread::factory()->create([
        'student_user_id' => $otherStudent->id,
    ]);

    MentorshipChatMessage::factory()->create([
        'mentorship_chat_thread_id' => $assignedThread->id,
        'sender_user_id' => $assignedStudent->id,
        'message' => 'Pesan assigned',
    ]);

    $assignedProject = ThesisProject::query()
        ->where('student_user_id', $assignedStudent->id)
        ->where('state', 'active')
        ->latest('id')
        ->firstOrFail();

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $assignedProject->id,
        'lecturer_user_id' => $coAdvisor->id,
        'role' => AdvisorType::Secondary->value,
        'status' => 'active',
        'assigned_by' => $admin->id,
        'started_at' => now(),
    ]);

    $this->actingAs($dosen)
        ->get(route('dosen.jadwal-bimbingan'))
        ->assertInertia(
            fn(Assert $page) => $page
                ->component('dosen/jadwal-bimbingan')
                ->has('pendingRequests', 1)
                ->where('pendingRequests.0.mahasiswa', $assignedStudent->name)
        );

    $this->actingAs($dosen)
        ->get(route('dosen.dokumen-revisi'))
        ->assertInertia(
            fn(Assert $page) => $page
                ->component('dosen/dokumen-revisi')
                ->has('documentQueue', 1)
                ->where('documentQueue.0.mahasiswa', $assignedStudent->name)
        );

    $this->actingAs($dosen)
        ->get(route('dosen.pesan-bimbingan'))
        ->assertInertia(
            fn(Assert $page) => $page
                ->component('dosen/pesan-bimbingan')
                ->has('threads', 1)
                ->where('threads.0.student', $assignedStudent->name)
                ->where('threads.0.members.0', $assignedStudent->name)
                ->where('threads.0.members.1', $dosen->name)
                ->where('threads.0.members.2', $coAdvisor->name)
                ->has('threads.0.memberProfiles', 3)
        );

    $this->actingAs($dosen)
        ->get(route('dosen.mahasiswa-bimbingan'))
        ->assertInertia(
            fn(Assert $page) => $page
                ->component('dosen/mahasiswa-bimbingan')
                ->has('mahasiswaRows', 1)
                ->where('mahasiswaRows.0.name', $assignedStudent->name)
                ->where('mahasiswaRows.0.otherAdvisors.0', 'Pembimbing 2: '.$coAdvisor->name)
        );
});

test('dosen can decide schedule only for own assignment', function () {
    $admin = User::factory()->asAdmin()->create();
    $dosen = createDosenUser();
    $otherDosen = createDosenUser();
    $student = createMahasiswaUser('2210519993');
    $foreignStudent = createMahasiswaUser('2210519996');

    $assignment = assignStudentToLecturer($student, $dosen, $admin);
    $foreignAssignment = assignStudentToLecturer($foreignStudent, $otherDosen, $admin);

    $ownSchedule = MentorshipSchedule::factory()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $dosen->id,
        'mentorship_assignment_id' => $assignment->id,
        'status' => 'pending',
        'created_by_user_id' => $student->id,
    ]);

    $foreignSchedule = MentorshipSchedule::factory()->create([
        'student_user_id' => $foreignStudent->id,
        'lecturer_user_id' => $otherDosen->id,
        'mentorship_assignment_id' => $foreignAssignment->id,
        'status' => 'pending',
        'created_by_user_id' => $foreignStudent->id,
    ]);

    $this->actingAs($dosen)
        ->post(route('dosen.jadwal-bimbingan.decision', $ownSchedule), [
            'decision' => 'approve',
            'location' => 'Google Meet',
            'lecturer_note' => 'Disetujui',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('mentorship_schedules', [
        'id' => $ownSchedule->id,
        'status' => 'approved',
        'scheduled_for' => $ownSchedule->requested_for?->format('Y-m-d H:i:s'),
    ]);

    $this->actingAs($dosen)
        ->post(route('dosen.jadwal-bimbingan.decision', $foreignSchedule), [
            'decision' => 'reject',
        ])
        ->assertForbidden();
});

test('examiner decision sends notification to mahasiswa', function () {
    Notification::fake();

    $student = createMahasiswaUser('2210519998');
    $examinerOne = createDosenUser();
    $examinerTwo = createDosenUser();

    $project = createThesisProjectForStudent($student);

    $defense = ThesisDefense::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $project->latestTitle?->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'scheduled',
        'result' => 'pending',
        'mode' => 'offline',
        'scheduled_for' => now()->addDays(2),
        'location' => 'Ruang Uji Sempro',
        'created_by' => $examinerOne->id,
    ]);

    assignDefenseExaminer($defense, $examinerOne, 'examiner', 1);
    assignDefenseExaminer($defense, $examinerTwo, 'examiner', 2);

    app(ThesisDefenseExaminerDecisionService::class)->submit($examinerOne, $defense, [
        'decision' => 'pass',
        'score' => 88,
        'decision_notes' => 'Presentasi sudah baik.',
        'revision_notes' => null,
    ]);

    Notification::assertSentTo($student, RealtimeNotification::class, function (RealtimeNotification $notification, array $channels) use ($student, $examinerOne): bool {
        $data = $notification->toArray($student);

        return in_array('broadcast', $channels, true)
            && $data['title'] === 'Nilai sempro dari penguji tersedia'
            && str_contains($data['description'], $examinerOne->name)
            && $data['preferenceKey'] === 'statusTugasAkhir';
    });
});

test('dosen can close confirmed schedule as completed or cancelled', function () {
    $admin = User::factory()->asAdmin()->create();
    $dosen = createDosenUser();
    $student = createMahasiswaUser('2210519998');

    $assignment = assignStudentToLecturer($student, $dosen, $admin);

    $schedule = MentorshipSchedule::factory()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $dosen->id,
        'mentorship_assignment_id' => $assignment->id,
        'status' => 'approved',
        'scheduled_for' => now()->addDay(),
        'created_by_user_id' => $student->id,
    ]);

    $this->actingAs($dosen)
        ->post(route('dosen.jadwal-bimbingan.decision', $schedule), [
            'decision' => 'complete',
            'lecturer_note' => 'Sesi selesai.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('mentorship_schedules', [
        'id' => $schedule->id,
        'status' => 'completed',
    ]);

    $this->actingAs($dosen)
        ->post(route('dosen.jadwal-bimbingan.decision', $schedule), [
            'decision' => 'cancel',
            'lecturer_note' => 'Dibatalkan.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('mentorship_schedules', [
        'id' => $schedule->id,
        'status' => 'cancelled',
    ]);
});

test('dosen must provide clear feedback for reject and reschedule', function () {
    $admin = User::factory()->asAdmin()->create();
    $dosen = createDosenUser();
    $student = createMahasiswaUser('2210519999');

    $assignment = assignStudentToLecturer($student, $dosen, $admin);
    $schedule = MentorshipSchedule::factory()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $dosen->id,
        'mentorship_assignment_id' => $assignment->id,
        'status' => 'pending',
        'created_by_user_id' => $student->id,
    ]);

    $this->actingAs($dosen)
        ->from(route('dosen.jadwal-bimbingan'))
        ->post(route('dosen.jadwal-bimbingan.decision', $schedule), [
            'decision' => 'reject',
        ])
        ->assertRedirect(route('dosen.jadwal-bimbingan'))
        ->assertSessionHasErrors('lecturer_note');

    $this->actingAs($dosen)
        ->from(route('dosen.jadwal-bimbingan'))
        ->post(route('dosen.jadwal-bimbingan.decision', $schedule), [
            'decision' => 'reschedule',
            'lecturer_note' => 'Silakan pindah ke jadwal lain.',
        ])
        ->assertRedirect(route('dosen.jadwal-bimbingan'))
        ->assertSessionHasErrors('scheduled_for');
});

test('dosen must provide feedback for approve decision', function () {
    $admin = User::factory()->asAdmin()->create();
    $dosen = createDosenUser();
    $student = createMahasiswaUser('2210519910');

    $assignment = assignStudentToLecturer($student, $dosen, $admin);
    $schedule = MentorshipSchedule::factory()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $dosen->id,
        'mentorship_assignment_id' => $assignment->id,
        'status' => 'pending',
        'created_by_user_id' => $student->id,
    ]);

    $this->actingAs($dosen)
        ->from(route('dosen.jadwal-bimbingan'))
        ->post(route('dosen.jadwal-bimbingan.decision', $schedule), [
            'decision' => 'approve',
            'location' => 'Ruang Bimbingan 2',
        ])
        ->assertRedirect(route('dosen.jadwal-bimbingan'))
        ->assertSessionHasErrors('lecturer_note');
});

test('dosen can review document and send message to own thread', function () {
    $admin = User::factory()->asAdmin()->create();
    $dosen = createDosenUser();
    $otherDosen = createDosenUser();
    $student = createMahasiswaUser('2210519994');
    $foreignStudent = createMahasiswaUser('2210519997');

    $assignment = assignStudentToLecturer($student, $dosen, $admin);
    $foreignAssignment = assignStudentToLecturer($foreignStudent, $otherDosen, $admin);

    $document = MentorshipDocument::factory()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $dosen->id,
        'mentorship_assignment_id' => $assignment->id,
        'status' => 'submitted',
    ]);

    $foreignDocument = MentorshipDocument::factory()->create([
        'student_user_id' => $foreignStudent->id,
        'lecturer_user_id' => $otherDosen->id,
        'mentorship_assignment_id' => $foreignAssignment->id,
        'status' => 'submitted',
    ]);

    $ownThread = MentorshipChatThread::factory()->create([
        'student_user_id' => $student->id,
    ]);

    $otherThread = MentorshipChatThread::factory()->create([
        'student_user_id' => createMahasiswaUser('2210519995')->id,
    ]);

    $this->actingAs($dosen)
        ->post(route('dosen.dokumen-revisi.review', $document), [
            'status' => 'approved',
            'revision_notes' => 'Sudah sesuai.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('mentorship_documents', [
        'id' => $document->id,
        'status' => 'approved',
    ]);

    $this->actingAs($dosen)
        ->post(route('dosen.dokumen-revisi.review', $foreignDocument), [
            'status' => 'needs_revision',
        ])
        ->assertForbidden();

    $this->actingAs($dosen)
        ->post(route('dosen.pesan-bimbingan.messages.store', $ownThread), [
            'message' => 'Mohon cek revisi bab 3.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('mentorship_chat_messages', [
        'mentorship_chat_thread_id' => $ownThread->id,
        'sender_user_id' => $dosen->id,
        'message' => 'Mohon cek revisi bab 3.',
    ]);

    $this->actingAs($dosen)
        ->post(route('dosen.pesan-bimbingan.messages.store', $otherThread), [
            'message' => 'Tidak boleh kirim.',
        ])
        ->assertForbidden();
});

test('dosen can authenticate mentorship thread broadcast channel through thesis supervisor assignment', function () {
    $admin = User::factory()->asAdmin()->create();
    $dosen = createDosenUser();
    $student = createMahasiswaUser('2210519911');

    $project = createThesisProjectForStudent($student);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $project->id,
        'lecturer_user_id' => $dosen->id,
        'role' => AdvisorType::Primary->value,
        'status' => 'active',
        'assigned_by' => $admin->id,
        'started_at' => now(),
    ]);

    $thread = MentorshipChatThread::factory()->create([
        'student_user_id' => $student->id,
        'type' => 'pembimbing',
    ]);

    $this->actingAs($dosen)
        ->post('/broadcasting/auth', [
            'channel_name' => 'private-mentorship.thread.'.$thread->id,
            'socket_id' => '1234.5678',
        ])
        ->assertOk();
});

test('dosen broadcast auth for sempro and sidang style threads still requires participant membership', function () {
    $dosen = createDosenUser();
    $otherDosen = createDosenUser();
    $student = createMahasiswaUser('2210519912');

    $thread = MentorshipChatThread::factory()->create([
        'student_user_id' => $student->id,
        'type' => 'sempro',
        'label' => 'Sempro',
    ]);

    MentorshipChatThreadParticipant::query()->create([
        'thread_id' => $thread->id,
        'user_id' => $student->id,
        'role' => 'student',
    ]);

    MentorshipChatThreadParticipant::query()->create([
        'thread_id' => $thread->id,
        'user_id' => $dosen->id,
        'role' => 'examiner',
    ]);

    $accessService = app(MentorshipAccessService::class);

    expect($accessService->canAccessThread($dosen, $thread))->toBeTrue();
    expect($accessService->canAccessThread($otherDosen, $thread))->toBeFalse();

    $this->actingAs($dosen)
        ->post('/broadcasting/auth', [
            'channel_name' => 'private-mentorship.thread.'.$thread->id,
            'socket_id' => '1234.5678',
        ])
        ->assertOk();
});

test('dosen seminar proposal page shows only assigned sempro and sidang defenses', function () {
    $dosen = createDosenUser();
    $otherDosen = createDosenUser();

    $assignedStudent = createMahasiswaUser('2210519001');
    $otherStudent = createMahasiswaUser('2210519002');

    $assignedProject = createThesisProjectForStudent($assignedStudent);
    $otherProject = createThesisProjectForStudent($otherStudent);

    $assignedSempro = ThesisDefense::query()->create([
        'project_id' => $assignedProject->id,
        'title_version_id' => $assignedProject->latestTitle?->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'scheduled',
        'result' => 'pending',
        'scheduled_for' => now()->addDays(2),
        'location' => 'Ruang Sidang A',
        'mode' => 'offline',
    ]);

    $assignedSidang = ThesisDefense::query()->create([
        'project_id' => $assignedProject->id,
        'title_version_id' => $assignedProject->latestTitle?->id,
        'type' => 'sidang',
        'attempt_no' => 1,
        'status' => 'scheduled',
        'result' => 'pending',
        'scheduled_for' => now()->addDays(7),
        'location' => 'Ruang Sidang B',
        'mode' => 'hybrid',
    ]);

    $foreignDefense = ThesisDefense::query()->create([
        'project_id' => $otherProject->id,
        'title_version_id' => $otherProject->latestTitle?->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'scheduled',
        'result' => 'pending',
        'scheduled_for' => now()->addDays(3),
        'location' => 'Ruang Sidang C',
        'mode' => 'offline',
    ]);

    assignDefenseExaminer($assignedSempro, $dosen, 'examiner', 1);
    assignDefenseExaminer($assignedSidang, $dosen, 'primary_supervisor', 1);
    assignDefenseExaminer($foreignDefense, $otherDosen, 'examiner', 1);

    $this->actingAs($dosen)
        ->get(route('dosen.seminar-proposal'))
        ->assertInertia(fn(Assert $page) => $page
            ->component('dosen/seminar-proposal')
            ->has('defenses', 2)
            ->where('defenses.0.studentName', $assignedStudent->name)
            ->where('defenses.0.type', 'sidang')
            ->where('defenses.1.type', 'sempro'));
});

test('dosen defense decision keeps defense scheduled until all examiners decide and then waits for admin finalization', function () {
    $dosen = createDosenUser();
    $otherDosen = createDosenUser();
    $student = createMahasiswaUser('2210519003');
    $project = createThesisProjectForStudent($student);

    $defense = ThesisDefense::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $project->latestTitle?->id,
        'type' => 'sidang',
        'attempt_no' => 1,
        'status' => 'scheduled',
        'result' => 'pending',
        'scheduled_for' => now()->addDays(5),
        'location' => 'Ruang Sidang Utama',
        'mode' => 'offline',
    ]);

    $ownExaminer = assignDefenseExaminer($defense, $dosen, 'examiner', 1);
    assignDefenseExaminer($defense, $otherDosen, 'examiner', 2);

    $this->actingAs($dosen)
        ->from(route('dosen.seminar-proposal'))
        ->post(route('dosen.seminar-proposal.decision', $defense), [
            'decision' => 'pass_with_revision',
            'score' => 86,
            'decision_notes' => 'Perlu perbaikan minor pada metodologi.',
            'revision_notes' => 'Lengkapi pembahasan evaluasi dan simpulan.',
        ])
        ->assertRedirect(route('dosen.seminar-proposal'));

    $this->assertDatabaseHas('thesis_defense_examiners', [
        'id' => $ownExaminer->id,
        'decision' => 'pass_with_revision',
        'revision_notes' => 'Lengkapi pembahasan evaluasi dan simpulan.',
    ]);

    $this->assertDatabaseHas('thesis_defenses', [
        'id' => $defense->id,
        'status' => 'scheduled',
        'result' => 'pending',
    ]);

    $this->assertDatabaseMissing('thesis_revisions', [
        'project_id' => $project->id,
        'defense_id' => $defense->id,
    ]);

    $this->actingAs($otherDosen)
        ->post(route('dosen.seminar-proposal.decision', $defense), [
            'decision' => 'pass',
            'score' => 90,
            'decision_notes' => 'Disetujui setelah perbaikan minor dicatat.',
        ])
        ->assertRedirect(route('dosen.seminar-proposal'));

    $this->assertDatabaseHas('thesis_defenses', [
        'id' => $defense->id,
        'status' => 'awaiting_finalization',
        'result' => 'pending',
    ]);

    $this->actingAs($dosen)
        ->from(route('dosen.seminar-proposal'))
        ->post(route('dosen.seminar-proposal.decision', $defense), [
            'decision' => 'pass',
            'score' => 88,
            'decision_notes' => 'Tidak boleh menimpa keputusan lama.',
        ])
        ->assertRedirect(route('dosen.seminar-proposal'))
        ->assertSessionHasErrors('decision');
});

test('dosen who requested sempro revision can resolve their own revision and advance project', function () {
    Notification::fake();

    $admin = User::factory()->asAdmin()->create();
    $dosen = createDosenUser();
    $otherDosen = createDosenUser();
    $student = createMahasiswaUser('2210519004');
    $project = createThesisProjectForStudent($student);

    AdminProfile::query()->create([
        'user_id' => $admin->id,
        'program_studi_id' => $project->program_studi_id,
    ]);

    $defense = ThesisDefense::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $project->latestTitle?->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'completed',
        'result' => 'pass_with_revision',
        'scheduled_for' => now()->subDays(3),
        'location' => 'Ruang Sempro A',
        'mode' => 'offline',
        'created_by' => $admin->id,
        'decided_by' => $admin->id,
        'decision_at' => now()->subDays(3),
        'notes' => 'Sempro perlu revisi minor.',
    ]);

    assignDefenseExaminer($defense, $dosen, 'examiner', 1)->forceFill([
        'decision' => 'pass_with_revision',
        'score' => 84,
        'notes' => 'Perbaiki alur pembahasan.',
        'revision_notes' => 'Lengkapi pembahasan hasil dan perbaiki simpulan.',
        'decided_at' => now()->subDays(3),
    ])->save();

    assignDefenseExaminer($defense, $otherDosen, 'examiner', 2)->forceFill([
        'decision' => 'pass',
        'score' => 88,
        'notes' => 'Sudah cukup baik.',
        'decided_at' => now()->subDays(3),
    ])->save();

    $revision = ThesisRevision::query()->create([
        'project_id' => $project->id,
        'defense_id' => $defense->id,
        'requested_by_user_id' => $dosen->id,
        'status' => 'submitted',
        'notes' => 'Lengkapi pembahasan hasil dan perbaiki simpulan.',
        'submitted_at' => now()->subDay(),
        'due_at' => now()->addDays(3),
    ]);

    $this->actingAs($dosen)
        ->post(route('dosen.seminar-proposal.revisions.resolve', $revision))
        ->assertRedirect(route('dosen.seminar-proposal'));

    $this->assertDatabaseHas('thesis_revisions', [
        'id' => $revision->id,
        'status' => 'resolved',
        'resolved_by_user_id' => $dosen->id,
    ]);

    $this->assertDatabaseHas('thesis_projects', [
        'id' => $project->id,
        'phase' => 'research',
        'state' => 'active',
    ]);

    expect(ThesisProjectEvent::query()
        ->where('project_id', $project->id)
        ->where('event_type', 'revision_resolved')
        ->count())->toBe(1);

    Notification::assertSentTo($student, RealtimeNotification::class, function (RealtimeNotification $notification, array $channels) use ($student): bool {
        $data = $notification->toArray($student);

        return in_array('database', $channels, true)
            && $data['title'] === 'Revisi sempro disetujui'
            && $data['preferenceKey'] === 'statusTugasAkhir';
    });

    $adminNotifications = Notification::sent($admin, FilamentDatabaseNotification::class);

    expect($adminNotifications)->toHaveCount(1)
        ->and($adminNotifications->first()?->toArray()['title'] ?? null)->toBe('Revisi sempro selesai');
});

test('sidang project completes only after all requesting lecturers resolve their revisions', function () {
    Notification::fake();

    $admin = User::factory()->asAdmin()->create();
    $firstLecturer = createDosenUser();
    $secondLecturer = createDosenUser();
    $student = createMahasiswaUser('2210519005');
    $project = createThesisProjectForStudent($student);

    AdminProfile::query()->create([
        'user_id' => $admin->id,
        'program_studi_id' => $project->program_studi_id,
    ]);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $project->id,
        'lecturer_user_id' => $firstLecturer->id,
        'role' => AdvisorType::Primary->value,
        'status' => 'active',
        'assigned_by' => $admin->id,
        'started_at' => now()->subDays(14),
    ]);

    $project->forceFill([
        'phase' => 'sidang',
        'state' => 'active',
    ])->save();

    $defense = ThesisDefense::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $project->latestTitle?->id,
        'type' => 'sidang',
        'attempt_no' => 1,
        'status' => 'completed',
        'result' => 'pass_with_revision',
        'scheduled_for' => now()->subDays(5),
        'location' => 'Ruang Sidang 3',
        'mode' => 'offline',
        'created_by' => $admin->id,
        'decided_by' => $admin->id,
        'decision_at' => now()->subDays(5),
        'notes' => 'Sidang perlu revisi akhir.',
    ]);

    assignDefenseExaminer($defense, $firstLecturer, 'primary_supervisor', 1)->forceFill([
        'decision' => 'pass_with_revision',
        'score' => 85,
        'revision_notes' => 'Rapikan abstrak.',
        'decided_at' => now()->subDays(5),
    ])->save();

    assignDefenseExaminer($defense, $secondLecturer, 'examiner', 2)->forceFill([
        'decision' => 'pass_with_revision',
        'score' => 87,
        'revision_notes' => 'Perbaiki daftar pustaka.',
        'decided_at' => now()->subDays(5),
    ])->save();

    $firstRevision = ThesisRevision::query()->create([
        'project_id' => $project->id,
        'defense_id' => $defense->id,
        'requested_by_user_id' => $firstLecturer->id,
        'status' => 'submitted',
        'notes' => 'Rapikan abstrak.',
        'submitted_at' => now()->subDay(),
    ]);

    $secondRevision = ThesisRevision::query()->create([
        'project_id' => $project->id,
        'defense_id' => $defense->id,
        'requested_by_user_id' => $secondLecturer->id,
        'status' => 'submitted',
        'notes' => 'Perbaiki daftar pustaka.',
        'submitted_at' => now()->subDay(),
    ]);

    $this->actingAs($firstLecturer)
        ->post(route('dosen.seminar-proposal.revisions.resolve', $firstRevision))
        ->assertRedirect(route('dosen.seminar-proposal'));

    $this->assertDatabaseHas('thesis_projects', [
        'id' => $project->id,
        'phase' => 'sidang',
        'state' => 'active',
    ]);

    $this->assertDatabaseHas('thesis_revisions', [
        'id' => $firstRevision->id,
        'status' => 'resolved',
    ]);

    expect(Notification::sent($admin, FilamentDatabaseNotification::class))->toHaveCount(0);

    $this->actingAs($firstLecturer)
        ->post(route('dosen.seminar-proposal.revisions.resolve', $secondRevision))
        ->assertRedirect(route('dosen.seminar-proposal'));

    $this->assertDatabaseHas('thesis_revisions', [
        'id' => $secondRevision->id,
        'status' => 'submitted',
    ]);

    $this->actingAs($secondLecturer)
        ->post(route('dosen.seminar-proposal.revisions.resolve', $secondRevision))
        ->assertRedirect(route('dosen.seminar-proposal'));

    $this->assertDatabaseHas('thesis_projects', [
        'id' => $project->id,
        'phase' => 'completed',
        'state' => 'completed',
        'closed_by' => $secondLecturer->id,
    ]);

    $this->assertDatabaseHas('thesis_supervisor_assignments', [
        'project_id' => $project->id,
        'lecturer_user_id' => $firstLecturer->id,
        'status' => 'ended',
    ]);

    expect(ThesisProjectEvent::query()
        ->where('project_id', $project->id)
        ->where('event_type', 'revision_resolved')
        ->count())->toBe(2);

    $adminNotifications = Notification::sent($admin, FilamentDatabaseNotification::class);

    expect($adminNotifications)->toHaveCount(1)
        ->and($adminNotifications->first()?->toArray()['title'] ?? null)->toBe('Revisi sidang selesai');
});
