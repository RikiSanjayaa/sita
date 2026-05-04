<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThesisRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'defense_id',
        'legacy_sempro_revision_id',
        'requested_by_user_id',
        'status',
        'notes',
        'due_at',
        'submitted_at',
        'resolved_at',
        'resolved_by_user_id',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'legacy_sempro_revision_id' => 'integer',
            'due_at' => 'datetime',
            'submitted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ThesisProject::class, 'project_id');
    }

    public function defense(): BelongsTo
    {
        return $this->belongsTo(ThesisDefense::class, 'defense_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ThesisDocument::class, 'revision_id');
    }
}
