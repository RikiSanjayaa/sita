<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThesisRevisionRound extends Model
{
    /** @use HasFactory<\Database\Factories\ThesisRevisionRoundFactory> */
    use HasFactory;

    public const STATUS_AWAITING_STUDENT = 'awaiting_student';

    public const STATUS_AWAITING_REVIEW = 'awaiting_review';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'revision_id',
        'round_no',
        'opened_at',
        'student_submitted_at',
        'student_document_id',
        'reviewed_at',
        'item_snapshot',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'round_no' => 'integer',
            'opened_at' => 'datetime',
            'student_submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'item_snapshot' => 'array',
        ];
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(ThesisRevision::class, 'revision_id');
    }

    public function studentDocument(): BelongsTo
    {
        return $this->belongsTo(ThesisDocument::class, 'student_document_id');
    }
}
