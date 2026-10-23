<?php

namespace App\Models;

use App\Support\WitaDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

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
        'mentorship_valid_from',
        'mentorship_valid_until',
    ];

    protected function casts(): array
    {
        return [
            'legacy_thesis_submission_id' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'mentorship_valid_from' => 'date',
            'mentorship_valid_until' => 'date',
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

    public function lecturerNotes(): HasMany
    {
        return $this->hasMany(ThesisProjectNote::class, 'project_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ThesisProjectEvent::class, 'project_id');
    }

    /**
     * Only the latest thesis per student may be current, and cancellation never
     * revives an older thesis. All historical rows remain available to admin.
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->latestPerStudent()->where($query->getModel()->qualifyColumn('state'), '!=', 'cancelled');
    }

    /** Latest lifecycle row, including owner-only cancellation feedback. */
    public function scopeLatestPerStudent(Builder $query): void
    {
        $table = $query->getModel()->getTable();

        $query->where($table.'.id', function ($latest) use ($table): void {
            $latest->select('latest_project.id')
                ->from('thesis_projects as latest_project')
                ->whereColumn('latest_project.student_user_id', $table.'.student_user_id')
                ->orderByRaw('CASE WHEN latest_project.started_at IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('latest_project.started_at')
                ->orderByDesc('latest_project.id')
                ->limit(1);
        });
    }

    /**
     * Projects whose final title may be shown publicly: a passed sempro, or a
     * completed thesis. Cancelled and superseded projects never qualify.
     */
    public function scopeHasPublicTitle(Builder $query): void
    {
        $query->current()
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

    /**
     * Mentorship ticket validity is enforced only once a date is set. A null
     * valid-until never suspends, so projects that predate the ticket keep working.
     */
    public function isMentorshipSuspended(): bool
    {
        return $this->state === 'active'
            && $this->mentorship_valid_until !== null
            && $this->mentorship_valid_until->lt(Carbon::today(WitaDateTime::TIMEZONE));
    }

    /**
     * The date the ticket lapsed, or null while mentorship activity is allowed.
     */
    public function mentorshipSuspendedSince(): ?CarbonImmutable
    {
        return $this->isMentorshipSuspended() ? CarbonImmutable::instance($this->mentorship_valid_until) : null;
    }
}
