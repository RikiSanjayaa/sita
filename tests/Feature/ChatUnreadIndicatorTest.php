<?php

use App\Enums\AdvisorType;
use App\Events\ChatUnreadUpdated;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatRead;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipChatThreadParticipant;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\ThesisProject;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{student: User, lecturer: User, thread: MentorshipChatThread, project: ThesisProject}
 */
function unreadBimbingan(string $assignmentStatus = 'active'): array
{
    $student = User::factory()->asMahasiswa()->create();
    $lecturer = User::factory()->asDosen()->create();
    $admin = User::factory()->asAdmin()->create();

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => ProgramStudi::factory()->create()->id,
        'phase' => 'sempro',
        'state' => 'active',
        'started_at' => now()->subWeek(),
    ]);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $project->id,
        'lecturer_user_id' => $lecturer->id,
        'role' => AdvisorType::Primary->value,
        'status' => $assignmentStatus,
        'assigned_by' => $admin->id,
        'started_at' => now(),
    ]);

    $thread = MentorshipChatThread::factory()->create([
        'student_user_id' => $student->id,
        'type' => 'pembimbing',
    ]);

    return compact('student', 'lecturer', 'thread', 'project');
}

function unreadMessage(MentorshipChatThread $thread, ?User $sender, string $type = 'text', $at = null): MentorshipChatMessage
{
    return MentorshipChatMessage::factory()->create([
        'mentorship_chat_thread_id' => $thread->id,
        'sender_user_id' => $sender?->id,
        'message_type' => $type,
        'message' => 'Halo',
        'created_at' => $at ?? now(),
    ]);
}

function unreadRead($test, User $user, string $route, MentorshipChatThread $thread, ?int $messageId = null)
{
    return $test->actingAs($user)->postJson(route($route, $thread), [
        'last_message_id' => $messageId ?? $thread->messages()->max('id'),
    ]);
}

function unreadFlag($test, User $user, ?string $role = null): bool
{
    $request = $role === null ? $test->actingAs($user) : $test->actingAs($user)->withSession(['active_role' => $role]);

    return $request->getJson(route('chat.unread'))->assertOk()->json('hasUnreadMessages');
}

it('requires authentication for the unread endpoint', function (): void {
    $this->getJson(route('chat.unread'))->assertUnauthorized();
});

it('turns on for an incoming message and off only after the receipt is saved', function (): void {
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = unreadBimbingan();

    expect(unreadFlag($this, $student))->toBeFalse()
        ->and(unreadFlag($this, $lecturer))->toBeFalse();

    unreadMessage($thread, $lecturer, 'text', now()->subMinute());

    expect(unreadFlag($this, $student))->toBeTrue()
        ->and(unreadFlag($this, $lecturer))->toBeFalse();

    // Visiting the page does not read anything.
    $this->actingAs($student)->get(route('mahasiswa.pesan'))->assertOk();
    expect(unreadFlag($this, $student))->toBeTrue();

    unreadRead($this, $student, 'mahasiswa.pesan.read', $thread)->assertOk();

    expect(unreadFlag($this, $student))->toBeFalse();

    unreadMessage($thread, $lecturer, 'text', now()->addMinute());
    expect(unreadFlag($this, $student))->toBeTrue();

    unreadRead($this, $lecturer, 'dosen.pesan.read', $thread)->assertOk();
    expect(unreadFlag($this, $lecturer))->toBeFalse();
});

it('stays on until every thread with unread messages is read', function (): void {
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $bimbingan] = unreadBimbingan();
    $other = User::factory()->asDosen()->create();

    $private = MentorshipChatThread::factory()->create(['student_user_id' => $student->id, 'type' => 'private', 'private_key' => $student->id.':'.$other->id]);
    foreach ([$student, $other] as $participant) {
        MentorshipChatThreadParticipant::query()->create(['thread_id' => $private->id, 'user_id' => $participant->id, 'role' => $participant->is($student) ? 'student' : 'lecturer']);
    }

    unreadMessage($bimbingan, $lecturer, 'text', now()->subMinutes(2));
    unreadMessage($private, $other, 'text', now()->subMinute());

    unreadRead($this, $student, 'mahasiswa.pesan.read', $bimbingan)->assertOk();
    expect(unreadFlag($this, $student))->toBeTrue();

    unreadRead($this, $student, 'mahasiswa.pesan.read', $private)->assertOk();
    expect(unreadFlag($this, $student))->toBeFalse();
});

