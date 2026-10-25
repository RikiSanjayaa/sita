<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Identity-free invalidation: tells one user's sessions to re-fetch GET /chat/unread.
 * Carries no thread, message or person data on purpose.
 */
class ChatUnreadUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly int $userId,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(sprintf('chat.unread.%d', $this->userId));
    }

    /**
     * @return array<string, never>
     */
    public function broadcastWith(): array
    {
        return [];
    }

    public function broadcastAs(): string
    {
        return 'chat.unread.updated';
    }
}
