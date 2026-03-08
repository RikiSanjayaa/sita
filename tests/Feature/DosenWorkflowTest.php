<?php

use App\Enums\AdvisorType;
use App\Enums\AssignmentStatus;
use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipAssignment;
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
    assignDefenseExaminer($assignedSidang, $dosen, 'chair', 1);
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

test('dosen defense decision keeps defense open until all examiners decide and then completes it', function () {
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
    ]);

    $this->assertDatabaseHas('thesis_defenses', [
        'id' => $defense->id,
        'status' => 'scheduled',
        'result' => 'pending',
    ]);

    $this->assertDatabaseHas('thesis_revisions', [
        'project_id' => $project->id,
        'defense_id' => $defense->id,
        'requested_by_user_id' => $dosen->id,
        'status' => 'open',
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
        'status' => 'completed',
        'result' => 'pass_with_revision',
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
