<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorshipChatThreadParticipant extends Model
{
    protected $table = 'mentorship_chat_thread_participants';

    protected $fillable = [
        'thread_id',
        'user_id',
        'role',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MentorshipChatThread::class, 'thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
