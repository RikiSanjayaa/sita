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

function unreadCount($test, User $user, ?string $role = null): int
{
    $request = $role === null ? $test->actingAs($user) : $test->actingAs($user)->withSession(['active_role' => $role]);
    $response = $request->getJson(route('chat.unread'))->assertOk();

    expect(array_keys($response->json()))->toBe(['unreadMessageCount'])
        ->and($response->json('unreadMessageCount'))->toBeInt();

    return $response->json('unreadMessageCount');
}

it('requires authentication for the unread endpoint', function (): void {
    $this->getJson(route('chat.unread'))->assertUnauthorized();
});

it('turns on for an incoming message and off only after the receipt is saved', function (): void {
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = unreadBimbingan();

    expect(unreadCount($this, $student))->toBe(0)
        ->and(unreadCount($this, $lecturer))->toBe(0);

    unreadMessage($thread, $lecturer, 'text', now()->subMinute());

    expect(unreadCount($this, $student))->toBe(1)
        ->and(unreadCount($this, $lecturer))->toBe(0);

    // Visiting the page does not read anything.
    $this->actingAs($student)->get(route('mahasiswa.pesan'))->assertOk();
    expect(unreadCount($this, $student))->toBe(1);

    unreadRead($this, $student, 'mahasiswa.pesan.read', $thread)->assertOk();

    expect(unreadCount($this, $student))->toBe(0);

    unreadMessage($thread, $lecturer, 'text', now()->addMinute());
    expect(unreadCount($this, $student))->toBe(1);

    unreadRead($this, $lecturer, 'dosen.pesan.read', $thread)->assertOk();
    expect(unreadCount($this, $lecturer))->toBe(0);
});

it('acknowledges a read with exact remaining thread and sidebar counts', function (string $role): void {
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = unreadBimbingan();
    $reader = $role === 'mahasiswa' ? $student : $lecturer;
    $sender = $role === 'mahasiswa' ? $lecturer : $student;
    $seen = unreadMessage($thread, $sender);
    unreadMessage($thread, $sender);
    unreadMessage($thread, $reader);
    unreadMessage($thread, $sender, 'document_event');

    $private = MentorshipChatThread::factory()->create(['student_user_id' => $student->id, 'type' => 'private', 'private_key' => $student->id.':'.$lecturer->id]);
    foreach ([$student, $lecturer] as $participant) {
        MentorshipChatThreadParticipant::query()->create(['thread_id' => $private->id, 'user_id' => $participant->id, 'role' => $participant->is($student) ? 'student' : 'lecturer']);
    }
    unreadMessage($private, $sender);

    unreadRead($this, $reader, $role.'.pesan.read', $thread, $seen->id)
        ->assertOk()
        ->assertJsonPath('threadUnreadCount', 1)
        ->assertJsonPath('unreadMessageCount', 2);

    unreadRead($this, $reader, $role.'.pesan.read', $thread)
        ->assertOk()
        ->assertJsonPath('threadUnreadCount', 0)
        ->assertJsonPath('unreadMessageCount', 1);
})->with(['mahasiswa', 'dosen']);

