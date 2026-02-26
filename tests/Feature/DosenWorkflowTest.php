<?php

use App\Enums\AdvisorType;
use App\Enums\AssignmentStatus;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipAssignment;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipDocument;
use App\Models\MentorshipSchedule;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function createDosenUser(): User
{
    return User::factory()->asDosen()->create();
}

function createMahasiswaUser(string $nim): User
{
    $student = User::factory()->asMahasiswa()->create();

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'nim' => $nim,
        'status_akademik' => 'aktif',
    ]);

    return $student;
}

function assignStudentToLecturer(User $student, User $lecturer, User $admin): MentorshipAssignment
{
    return MentorshipAssignment::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $lecturer->id,
        'advisor_type' => AdvisorType::Primary->value,
        'status' => AssignmentStatus::Active->value,
        'assigned_by' => $admin->id,
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
        ->assertInertia(fn (Assert $page) => $page
            ->component('dosen/jadwal-bimbingan')
            ->has('pendingRequests', 1)
            ->where('pendingRequests.0.mahasiswa', $assignedStudent->name)
        );

    $this->actingAs($dosen)
        ->get(route('dosen.dokumen-revisi'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dosen/dokumen-revisi')
            ->has('documentQueue', 1)
            ->where('documentQueue.0.mahasiswa', $assignedStudent->name)
        );

    $this->actingAs($dosen)
        ->get(route('dosen.pesan-bimbingan'))
        ->assertInertia(fn (Assert $page) => $page
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
