<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
        'scheduled_until',
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
            'scheduled_until' => 'datetime',
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

    /**
     * The one rule for who a mahasiswa may see on a defense panel.
     *
     * Sempro and Sinopsis always show their examiners. A Sidang keeps its additional
     * examiners (role `examiner`) secret until the defense is completed; the supervisors
     * who sit on the panel stay visible. Every payload sent to a mahasiswa must go
     * through this method or {@see self::examinersForStudent()}, never through
     * `$defense->examiners` directly. {@see self::scopeWithSecretExaminers()} is the
     * SQL form of the same rule.
     */
    public function examinersVisibleToStudent(): bool
    {
        return $this->type !== 'sidang' || $this->status === 'completed';
    }

    /**
     * Panel members a mahasiswa may see, in panel order. Needs `examiners` eager loaded.
     *
     * @return Collection<int, ThesisDefenseExaminer>
     */
    public function examinersForStudent(): Collection
    {
        $examiners = $this->examiners->sortBy('order_no');

        if (! $this->examinersVisibleToStudent()) {
            $examiners = $examiners->filter(fn(ThesisDefenseExaminer $examiner): bool => $examiner->isSupervisorRole());
        }

        return $examiners->values();
    }

    /**
     * Defenses whose additional examiners are still secret from the mahasiswa.
     *
     * @param  Builder<ThesisDefense>  $query
     * @return Builder<ThesisDefense>
     */
    public function scopeWithSecretExaminers(Builder $query): Builder
    {
        return $query->where('type', 'sidang')->where('status', '!=', 'completed');
    }

    /**
     * Examiners grade with a score for sidang and for the OBE stage-1 exam
     * (Ujian Sinopsis). Non-OBE sempro is decision-only.
     */
    public function requiresScore(): bool
    {
        if ($this->type !== 'sempro') {
            return true;
        }

        $this->loadMissing('project.student.mahasiswaProfile');

        return (bool) $this->project?->student?->mahasiswaProfile?->is_obe_curriculum;
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ThesisDocument::class, 'defense_id');
    }
}
