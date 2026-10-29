<?php

use App\Events\ChatMessageCreated;
use App\Events\ChatMessageUpdated;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatRead;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipChatThreadParticipant;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\ChatMessagePayloadBuilder;
use App\Services\PrivateChatService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function replyEditFixture(string $portal): array
{
    $owner = $portal === 'mahasiswa' ? User::factory()->asMahasiswa()->create() : User::factory()->asDosen()->create();
    $peer = User::factory()->asDosen()->create();
    $thread = app(PrivateChatService::class)->findOrCreateThread($owner, $peer);
    $source = $thread->messages()->create(['sender_user_id' => $owner->id, 'message_type' => 'text', 'message' => 'Teks asal', 'sent_at' => now()->subYears(2), 'created_at' => now()->subYears(2)]);

    return [$owner, $peer, $thread, $source];
}

it('keeps live reply context and original order receipts and notification state after an old text edit', function (string $portal): void {
    Event::fake([ChatMessageCreated::class, ChatMessageUpdated::class]);
    [$owner, $peer, $thread, $source] = replyEditFixture($portal);
    $receipt = MentorshipChatRead::query()->create(['mentorship_chat_thread_id' => $thread->id, 'user_id' => $peer->id, 'last_read_message_id' => $source->id, 'last_read_at' => $source->created_at]);
    $source->forceFill(['created_at' => now()->subYears(2)])->saveQuietly();
    $receiptBefore = $receipt->only(['last_read_message_id', 'last_read_at']);
    $original = $source->getAttributes();
    $uuid = (string) Str::uuid();
    $replyId = $this->actingAs($owner)->postJson(route("{$portal}.pesan.messages.store", $thread), ['message' => 'Jawaban', 'reply_to_id' => $source->id, 'client_message_id' => $uuid])
        ->assertCreated()->assertJsonPath('clientMessageId', $uuid)
        ->assertJsonPath('messages.0.reply.message', 'Teks asal')->json('messages.0.id');
    $notifications = $peer->notifications()->count();
    Event::fake([ChatMessageCreated::class, ChatMessageUpdated::class]);

    $this->actingAs($owner)->patchJson(route("{$portal}.pesan.messages.update", [$thread, $source]), ['message' => 'Teks diperbaiki'])
        ->assertOk()->assertJsonPath('message.id', $source->id)->assertJsonPath('message.message', 'Teks diperbaiki');
    $source->refresh();
    expect($source->created_at->toDateTimeString())->toBe($original['created_at'])
        ->and($source->sent_at->toDateTimeString())->toBe($original['sent_at'])
        ->and($source->edited_at)->not->toBeNull()
        ->and($thread->messages()->orderBy('id')->pluck('id')->all())->toBe([$source->id, $replyId])
        ->and($receipt->fresh()->only(['last_read_message_id', 'last_read_at']))->toEqual($receiptBefore)
        ->and($peer->notifications()->count())->toBe($notifications);
    Event::assertNotDispatched(ChatMessageCreated::class);
    Event::assertDispatchedTimes(ChatMessageUpdated::class, 1);
    Event::assertDispatched(ChatMessageUpdated::class, fn ($event): bool => $event->broadcastAs() === 'chat.message.updated' && $event->messagePayload['id'] === $source->id && $event->messagePayload['message'] === 'Teks diperbaiki' && (string) $event->broadcastOn() === 'private-mentorship.thread.'.$thread->id);
    $reply = MentorshipChatMessage::query()->findOrFail($replyId);
    expect(app(ChatMessagePayloadBuilder::class)->build($reply)['reply']['message'])->toBe('Teks diperbaiki');
    $this->actingAs($owner)->get(route("{$portal}.pesan.messages.show", [$thread, $reply]))->assertOk()->assertJsonPath('message.reply.message', 'Teks diperbaiki');
})->with(['mahasiswa', 'dosen']);

