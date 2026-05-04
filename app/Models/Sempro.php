<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sempro extends Model
{
    use HasFactory;

    protected $fillable = [
        'thesis_submission_id',
        'status',
        'scheduled_for',
        'location',
        'mode',
        'revision_due_at',
        'approved_at',
        'approved_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'revision_due_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ThesisSubmission::class, 'thesis_submission_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function examiners(): HasMany
    {
        return $this->hasMany(SemproExaminer::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(SemproRevision::class);
    }
}
