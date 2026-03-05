<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MahasiswaProfile extends Model
{
    /** @use HasFactory<\Database\Factories\MahasiswaProfileFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'nim',
        'program_studi_id',
        'angkatan',
        'is_active',
    ];

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class, 'program_studi_id');
    }

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updated(function (self $profile): void {
            if (!$profile->wasChanged('is_active')) {
                return;
            }

            if ($profile->is_active) {
                return;
            }

            MentorshipAssignment::query()
                ->where('student_user_id', $profile->user_id)
                ->where('status', AssignmentStatus::Active->value)
                ->update([
                    'status' => AssignmentStatus::Ended->value,
                    'ended_at' => now(),
                ]);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