it('rejects cross-thread replies foreign edits inaccessible context system edits and text erasure', function (string $portal): void {
    [$owner, $peer, $thread, $source] = replyEditFixture($portal);
    $outsider = User::factory()->asDosen()->create();
    $other = app(PrivateChatService::class)->findOrCreateThread($owner, $outsider);
    $foreign = $other->messages()->create(['sender_user_id' => $owner->id, 'message_type' => 'text', 'message' => 'Thread lain']);
    $system = $thread->messages()->create(['sender_user_id' => $owner->id, 'message_type' => 'document_event', 'message' => 'Tidak boleh diedit']);
    $this->actingAs($owner)->postJson(route("{$portal}.pesan.messages.store", $thread), ['message' => 'Jawab', 'reply_to_id' => $foreign->id, 'client_message_id' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('reply_to_id');
    $this->actingAs($owner)->patchJson(route("{$portal}.pesan.messages.update", [$thread, $foreign]), ['message' => 'Bocor'])->assertNotFound();
    $this->actingAs($peer)->patchJson(route('dosen.pesan.messages.update', [$thread, $source]), ['message' => 'Bajak'])->assertForbidden();
    $this->actingAs($outsider)->get(route('dosen.pesan.messages.show', [$thread, $source]))->assertForbidden();
    $this->actingAs($owner)->get(route("{$portal}.pesan.messages.show", [$thread, $foreign]))->assertNotFound();
    $this->actingAs($owner)->patchJson(route("{$portal}.pesan.messages.update", [$thread, $system]), ['message' => 'Ubah sistem'])->assertForbidden();
    $this->actingAs($owner)->patchJson(route("{$portal}.pesan.messages.update", [$thread, $source]), ['message' => '   '])->assertUnprocessable()->assertJsonValidationErrors('message');
    expect($source->fresh()->message)->toBe('Teks asal')->and($thread->messages()->count())->toBe(2);
})->with(['mahasiswa', 'dosen']);

it('edits captions without replacing file bytes or attachment metadata and permits an empty attached caption', function (string $portal): void {
    Storage::fake('public');
    [$owner, , $thread] = replyEditFixture($portal);
    $sourceBytes = file_get_contents(base_path('tests/fixtures/chat-image.png'));
    $response = $this->actingAs($owner)->postJson(route("{$portal}.pesan.messages.store", $thread), ['message' => 'Caption awal', 'client_message_id' => (string) Str::uuid(), 'attachment' => new UploadedFile(base_path('tests/fixtures/chat-image.png'), 'bukti.png', 'image/png', test: true)])->assertCreated();
    $attachment = MentorshipChatMessage::query()->findOrFail($response->json('messages.0.id'));
    $fileAttributes = $attachment->only(['attachment_path', 'attachment_disk', 'attachment_name', 'attachment_mime', 'attachment_size_kb', 'related_document_id', 'message_type']);
    $this->actingAs($owner)->patchJson(route("{$portal}.pesan.messages.update", [$thread, $attachment]), ['message' => 'Caption baru'])->assertOk();
    $this->actingAs($owner)->patchJson(route("{$portal}.pesan.messages.update", [$thread, $attachment]), ['message' => '', 'attachment' => UploadedFile::fake()->create('ganti.pdf', 1, 'application/pdf')])->assertUnprocessable()->assertJsonValidationErrors('attachment');
    $this->actingAs($owner)->patchJson(route("{$portal}.pesan.messages.update", [$thread, $attachment]), ['message' => ''])->assertOk();
    expect($attachment->fresh()->only(array_keys($fileAttributes)))->toBe($fileAttributes)
        ->and(hash('sha256', Storage::disk('public')->get($attachment->attachment_path)))->toBe(hash('sha256', $sourceBytes));
})->with(['mahasiswa', 'dosen']);

it('rejects replies and edits in completed or archived group conversations', function (string $state): void {
    $student = User::factory()->asMahasiswa()->create();
    ThesisProject::factory()->create(['student_user_id' => $student->id, 'state' => $state, 'phase' => $state === 'completed' ? 'completed' : 'research']);
    $thread = MentorshipChatThread::factory()->create(['student_user_id' => $student->id, 'type' => 'pembimbing']);
    $source = $thread->messages()->create(['sender_user_id' => $student->id, 'message_type' => 'text', 'message' => 'Arsip']);
    $this->actingAs($student)->patchJson(route('mahasiswa.pesan.messages.update', [$thread, $source]), ['message' => 'Ubah'])->assertUnprocessable();
    $this->actingAs($student)->postJson(route('mahasiswa.pesan.messages.store', $thread), ['message' => 'Jawab', 'reply_to_id' => $source->id, 'client_message_id' => (string) Str::uuid()])->assertUnprocessable();
    expect($source->fresh()->message)->toBe('Arsip')->and($thread->messages()->count())->toBe(1);
})->with(['completed']);

it('exposes only an unavailable tombstone for missing cross-thread and secret Sidang quote sources', function (): void {
    $student = User::factory()->asMahasiswa()->create();
    $examiner = User::factory()->asDosen()->create(['name' => 'Rahasia quote']);
    $project = ThesisProject::factory()->create(['student_user_id' => $student->id]);
    $defense = ThesisDefense::query()->create(['project_id' => $project->id, 'type' => 'sidang', 'attempt_no' => 1, 'status' => 'scheduled']);
    ThesisDefenseExaminer::query()->create(['defense_id' => $defense->id, 'lecturer_user_id' => $examiner->id, 'role' => 'examiner', 'order_no' => 1]);
    $thread = MentorshipChatThread::factory()->create(['student_user_id' => $student->id, 'type' => 'sidang', 'context_id' => $defense->id]);
    MentorshipChatThreadParticipant::query()->create(['thread_id' => $thread->id, 'user_id' => $student->id, 'role' => 'student']);
    $source = $thread->messages()->create(['sender_user_id' => $examiner->id, 'message_type' => 'text', 'message' => 'Isi rahasia', 'attachment_name' => 'rahasia.pdf']);
    $reply = $thread->messages()->create(['sender_user_id' => $student->id, 'message_type' => 'text', 'message' => 'Konteks', 'reply_to_id' => $source->id]);
    expect(app(ChatMessagePayloadBuilder::class)->build($reply)['reply'])->toBe(['available' => false]);
    $this->actingAs($student)->get(route('mahasiswa.pesan.messages.show', [$thread, $source]))->assertNotFound();
    $this->actingAs($student)->postJson(route('mahasiswa.pesan.messages.store', $thread), ['message' => 'Jawab', 'reply_to_id' => $source->id, 'client_message_id' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('reply_to_id');
    MentorshipChatThreadParticipant::query()->create(['thread_id' => $thread->id, 'user_id' => $examiner->id, 'role' => 'examiner']);
    Event::fake([ChatMessageUpdated::class]);
    $this->actingAs($examiner)->patchJson(route('dosen.pesan.messages.update', [$thread, $source]), ['message' => 'Perubahan rahasia'])
        ->assertOk()->assertJsonPath('message.quoteAvailable', false);
    Event::assertDispatched(ChatMessageUpdated::class, fn ($event): bool => $event->messagePayload['quoteAvailable'] === false);
    $source->delete();
    expect(app(ChatMessagePayloadBuilder::class)->build($reply->fresh())['reply'])->toBe(['available' => false]);
    $other = MentorshipChatMessage::factory()->create();
    $reply->update(['reply_to_id' => $other->id]);
    expect(app(ChatMessagePayloadBuilder::class)->build($reply->fresh())['reply'])->toBe(['available' => false]);
});

it('stores document bytes unchanged and keeps ZIP outside the server allowlist', function (string $format): void {
    Storage::fake('public');
    [$owner, , $thread] = replyEditFixture('mahasiswa');
    $bytes = file_get_contents(base_path('tests/fixtures/panduan-template.pdf'));
    if ($format !== 'pdf') {
        $path = tempnam(sys_get_temp_dir(), 'chat-docx-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>SiTA fixture</w:t></w:r></w:p></w:body></w:document>');
        if ($format === 'zip') {
            $zip->deleteName('[Content_Types].xml');
            $zip->deleteName('word/document.xml');
            $zip->addFromString('demo.txt', 'SiTA ZIP fixture');
        }
        $zip->close();
        $bytes = file_get_contents($path);
        unlink($path);
    }
    $response = $this->actingAs($owner)->postJson(route('mahasiswa.pesan.messages.store', $thread), [
        'client_message_id' => (string) Str::uuid(),
        'attachment' => UploadedFile::fake()->createWithContent('document.'.$format, $bytes),
    ]);
    if ($format === 'zip') {
        $response->assertUnprocessable()->assertJsonValidationErrors('attachment');
        expect(Storage::disk('public')->allFiles())->toBe([]);

        return;
    }
    $id = $response->assertCreated()->json('messages.0.id');
    $message = MentorshipChatMessage::query()->findOrFail($id);
    expect(hash('sha256', Storage::disk('public')->get($message->attachment_path)))->toBe(hash('sha256', $bytes));
})->with(['pdf', 'docx', 'zip']);

it('authorizes active group replies but rejects an ended supervisor edit and reply', function (): void {
    Event::fake([ChatMessageCreated::class, ChatMessageUpdated::class]);
    $student = User::factory()->asMahasiswa()->create();
    $lecturer = User::factory()->asDosen()->create();
    $project = ThesisProject::factory()->create(['student_user_id' => $student->id]);
    $assignment = ThesisSupervisorAssignment::query()->create(['project_id' => $project->id, 'lecturer_user_id' => $lecturer->id, 'role' => 'primary', 'status' => 'active', 'started_at' => now()]);
    $thread = MentorshipChatThread::factory()->create(['student_user_id' => $student->id, 'type' => 'pembimbing']);
    $source = $thread->messages()->create(['sender_user_id' => $lecturer->id, 'message_type' => 'text', 'message' => 'Arahan grup']);
    $this->actingAs($student)->postJson(route('mahasiswa.pesan.messages.store', $thread), ['client_message_id' => (string) Str::uuid(), 'reply_to_id' => $source->id, 'message' => 'Jawaban grup'])->assertCreated()->assertJsonPath('messages.0.reply.message', 'Arahan grup');
    $this->actingAs($lecturer)->patchJson(route('dosen.pesan.messages.update', [$thread, $source]), ['message' => 'Arahan diperbarui'])->assertOk();
    $assignment->update(['status' => 'ended', 'ended_at' => now()]);
    $this->actingAs($lecturer)->patchJson(route('dosen.pesan.messages.update', [$thread, $source]), ['message' => 'Ubah arsip'])->assertUnprocessable();
    $this->actingAs($lecturer)->postJson(route('dosen.pesan.messages.store', $thread), ['client_message_id' => (string) Str::uuid(), 'reply_to_id' => $source->id, 'message' => 'Jawab arsip'])->assertUnprocessable();
    expect($source->fresh()->message)->toBe('Arahan diperbarui')->and($thread->messages()->count())->toBe(2);
});

it('keeps the original WITA day and clock through send edit and reload across UTC midnight', function (string $portal): void {
    Event::fake([ChatMessageCreated::class, ChatMessageUpdated::class]);
    $timezone = date_default_timezone_get();
    date_default_timezone_set('UTC');

    try {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-28T16:05:00Z'));
        [$owner, , $thread] = replyEditFixture($portal);
        $response = $this->actingAs($owner)->postJson(route("{$portal}.pesan.messages.store", $thread), [
            'message' => 'Pesan setelah tengah malam WITA',
            'client_message_id' => (string) Str::uuid(),
        ])->assertCreated()
            ->assertJsonPath('messages.0.time', '00.05')
            ->assertJsonPath('messages.0.sentAt', '2026-10-28T16:05:00+00:00');
        $id = $response->json('messages.0.id');
        Event::assertDispatched(ChatMessageCreated::class, fn ($event): bool => $event->messagePayload['id'] === $id
            && $event->messagePayload['time'] === '00.05'
            && $event->messagePayload['sentAt'] === '2026-10-28T16:05:00+00:00');

        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-30T10:00:00Z'));
        $this->actingAs($owner)->patchJson(route("{$portal}.pesan.messages.update", [$thread, $id]), ['message' => 'Diperbaiki dua hari kemudian'])
            ->assertOk()
            ->assertJsonPath('message.time', '00.05')
            ->assertJsonPath('message.sentAt', '2026-10-28T16:05:00+00:00');
        $this->actingAs($owner)->getJson(route("{$portal}.pesan.messages.show", [$thread, $id]))
            ->assertOk()
            ->assertJsonPath('message.time', '00.05')
            ->assertJsonPath('message.sentAt', '2026-10-28T16:05:00+00:00');
        Event::assertDispatched(ChatMessageUpdated::class, fn ($event): bool => $event->messagePayload['id'] === $id
            && $event->messagePayload['time'] === '00.05'
            && $event->messagePayload['sentAt'] === '2026-10-28T16:05:00+00:00');
    } finally {
        $this->travelBack();
        date_default_timezone_set($timezone);
    }
})->with(['mahasiswa', 'dosen']);