it('ignores own messages and document_event system messages', function (): void {
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = unreadBimbingan();

    unreadMessage($thread, $student);
    unreadMessage($thread, $lecturer, 'document_event');

    // The student's own message and the system event are not unread for the student;
    // the student's message is an ordinary incoming message for the lecturer.
    expect(unreadFlag($this, $student))->toBeFalse()
        ->and(unreadFlag($this, $lecturer))->toBeTrue();

    unreadRead($this, $lecturer, 'dosen.pesan.read', $thread)->assertOk();
    unreadMessage($thread, $student, 'document_event', now()->addMinute());

    expect(unreadFlag($this, $lecturer))->toBeFalse();
});

it('keeps unread state isolated per account, role and forbidden thread', function (): void {
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = unreadBimbingan();
    $stranger = User::factory()->asMahasiswa()->create();
    $strangerLecturer = User::factory()->asDosen()->create();
    $admin = User::factory()->asAdmin()->create();

    unreadMessage($thread, $lecturer);

    expect(unreadFlag($this, $stranger))->toBeFalse()
        ->and(unreadFlag($this, $strangerLecturer))->toBeFalse()
        ->and(unreadFlag($this, $admin))->toBeFalse();

    unreadRead($this, $stranger, 'mahasiswa.pesan.read', $thread)->assertForbidden();
    expect(MentorshipChatRead::query()->count())->toBe(0)
        ->and(unreadFlag($this, $student))->toBeTrue();

    // A dual-role account only sees the unread state of the active role.
    $dual = User::factory()->asMahasiswa()->create();
    $dual->roles()->syncWithoutDetaching([Role::query()->firstOrCreate(['name' => 'dosen'])->id]);
    $thread2 = MentorshipChatThread::factory()->create(['student_user_id' => $dual->id, 'type' => 'pembimbing']);
    unreadMessage($thread2, $lecturer);

    expect(unreadFlag($this, $dual, 'mahasiswa'))->toBeTrue()
        ->and(unreadFlag($this, $dual, 'dosen'))->toBeFalse();
});

it('does not create a pembimbing thread when computing the indicator', function (): void {
    $student = User::factory()->asMahasiswa()->create();

    expect(unreadFlag($this, $student))->toBeFalse();
    $this->actingAs($student)->get(route('dashboard'));

    expect(MentorshipChatThread::query()->count())->toBe(0);
});

it('counts messages of an archived (ended) supervision for the lecturer', function (): void {
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = unreadBimbingan('ended');
    unreadMessage($thread, $student);

    expect(unreadFlag($this, $lecturer))->toBeTrue();
});

it('exposes the flag as a lazy shared inertia prop', function (): void {
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = unreadBimbingan();
    unreadMessage($thread, $lecturer);

    $this->actingAs($student)
        ->get(route('mahasiswa.pesan'))
        ->assertInertia(fn (Assert $page) => $page->where('hasUnreadMessages', true));
});

it('reading notifications and reading chats do not affect each other', function (): void {
    Notification::fake();
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = unreadBimbingan();
    unreadMessage($thread, $lecturer);

    $student->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => RealtimeNotification::class,
        'data' => ['title' => 'x'],
    ]);

    $this->actingAs($student)->post('/settings/notifications/read-all')->assertSuccessful();

    expect($student->unreadNotifications()->count())->toBe(0)
        ->and(unreadFlag($this, $student))->toBeTrue();

    unreadRead($this, $student, 'mahasiswa.pesan.read', $thread)->assertOk();
    expect(unreadFlag($this, $student))->toBeFalse();
});

