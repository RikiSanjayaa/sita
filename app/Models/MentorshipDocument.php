<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MentorshipDocument extends Model
{
    /** @use HasFactory<\Database\Factories\MentorshipDocumentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'student_user_id',
        'lecturer_user_id',
        'mentorship_assignment_id',
        'title',
        'file_name',
        'file_url',
        'file_size_kb',
        'status',
        'revision_notes',
        'reviewed_at',
        'uploaded_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_user_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(MentorshipAssignment::class, 'mentorship_assignment_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(MentorshipChatMessage::class, 'related_document_id');
    }
}
