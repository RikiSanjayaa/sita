<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThesisProjectTitle extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'version_no',
        'title_id',
        'title_en',
        'proposal_summary',
        'status',
        'submitted_by_user_id',
        'submitted_at',
        'decided_by_user_id',
        'decided_at',
        'decision_notes',
    ];

    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ThesisProject::class, 'project_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function defenses(): HasMany
    {
        return $this->hasMany(ThesisDefense::class, 'title_version_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ThesisDocument::class, 'title_version_id');
    }
}
