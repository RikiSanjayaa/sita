<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DosenProfile extends Model
{
    /** @use HasFactory<\Database\Factories\DosenProfileFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'nik',
        'program_studi_id',
        'concentration',
        'supervision_quota',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::saved(function (DosenProfile $profile): void {
            if ($profile->program_studi_id === null || $profile->concentration === null) {
                return;
            }

            DosenProgramStudiAssignment::query()->updateOrCreate(
                [
                    'user_id' => $profile->user_id,
                    'program_studi_id' => $profile->program_studi_id,
                    'concentration' => $profile->concentration,
                ],
                [
                    'is_primary' => true,
                    'is_active' => $profile->is_active,
                ],
            );
        });
    }

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class, 'program_studi_id');
    }

    public function programStudiAssignments(): HasMany
    {
        return $this->hasMany(DosenProgramStudiAssignment::class, 'user_id', 'user_id');
    }

    public function activeProgramStudiAssignments(): HasMany
    {
        return $this->programStudiAssignments()->where('is_active', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'supervision_quota' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
