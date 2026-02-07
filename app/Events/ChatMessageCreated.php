<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $messagePayload
     */
    public function __construct(
        public readonly int $threadId,
        public readonly array $messagePayload,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(sprintf('mentorship.thread.%d', $this->threadId));
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'threadId' => $this->threadId,
            'message' => $this->messagePayload,
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.message.created';
    }
}
