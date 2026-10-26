<?php

use App\Events\ChatMessageCreated;
use App\Models\User;
use App\Services\PrivateChatService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

it('acknowledges identical sends independently without a page redirect', function (string $portal): void {
    Event::fake([ChatMessageCreated::class]);
    $sender = $portal === 'mahasiswa' ? User::factory()->asMahasiswa()->create() : User::factory()->asDosen()->create();
    $recipient = User::factory()->asDosen()->create();
    $thread = app(PrivateChatService::class)->findOrCreateThread($sender, $recipient);
    $ids = [];

    foreach (range(1, 2) as $attempt) {
        $correlation = (string) Str::uuid();
        $response = $this->actingAs($sender)->postJson(route("{$portal}.pesan.messages.store", $thread), [
            'message' => 'Pesan identik',
            'client_message_id' => $correlation,
        ])->assertCreated()
            ->assertHeaderMissing('Location')
            ->assertJsonPath('threadId', $thread->id)
            ->assertJsonPath('clientMessageId', $correlation)
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.message', 'Pesan identik')
            ->assertJsonMissingPath('props');
        $id = $response->json('messages.0.id');
        expect($thread->messages()->findOrFail($id)->message)->toBe('Pesan identik');
        Event::assertDispatched(ChatMessageCreated::class, fn (ChatMessageCreated $event): bool => $event->messagePayload['id'] === $id && $event->messagePayload['clientMessageId'] === $correlation);
        $ids[] = $id;
    }

    expect($ids[0])->not->toBe($ids[1]);
})->with(['mahasiswa', 'dosen']);
