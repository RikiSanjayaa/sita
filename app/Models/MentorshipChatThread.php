<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MentorshipChatThread extends Model
{
    /** @use HasFactory<\Database\Factories\MentorshipChatThreadFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'student_user_id',
        'is_escalated',
        'escalated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_escalated' => 'boolean',
            'escalated_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MentorshipChatMessage::class, 'mentorship_chat_thread_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(MentorshipChatMessage::class, 'mentorship_chat_thread_id')->latestOfMany();
    }
}
