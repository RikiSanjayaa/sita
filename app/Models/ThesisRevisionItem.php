<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThesisRevisionItem extends Model
{
    /** @use HasFactory<\Database\Factories\ThesisRevisionItemFactory> */
    use HasFactory;

    public const PRIORITIES = ['major', 'minor'];

    public const STATUS_OPEN = 'open';

    public const STATUS_ADDRESSED = 'addressed';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REOPENED = 'reopened';

    protected $fillable = [
        'revision_id',
        'round_opened',
        'location',
        'description',
        'priority',
        'status',
        'student_response',
        'student_response_location',
        'verification_comment',
        'verified_at',
        'verified_by_user_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'round_opened' => 'integer',
            'sort_order' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(ThesisRevision::class, 'revision_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
