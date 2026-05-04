<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CsatResponse extends Model
{
    use HasFactory;

    public const COOLDOWN_DAYS = 30;

    public const LOW_SCORE_THRESHOLD = 2;

    protected $fillable = [
        'user_id',
        'program_studi_id',
        'respondent_role',
        'score',
        'kritik',
        'saran',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class);
    }

    public function scopeVisibleToAdmin(Builder $query, ?User $user): Builder
    {
        $programStudiId = $user?->adminProgramStudiId();

        if ($programStudiId !== null) {
            $query->where('program_studi_id', $programStudiId);
        }

        return $query;
    }

    public function scopeRecent(Builder $query, int $days = self::COOLDOWN_DAYS): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function cooldownEndsAt(): ?CarbonInterface
    {
        return $this->created_at?->copy()->addDays(self::COOLDOWN_DAYS);
    }

    public static function respondentRoleOptions(): array
    {
        return [
            'mahasiswa' => 'Mahasiswa',
            'dosen' => 'Dosen',
        ];
    }

    public static function respondentRoleLabel(string $role): string
    {
        return self::respondentRoleOptions()[$role] ?? ucfirst($role);
    }
}
