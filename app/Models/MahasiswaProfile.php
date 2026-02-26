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
        'program_studi',
        'angkatan',
        'status_akademik',
    ];

    /**
     * @var array<int, string>
     */
    private const FINAL_STATUSES = [
        'lulus',
        'drop',
        'nonaktif',
    ];

    protected static function booted(): void
    {
        static::updated(function (self $profile): void {
            if (! $profile->wasChanged('status_akademik')) {
                return;
            }

            $currentStatus = strtolower((string) $profile->status_akademik);

            if (! in_array($currentStatus, self::FINAL_STATUSES, true)) {
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
