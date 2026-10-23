<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThesisDefenseExaminer extends Model
{
    use HasFactory;

    protected $fillable = [
        'defense_id',
        'lecturer_user_id',
        'legacy_sempro_examiner_id',
        'role',
        'order_no',
        'decision',
        'score',
        'notes',
        'revision_notes',
        'decided_at',
        'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'legacy_sempro_examiner_id' => 'integer',
            'order_no' => 'integer',
            'score' => 'decimal:2',
            'decided_at' => 'datetime',
        ];
    }

    public function defense(): BelongsTo
    {
        return $this->belongsTo(ThesisDefense::class, 'defense_id');
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_user_id');
    }

    /**
     * Supervisors sit on a Sidang panel automatically and are never secret.
     */
    public function isSupervisorRole(): bool
    {
        return in_array($this->role, ['primary_supervisor', 'secondary_supervisor'], true);
    }

    /**
     * Additional (non-supervisor) panel members of a Sidang that is not completed yet,
     * the people a mahasiswa must not learn about. See {@see ThesisDefense::examinersVisibleToStudent()}.
     *
     * @param  Builder<ThesisDefenseExaminer>  $query
     * @return Builder<ThesisDefenseExaminer>
     */
    public function scopeSecretFromStudent(Builder $query): Builder
    {
        return $query
            ->whereNotIn('role', ['primary_supervisor', 'secondary_supervisor'])
            ->whereHas('defense', fn(Builder $defense): Builder => $defense->withSecretExaminers());
    }

    /**
     * Lecturer ids that are secret sidang examiners for this mahasiswa right now.
     *
     * @return array<int, int>
     */
    public static function secretLecturerIdsForStudent(int $studentUserId): array
    {
        return static::query()
            ->secretFromStudent()
            ->whereHas('defense.project', fn(Builder $project): Builder => $project->where('student_user_id', $studentUserId))
            ->pluck('lecturer_user_id')
            ->map(static fn($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
