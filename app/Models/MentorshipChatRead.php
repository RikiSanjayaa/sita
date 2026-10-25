<?php

namespace App\Models;

use App\Observers\ChatUnreadObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(ChatUnreadObserver::class)]
class MentorshipChatRead extends Model
{
    /** @use HasFactory<\Database\Factories\MentorshipChatReadFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'mentorship_chat_thread_id',
        'user_id',
        'last_read_at',
        'last_read_message_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_read_at' => 'datetime',
            'last_read_message_id' => 'integer',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MentorshipChatThread::class, 'mentorship_chat_thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
