<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThesisDefense extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'title_version_id',
        'legacy_sempro_id',
        'type',
        'attempt_no',
        'status',
        'result',
        'scheduled_for',
        'location',
        'mode',
        'created_by',
        'decided_by',
        'decision_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'legacy_sempro_id' => 'integer',
            'attempt_no' => 'integer',
            'scheduled_for' => 'datetime',
            'decision_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ThesisProject::class, 'project_id');
    }

    public function titleVersion(): BelongsTo
    {
        return $this->belongsTo(ThesisProjectTitle::class, 'title_version_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function examiners(): HasMany
    {
        return $this->hasMany(ThesisDefenseExaminer::class, 'defense_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ThesisRevision::class, 'defense_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ThesisDocument::class, 'defense_id');
    }
}
