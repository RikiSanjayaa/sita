<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorshipChatMessage extends Model
{
    /** @use HasFactory<\Database\Factories\MentorshipChatMessageFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'mentorship_chat_thread_id',
        'sender_user_id',
        'related_document_id',
        'message_type',
        'message',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MentorshipChatThread::class, 'mentorship_chat_thread_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function relatedDocument(): BelongsTo
    {
        return $this->belongsTo(MentorshipDocument::class, 'related_document_id');
    }
}