it('reads every correlated chat notice up to the displayed watermark without consuming other updates', function (string $role): void {
    $this->travelTo(now()->startOfSecond());
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = unreadBimbingan();
    $reader = $role === 'mahasiswa' ? $student : $lecturer;
    $sender = $role === 'mahasiswa' ? $lecturer : $student;
    $seen = unreadMessage($thread, $sender);
    $later = unreadMessage($thread, $sender);
    $notice = function (User $owner, array $overrides = []) use ($role, $thread, $seen) {
        return $owner->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => RealtimeNotification::class,
            'data' => array_replace([
                'title' => 'Pesan baru',
                'preferenceKey' => 'pesanBaru',
                'url' => '/'.$role.'/pesan?thread='.$thread->id,
                'chatThreadId' => $thread->id,
                'chatMessageId' => $seen->id,
            ], $overrides),
        ]);
    };
    $expected = [];
    for ($index = 0; $index < 18; $index++) {
        $expected[] = $notice($reader)->id;
    }
    $newer = $notice($reader, ['chatMessageId' => $later->id]);
    $otherThread = $notice($reader, ['chatThreadId' => $thread->id + 1000]);
    $academic = $notice($reader, ['preferenceKey' => 'statusTugasAkhir']);
    $otherAccount = $notice($sender);

    $response = unreadRead($this, $reader, $role.'.pesan.read', $thread, $seen->id)
        ->assertOk()->assertJsonPath('threadUnreadCount', 1);
    expect($response->json('readNotificationIds'))->toEqualCanonicalizing($expected);
    expect($reader->unreadNotifications()->pluck('id')->all())
        ->toEqualCanonicalizing([$newer->id, $otherThread->id, $academic->id]);
    expect($otherAccount->fresh()->read_at)->toBeNull();

    unreadRead($this, $reader, $role.'.pesan.read', $thread, $later->id)
        ->assertOk()->assertJsonPath('readNotificationIds', [$newer->id]);
    unreadRead($this, $reader, $role.'.pesan.read', $thread, $seen->id)
        ->assertOk()->assertJsonPath('readNotificationIds', []);
    expect(MentorshipChatRead::query()->where('user_id', $reader->id)->where('mentorship_chat_thread_id', $thread->id)->value('last_read_message_id'))
        ->toBe($later->id);
})->with(['mahasiswa', 'dosen']);

it('reads targeted legacy notices only when their ambiguous same-second message window is fully visible', function (): void {
    $this->travelTo(now()->startOfSecond());
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = unreadBimbingan();
    $seen = unreadMessage($thread, $lecturer);
    $later = unreadMessage($thread, $lecturer);
    $notices = collect(['/mahasiswa/pesan?thread='.$thread->id, '/mahasiswa/pesan'])->map(fn (string $url) => $student->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => RealtimeNotification::class,
        'data' => ['preferenceKey' => 'pesanBaru', 'url' => $url],
    ]));
    unreadRead($this, $student, 'mahasiswa.pesan.read', $thread, $seen->id)
        ->assertOk()->assertJsonPath('readNotificationIds', []);
    unreadRead($this, $student, 'mahasiswa.pesan.read', $thread, $later->id)
        ->assertOk()->assertJsonPath('readNotificationIds', [$notices[0]->id]);
    expect($notices[1]->fresh()->read_at)->toBeNull();
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
    expect(unreadCount($this, $student))->toBe(1);

    unreadRead($this, $student, 'mahasiswa.pesan.read', $private)->assertOk();
    expect(unreadCount($this, $student))->toBe(0);
});

it('ignores own messages and document_event system messages', function (): void {
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = unreadBimbingan();

    unreadMessage($thread, $student);
    unreadMessage($thread, $lecturer, 'document_event');

    // The student's own message and the system event are not unread for the student;
    // the student's message is an ordinary incoming message for the lecturer.
    expect(unreadCount($this, $student))->toBe(0)
        ->and(unreadCount($this, $lecturer))->toBe(1);

    unreadRead($this, $lecturer, 'dosen.pesan.read', $thread)->assertOk();
    unreadMessage($thread, $student, 'document_event', now()->addMinute());

    expect(unreadCount($this, $lecturer))->toBe(0);
});

it('keeps unread state isolated per account, role and forbidden thread', function (): void {
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = unreadBimbingan();
    $stranger = User::factory()->asMahasiswa()->create();
    $strangerLecturer = User::factory()->asDosen()->create();
    $admin = User::factory()->asAdmin()->create();

    unreadMessage($thread, $lecturer);

    expect(unreadCount($this, $stranger))->toBe(0)
        ->and(unreadCount($this, $strangerLecturer))->toBe(0)
        ->and(unreadCount($this, $admin))->toBe(0);

    unreadRead($this, $stranger, 'mahasiswa.pesan.read', $thread)->assertForbidden();
    expect(MentorshipChatRead::query()->count())->toBe(0)
        ->and(unreadCount($this, $student))->toBe(1);

    // A dual-role account only sees the unread state of the active role.
    $dual = User::factory()->asMahasiswa()->create();
    $dual->roles()->syncWithoutDetaching([Role::query()->firstOrCreate(['name' => 'dosen'])->id]);
    $thread2 = MentorshipChatThread::factory()->create(['student_user_id' => $dual->id, 'type' => 'pembimbing']);
    unreadMessage($thread2, $lecturer);

    expect(unreadCount($this, $dual, 'mahasiswa'))->toBe(1)
        ->and(unreadCount($this, $dual, 'dosen'))->toBe(0);
});

