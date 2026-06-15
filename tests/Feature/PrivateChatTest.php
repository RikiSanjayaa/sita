<?php

use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipChatThreadParticipant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('allows mahasiswa to start a private chat with another mahasiswa', function (): void {
    $student = User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa A']);
    $recipient = User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa B']);

    $response = $this->actingAs($student)
        ->post(route('mahasiswa.pesan.private.store'), [
            'recipient_id' => $recipient->id,
        ]);

    $thread = MentorshipChatThread::query()
        ->where('type', 'private')
        ->where('private_key', "{$student->id}:{$recipient->id}")
        ->firstOrFail();

    $response->assertRedirect(route('mahasiswa.pesan', ['thread' => $thread->id]));

    expect($thread->student_user_id)->toBe($student->id)
        ->and(MentorshipChatThreadParticipant::query()->where('thread_id', $thread->id)->count())->toBe(2);

    $this->actingAs($recipient)
        ->get(route('mahasiswa.pesan'))
        ->assertInertia(
            fn(Assert $page) => $page
                ->component('pesan')
                ->has('threads', 2)
                ->where('threads.1.threadType', 'private')
                ->where('threads.1.threadLabel', 'Mahasiswa')
                ->where('threads.1.name', 'Mahasiswa A')
        );
});

it('reuses the same private thread for the same pair', function (): void {
    $student = User::factory()->asMahasiswa()->create();
    $lecturer = User::factory()->asDosen()->create();

    $this->actingAs($student)
        ->post(route('mahasiswa.pesan.private.store'), ['recipient_id' => $lecturer->id])
        ->assertRedirect();

    $this->actingAs($lecturer)
        ->post(route('dosen.pesan.private.store'), ['recipient_id' => $student->id])
        ->assertRedirect();

    expect(MentorshipChatThread::query()->where('type', 'private')->count())->toBe(1);
});

it('allows dosen to use private chat with another dosen', function (): void {
    $lecturer = User::factory()->asDosen()->create(['name' => 'Dosen A']);
    $recipient = User::factory()->asDosen()->create(['name' => 'Dosen B']);

    $this->actingAs($lecturer)
        ->post(route('dosen.pesan.private.store'), [
            'recipient_id' => $recipient->id,
        ])
        ->assertRedirect();

    $thread = MentorshipChatThread::query()
        ->where('type', 'private')
        ->where('private_key', "{$lecturer->id}:{$recipient->id}")
        ->firstOrFail();

    expect($thread->student_user_id)->toBeNull();

    $this->actingAs($recipient)
        ->post(route('dosen.pesan.messages.store', $thread), [
            'message' => 'Halo sesama dosen.',
        ])
        ->assertRedirect();

    expect(MentorshipChatMessage::query()
        ->where('mentorship_chat_thread_id', $thread->id)
        ->where('message', 'Halo sesama dosen.')
        ->exists())->toBeTrue();
});

it('prevents non participants from posting to a private chat', function (): void {
    $lecturer = User::factory()->asDosen()->create();
    $recipient = User::factory()->asDosen()->create();
    $outsider = User::factory()->asDosen()->create();

    $this->actingAs($lecturer)
        ->post(route('dosen.pesan.private.store'), [
            'recipient_id' => $recipient->id,
        ]);

    $thread = MentorshipChatThread::query()->where('type', 'private')->firstOrFail();

    $this->actingAs($outsider)
        ->post(route('dosen.pesan.messages.store', $thread), [
            'message' => 'Saya seharusnya tidak bisa masuk.',
        ])
        ->assertForbidden();
});
