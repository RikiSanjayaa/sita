<?php

use App\Enums\AppRole;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\Role;
use App\Models\User;

function createAdminVisibilityUser(string $role): User
{
    $user = User::factory()->create(['last_active_role' => $role]);
    $roleModel = Role::query()->firstOrCreate(['name' => $role]);
    $user->roles()->sync([$roleModel->id]);

    return $user;
}

test('admin can access chat metadata page', function () {
    $admin = createAdminVisibilityUser(AppRole::Admin->value);

    $this->actingAs($admin)
        ->get('/admin/aktivitas-sistem')
        ->assertOk();
});

test('non-admin cannot access admin metadata page', function () {
    $mahasiswa = createAdminVisibilityUser(AppRole::Mahasiswa->value);

    $this->actingAs($mahasiswa)
        ->get('/admin/aktivitas-sistem')
        ->assertForbidden();
});

test('admin must use escalation to open chat content', function () {
    $admin = createAdminVisibilityUser(AppRole::Admin->value);
    $student = createAdminVisibilityUser(AppRole::Mahasiswa->value);

    $thread = MentorshipChatThread::query()->create([
        'student_user_id' => $student->id,
        'is_escalated' => false,
    ]);
    MentorshipChatMessage::query()->create([
        'mentorship_chat_thread_id' => $thread->id,
        'sender_user_id' => $student->id,
        'message_type' => 'text',
        'message' => 'Halo dosen.',
        'sent_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get("/admin/chat/threads/{$thread->id}")
        ->assertForbidden();

    $this->actingAs($admin)
        ->get("/admin/chat/threads/{$thread->id}?escalated=1")
        ->assertOk()
        ->assertJsonStructure([
            'thread_id',
            'messages' => [['author', 'message', 'time']],
        ]);
});