it('broadcasts an identity-free invalidation to the recipient regardless of notification preferences', function (): void {
    Event::fake([ChatUnreadUpdated::class]);
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = unreadBimbingan();
    $student->forceFill(['notification_preferences' => ['pesanBaru' => false, 'feedbackDokumen' => false]])->save();

    unreadMessage($thread, $lecturer);

    Event::assertDispatched(ChatUnreadUpdated::class, fn (ChatUnreadUpdated $event): bool => $event->userId === $student->id);
    Event::assertNotDispatched(ChatUnreadUpdated::class, fn (ChatUnreadUpdated $event): bool => $event->userId === $lecturer->id);
});

it('broadcasts to the reader when a receipt really changes, and not for no-op saves or system events', function (): void {
    Event::fake([ChatUnreadUpdated::class]);
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = unreadBimbingan();

    unreadMessage($thread, $lecturer, 'document_event');
    Event::assertNotDispatched(ChatUnreadUpdated::class);

    unreadMessage($thread, $lecturer, 'text', now()->subMinute());
    Event::assertDispatchedTimes(ChatUnreadUpdated::class, 1);

    unreadRead($this, $student, 'mahasiswa.pesan.read', $thread)->assertOk();
    Event::assertDispatchedTimes(ChatUnreadUpdated::class, 2);
    Event::assertDispatched(ChatUnreadUpdated::class, fn (ChatUnreadUpdated $event): bool => $event->userId === $student->id);

    unreadRead($this, $student, 'mahasiswa.pesan.read', $thread)->assertOk();
    Event::assertDispatchedTimes(ChatUnreadUpdated::class, 2);
});

it('keeps the broadcast payload and channel free of chat or person data', function (): void {
    $event = new ChatUnreadUpdated(42);

    expect($event->broadcastWith())->toBe([])
        ->and($event->broadcastAs())->toBe('chat.unread.updated')
        ->and($event->broadcastOn()->name)->toBe('private-chat.unread.42');
});

it('authorises only the owner on the private unread channel through /broadcasting/auth', function (): void {
    // The null test driver skips channel authorisation, so run the real Pusher-protocol auth with dummy credentials.
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);
    app(BroadcastManager::class)->forgetDrivers();
    require base_path('routes/channels.php');

    $user = User::factory()->asMahasiswa()->create();
    $other = User::factory()->asMahasiswa()->create();
    $auth = fn (User $actor, int $channelOwnerId) => $this->actingAs($actor)->post('/broadcasting/auth', [
        'channel_name' => 'private-chat.unread.'.$channelOwnerId,
        'socket_id' => '1234.5678',
    ]);

    $auth($user, $user->id)->assertOk()->assertJsonStructure(['auth']);
    $auth($user, $other->id)->assertForbidden();
    $auth($other, $user->id)->assertForbidden();
});

it('does not broadcast for messages without a sender', function (): void {
    Event::fake([ChatUnreadUpdated::class]);
    ['thread' => $thread] = unreadBimbingan();

    unreadMessage($thread, null);

    Event::assertNotDispatched(ChatUnreadUpdated::class);
});

