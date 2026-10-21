<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ThesisProject extends Model
{
    use HasFactory;

    /**
     * Sempro results that make the final title public. A failed sempro never does.
     */
    public const PUBLIC_TITLE_SEMPRO_RESULTS = ['pass', 'pass_with_revision'];

    protected $fillable = [
        'student_user_id',
        'program_studi_id',
        'legacy_thesis_submission_id',
        'phase',
        'state',
        'started_at',
        'completed_at',
        'cancelled_at',
        'created_by',
        'closed_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'legacy_thesis_submission_id' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class, 'program_studi_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function legacySubmission(): BelongsTo
    {
        return $this->belongsTo(ThesisSubmission::class, 'legacy_thesis_submission_id');
    }

    public function titles(): HasMany
    {
        return $this->hasMany(ThesisProjectTitle::class, 'project_id');
    }

    public function latestTitle(): HasOne
    {
        return $this->hasOne(ThesisProjectTitle::class, 'project_id')->latestOfMany('version_no');
    }

    public function supervisorAssignments(): HasMany
    {
        return $this->hasMany(ThesisSupervisorAssignment::class, 'project_id');
    }

    public function activeSupervisorAssignments(): HasMany
    {
        return $this->hasMany(ThesisSupervisorAssignment::class, 'project_id')
            ->where('status', 'active');
    }

    public function defenses(): HasMany
    {
        return $this->hasMany(ThesisDefense::class, 'project_id');
    }

    public function semproDefenses(): HasMany
    {
        return $this->hasMany(ThesisDefense::class, 'project_id')
            ->where('type', 'sempro');
    }

    public function sidangDefenses(): HasMany
    {
        return $this->hasMany(ThesisDefense::class, 'project_id')
            ->where('type', 'sidang');
    }

    public function passedSemproDefenses(): HasMany
    {
        return $this->hasMany(ThesisDefense::class, 'project_id')
            ->where('type', 'sempro')
            ->where('status', 'completed')
            ->whereIn('result', self::PUBLIC_TITLE_SEMPRO_RESULTS);
    }

    public function approvedTitles(): HasMany
    {
        return $this->hasMany(ThesisProjectTitle::class, 'project_id')
            ->where('status', 'approved')
            ->orderByDesc('version_no');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ThesisRevision::class, 'project_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ThesisDocument::class, 'project_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ThesisProjectEvent::class, 'project_id');
    }

    /**
     * Projects whose final title may be shown publicly: a passed sempro, or a
     * completed thesis. Cancelled projects never qualify.
     */
    public function scopeHasPublicTitle(Builder $query): void
    {
        $query->where('state', '!=', 'cancelled')
            ->where(function (Builder $query): void {
                $query->where('state', 'completed')
                    ->orWhereHas('passedSemproDefenses');
            });
    }

    public function hasPublicTitle(): bool
    {
        return $this->state !== 'cancelled'
            && ($this->state === 'completed' || $this->passedSemproDefenses->isNotEmpty());
    }

    /**
     * The approved title students may see publicly, or null while the rule fails.
     */
    public function publicTitle(): ?ThesisProjectTitle
    {
        return $this->hasPublicTitle() ? $this->approvedTitles->first() : null;
    }
}
