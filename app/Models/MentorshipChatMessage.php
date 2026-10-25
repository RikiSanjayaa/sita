<?php

namespace App\Models;

use App\Observers\ChatUnreadObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(ChatUnreadObserver::class)]
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
        'attachment_disk',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size_kb',
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
            'attachment_size_kb' => 'integer',
        ];
    }

    /**
     * Messages that are unread for the user: from someone else, not a document_event system message,
     * and after the user's own receipt. Receipts with a message-id watermark compare ids (exact even
     * within the same second); legacy receipts without one fall back to last_read_at.
     *
     * @param  Builder<MentorshipChatMessage>  $query
     * @return Builder<MentorshipChatMessage>
     */
    public function scopeUnreadFor(Builder $query, int $userId): Builder
    {
        return $query
            ->leftJoin('mentorship_chat_reads as own_read', function ($join) use ($userId): void {
                $join->on('own_read.mentorship_chat_thread_id', '=', 'mentorship_chat_messages.mentorship_chat_thread_id')
                    ->where('own_read.user_id', '=', $userId);
            })
            ->where('mentorship_chat_messages.sender_user_id', '!=', $userId)
            ->where('mentorship_chat_messages.message_type', '!=', 'document_event')
            ->where(function (Builder $unread): void {
                $unread->whereColumn('mentorship_chat_messages.id', '>', 'own_read.last_read_message_id')
                    ->orWhere(function (Builder $legacy): void {
                        $legacy->whereNull('own_read.last_read_message_id')
                            ->where(function (Builder $time): void {
                                $time->whereNull('own_read.last_read_at')
                                    ->orWhereColumn('mentorship_chat_messages.created_at', '>', 'own_read.last_read_at');
                            });
                    });
            });
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