it('shows the latest 50 messages in ascending order to the student when a thread has more', function (): void {
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = unreadBimbingan();

    foreach (range(1, 55) as $i) {
        MentorshipChatMessage::factory()->create([
            'mentorship_chat_thread_id' => $thread->id,
            'sender_user_id' => $lecturer->id,
            'message_type' => 'text',
            'message' => 'Pesan '.$i,
            'created_at' => now()->subMinutes(100 - $i),
        ]);
    }

    $this->actingAs($student)
        ->get(route('mahasiswa.pesan'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('threads.0.messages', 50)
            ->where('threads.0.messages.0.message', 'Pesan 6')
            ->where('threads.0.messages.49.message', 'Pesan 55'));
});

it('requires a valid last_message_id from the same thread when saving a receipt', function (): void {
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = unreadBimbingan();
    $foreign = unreadMessage(MentorshipChatThread::factory()->create(['type' => 'pembimbing']), $lecturer);
    unreadMessage($thread, $lecturer);

    $this->actingAs($student)->postJson(route('mahasiswa.pesan.read', $thread))->assertUnprocessable();
    unreadRead($this, $student, 'mahasiswa.pesan.read', $thread, $foreign->id)->assertUnprocessable();
    $this->actingAs($lecturer)->postJson(route('dosen.pesan.read', $thread), ['last_message_id' => $foreign->id])->assertUnprocessable();

    expect(MentorshipChatRead::query()->count())->toBe(0)
        ->and(unreadFlag($this, $student))->toBeTrue();
});

it('only reads up to the last seen message and never moves the receipt backwards', function (): void {
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = unreadBimbingan();
    $first = unreadMessage($thread, $lecturer, 'text', now()->subMinutes(3));
    $second = unreadMessage($thread, $lecturer, 'text', now()->subMinutes(2));

    unreadRead($this, $student, 'mahasiswa.pesan.read', $thread, $first->id)->assertOk();
    expect(unreadFlag($this, $student))->toBeTrue();

    unreadRead($this, $student, 'mahasiswa.pesan.read', $thread, $second->id)->assertOk();
    expect(unreadFlag($this, $student))->toBeFalse();

    // A stale tab that only saw the first message must not make the second unread again.
    unreadRead($this, $student, 'mahasiswa.pesan.read', $thread, $first->id)->assertOk();
    expect(unreadFlag($this, $student))->toBeFalse();
});

it('keeps a message that arrives in the same second as the receipt unread', function (): void {
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = unreadBimbingan();
    $at = now()->startOfSecond();

    $seen = unreadMessage($thread, $lecturer, 'text', $at);
    unreadRead($this, $student, 'mahasiswa.pesan.read', $thread, $seen->id)->assertOk();
    expect(unreadFlag($this, $student))->toBeFalse();

    unreadMessage($thread, $lecturer, 'text', $at);

    expect(unreadFlag($this, $student))->toBeTrue();
    expect(MentorshipChatRead::query()->firstOrFail()->last_read_message_id)->toBe($seen->id);
});

it('never lowers the id watermark when an older window is saved afterwards', function (): void {
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = unreadBimbingan();
    $at = now()->startOfSecond();
    $first = unreadMessage($thread, $lecturer, 'text', $at);
    $second = unreadMessage($thread, $lecturer, 'text', $at);

    unreadRead($this, $student, 'mahasiswa.pesan.read', $thread, $second->id)->assertOk();
    unreadRead($this, $student, 'mahasiswa.pesan.read', $thread, $first->id)->assertOk();

    expect(MentorshipChatRead::query()->firstOrFail()->last_read_message_id)->toBe($second->id)
        ->and(unreadFlag($this, $student))->toBeFalse();
});

it('falls back to the timestamp for receipts saved before the id watermark existed', function (): void {
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = unreadBimbingan();
    unreadMessage($thread, $lecturer, 'text', now()->subMinutes(2));

    MentorshipChatRead::query()->create([
        'mentorship_chat_thread_id' => $thread->id,
        'user_id' => $student->id,
        'last_read_at' => now()->subMinute(),
        'last_read_message_id' => null,
    ]);

    expect(unreadFlag($this, $student))->toBeFalse();

    unreadMessage($thread, $lecturer, 'text', now());
    expect(unreadFlag($this, $student))->toBeTrue();

    // The first new-style receipt upgrades the legacy row to an id watermark.
    unreadRead($this, $student, 'mahasiswa.pesan.read', $thread)->assertOk();
    expect(unreadFlag($this, $student))->toBeFalse()
        ->and(MentorshipChatRead::query()->firstOrFail()->last_read_message_id)->not->toBeNull();
});
