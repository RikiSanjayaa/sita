<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_read_at' => 'datetime',
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
