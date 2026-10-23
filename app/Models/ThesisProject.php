<?php

namespace App\Models;

use App\Support\WitaDateTime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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
        'archived_at',
        'archive_summary',
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
            'archived_at' => 'datetime',
            'archive_summary' => 'array',
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

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Completed projects whose grace period has passed and that are not archived yet.
     * Cancelled and active projects never qualify.
     */
    public function scopeDueForArchive(Builder $query, ?CarbonInterface $now = null): void
    {
        $cutoff = ($now ?? now())->copy()->subDays((int) config('sita.archive_grace_days', 30));

        $query->where($query->getModel()->qualifyColumn('state'), 'completed')
            ->whereNull($query->getModel()->qualifyColumn('archived_at'))
            ->whereNotNull($query->getModel()->qualifyColumn('completed_at'))
            ->where($query->getModel()->qualifyColumn('completed_at'), '<=', $cutoff);
    }

    /**
     * Completed project still inside the grace period (chat is read-only, nothing deleted yet).
     */
    public function scopeInArchiveGrace(Builder $query, ?CarbonInterface $now = null): void
    {
        $cutoff = ($now ?? now())->copy()->subDays((int) config('sita.archive_grace_days', 30));

        $query->where($query->getModel()->qualifyColumn('state'), 'completed')
            ->whereNull($query->getModel()->qualifyColumn('archived_at'))
            ->where(function (Builder $query) use ($cutoff): void {
                $query->whereNull($query->getModel()->qualifyColumn('completed_at'))
                    ->orWhere($query->getModel()->qualifyColumn('completed_at'), '>', $cutoff);
            });
    }

    /**
     * When the archive command will remove mentorship data, or null for a project that
     * is not completed or is already archived.
     */
    public function archiveDueAt(): ?CarbonImmutable
    {
        if ($this->state !== 'completed' || $this->archived_at !== null || $this->completed_at === null) {
            return null;
        }

        return CarbonImmutable::instance($this->completed_at)->addDays((int) config('sita.archive_grace_days', 30));
    }

    /**
     * Completed bimbingan sessions of one lecturer, read from archive_summary. Null while
     * the project is not archived, so callers fall back to the live count.
     */
    public function archivedCompletedCount(int $lecturerUserId): ?int
    {
        if (! $this->isArchived()) {
            return null;
        }

        foreach ($this->archive_summary['completed_mentorship'] ?? [] as $row) {
            if ((int) ($row['lecturer_user_id'] ?? 0) === $lecturerUserId) {
                return (int) ($row['completed'] ?? 0);
            }
        }

        return 0;
    }

    /**
     * Last activity captured before the mentorship data was deleted, or null when not archived.
     */
    public function archivedLastActivity(): ?CarbonImmutable
    {
        $value = $this->isArchived() ? ($this->archive_summary['last_activity_at'] ?? null) : null;

        return $value === null ? null : CarbonImmutable::parse($value);
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
