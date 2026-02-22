<?php

use App\Enums\AdvisorType;
use App\Enums\AppRole;
use App\Enums\AssignmentStatus;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipAssignment;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
        'status_akademik' => 'aktif',
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
        'status_akademik' => 'aktif',
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
        'status_akademik' => 'aktif',
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

test('download permissions enforce ownership and escalation rules', function () {
    Storage::fake('public');

    $admin = createUserWithRole(AppRole::Admin->value);
    $student = createUserWithRole(AppRole::Mahasiswa->value);
    $otherStudent = createUserWithRole(AppRole::Mahasiswa->value);
    $dosen = createUserWithRole(AppRole::Dosen->value);

    MahasiswaProfile::factory()->create(['user_id' => $student->id, 'status_akademik' => 'aktif']);
    MahasiswaProfile::factory()->create(['user_id' => $otherStudent->id, 'status_akademik' => 'aktif']);

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
