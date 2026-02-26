<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Services\MentorshipAssignmentService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MentorshipAssignment extends Model
{
    /** @use HasFactory<\Database\Factories\MentorshipAssignmentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'student_user_id',
        'lecturer_user_id',
        'advisor_type',
        'status',
        'assigned_by',
        'started_at',
        'ended_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $assignment): void {
            app(MentorshipAssignmentService::class)->validateForSave($assignment);
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(MentorshipSchedule::class, 'mentorship_assignment_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MentorshipDocument::class, 'mentorship_assignment_id');
    }

    public function isActive(): bool
    {
        return $this->status === AssignmentStatus::Active->value;
    }
}