it('does not create a pembimbing thread when computing the indicator', function (): void {
    $student = User::factory()->asMahasiswa()->create();

    expect(unreadCount($this, $student))->toBe(0);
    $this->actingAs($student)->get(route('dashboard'));

    expect(MentorshipChatThread::query()->count())->toBe(0);
});

it('counts messages of an archived (ended) supervision for the lecturer', function (): void {
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = unreadBimbingan('ended');
    unreadMessage($thread, $student);

    expect(unreadCount($this, $lecturer))->toBe(1);
});

it('exposes the count as a lazy shared inertia prop', function (): void {
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = unreadBimbingan();
    unreadMessage($thread, $lecturer);
    unreadMessage($thread, $lecturer);

    $this->actingAs($student)
        ->get(route('mahasiswa.pesan'))
        ->assertInertia(fn (Assert $page) => $page->where('unreadMessageCount', 2));
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
        ->and(unreadCount($this, $student))->toBe(1);

    unreadRead($this, $student, 'mahasiswa.pesan.read', $thread)->assertOk();
    expect(unreadCount($this, $student))->toBe(0);
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

it('shows the newest lecturer messages chronologically when their timestamps match', function (): void {
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = unreadBimbingan();
    $at = now()->startOfSecond();
    $messages = collect(range(1, 35))
        ->map(fn (): MentorshipChatMessage => unreadMessage($thread, $student, 'text', $at));

    $this->actingAs($lecturer)
        ->get(route('dosen.pesan'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('threads.0.messages.0.id', $messages[5]->id)
            ->where('threads.0.messages.29.id', $messages[34]->id));

    unreadRead($this, $lecturer, 'dosen.pesan.read', $thread, $messages[34]->id)->assertOk();
    expect(unreadCount($this, $lecturer))->toBe(0);
});

it('requires a valid last_message_id from the same thread when saving a receipt', function (): void {
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = unreadBimbingan();
    $foreign = unreadMessage(MentorshipChatThread::factory()->create(['type' => 'pembimbing']), $lecturer);
    unreadMessage($thread, $lecturer);

    $this->actingAs($student)->postJson(route('mahasiswa.pesan.read', $thread))->assertUnprocessable();
    unreadRead($this, $student, 'mahasiswa.pesan.read', $thread, $foreign->id)->assertUnprocessable();
    $this->actingAs($lecturer)->postJson(route('dosen.pesan.read', $thread), ['last_message_id' => $foreign->id])->assertUnprocessable();

    expect(MentorshipChatRead::query()->count())->toBe(0)
        ->and(unreadCount($this, $student))->toBe(1);
});

it('only reads up to the last seen message and never moves the receipt backwards', function (): void {
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = unreadBimbingan();
    $first = unreadMessage($thread, $lecturer, 'text', now()->subMinutes(3));
    $second = unreadMessage($thread, $lecturer, 'text', now()->subMinutes(2));

    unreadRead($this, $student, 'mahasiswa.pesan.read', $thread, $first->id)->assertOk();
    expect(unreadCount($this, $student))->toBe(1);

    unreadRead($this, $student, 'mahasiswa.pesan.read', $thread, $second->id)->assertOk();
    expect(unreadCount($this, $student))->toBe(0);

    // A stale tab that only saw the first message must not make the second unread again.
    unreadRead($this, $student, 'mahasiswa.pesan.read', $thread, $first->id)->assertOk();
    expect(unreadCount($this, $student))->toBe(0);
});

it('keeps a message that arrives in the same second as the receipt unread', function (): void {
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = unreadBimbingan();
    $at = now()->startOfSecond();

    $seen = unreadMessage($thread, $lecturer, 'text', $at);
    unreadRead($this, $student, 'mahasiswa.pesan.read', $thread, $seen->id)->assertOk();
    expect(unreadCount($this, $student))->toBe(0);

    unreadMessage($thread, $lecturer, 'text', $at);

    expect(unreadCount($this, $student))->toBe(1);
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
        ->and(unreadCount($this, $student))->toBe(0);
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

    expect(unreadCount($this, $student))->toBe(0);

    unreadMessage($thread, $lecturer, 'text', now());
    expect(unreadCount($this, $student))->toBe(1);

    // The first new-style receipt upgrades the legacy row to an id watermark.
    unreadRead($this, $student, 'mahasiswa.pesan.read', $thread)->assertOk();
    expect(unreadCount($this, $student))->toBe(0)
        ->and(MentorshipChatRead::query()->firstOrFail()->last_read_message_id)->not->toBeNull();
});

it('counts exact incoming messages across threads, not threads', function (): void {
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $bimbingan] = unreadBimbingan();
    $other = User::factory()->asDosen()->create();
    $stranger = User::factory()->asMahasiswa()->create();

    $private = MentorshipChatThread::factory()->create(['student_user_id' => $student->id, 'type' => 'private', 'private_key' => $student->id.':'.$other->id]);
    foreach ([$student, $other] as $participant) {
        MentorshipChatThreadParticipant::query()->create(['thread_id' => $private->id, 'user_id' => $participant->id, 'role' => $participant->is($student) ? 'student' : 'lecturer']);
    }
    $foreign = MentorshipChatThread::factory()->create(['student_user_id' => $stranger->id, 'type' => 'pembimbing']);

    $first = unreadMessage($bimbingan, $lecturer, 'text', now()->subMinutes(5));
    unreadMessage($bimbingan, $lecturer, 'text', now()->subMinutes(4));
    unreadMessage($bimbingan, $lecturer, 'text', now()->subMinutes(3));
    $privateFirst = unreadMessage($private, $other, 'text', now()->subMinutes(2));
    unreadMessage($private, $other, 'text', now()->subMinute());

    // Own, system, sender-less and foreign-thread messages never count.
    unreadMessage($bimbingan, $student);
    unreadMessage($bimbingan, $lecturer, 'document_event');
    unreadMessage($private, null);
    unreadMessage($foreign, $lecturer);
    unreadMessage($foreign, $lecturer);

    expect(unreadCount($this, $student))->toBe(5)
        ->and(unreadCount($this, $stranger))->toBe(2)
        ->and(unreadCount($this, $lecturer))->toBe(1);

    // A partial read only removes the messages it covered and leaves the other thread alone.
    unreadRead($this, $student, 'mahasiswa.pesan.read', $bimbingan, $first->id)->assertOk();
    expect(unreadCount($this, $student))->toBe(4);

    unreadRead($this, $student, 'mahasiswa.pesan.read', $private, $privateFirst->id)->assertOk();
    expect(unreadCount($this, $student))->toBe(3);

    unreadRead($this, $student, 'mahasiswa.pesan.read', $bimbingan)->assertOk();
    expect(unreadCount($this, $student))->toBe(1);

    unreadRead($this, $student, 'mahasiswa.pesan.read', $private)->assertOk();
    expect(unreadCount($this, $student))->toBe(0);
});

it('keeps the backend count exact above 99', function (): void {
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = unreadBimbingan();

    MentorshipChatMessage::factory()->count(120)->create([
        'mentorship_chat_thread_id' => $thread->id,
        'sender_user_id' => $lecturer->id,
        'message_type' => 'text',
        'message' => 'Halo',
    ]);

    expect(unreadCount($this, $student))->toBe(120);
});
