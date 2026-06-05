<?php

namespace App\Models;

use App\Enums\AppRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class KaprodiAssignment extends Model
{
    /** @use HasFactory<\Database\Factories\KaprodiAssignmentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'program_studi_id',
        'user_id',
        'is_primary',
        'primary_guard',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $assignment): void {
            $assignment->primary_guard = $assignment->is_primary ? 1 : null;
            self::validateAssignment($assignment);
        });

        static::saved(function (self $assignment): void {
            self::ensureUserHasKaprodiRole($assignment->user);
        });
    }

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class, 'program_studi_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    private static function validateAssignment(self $assignment): void
    {
        $programStudiId = (int) $assignment->program_studi_id;
        $userId = (int) $assignment->user_id;

        if ($programStudiId <= 0 || $userId <= 0) {
            return;
        }

        $existingForUser = self::query()
            ->where('user_id', $userId)
            ->where('program_studi_id', '!=', $programStudiId)
            ->when($assignment->exists, fn($query) => $query->whereKeyNot($assignment->getKey()))
            ->exists();

        if ($existingForUser) {
            throw ValidationException::withMessages([
                'user_id' => ['Satu user hanya boleh ditetapkan sebagai kaprodi pada satu program studi.'],
            ]);
        }

        $assignmentCount = self::query()
            ->where('program_studi_id', $programStudiId)
            ->when($assignment->exists, fn($query) => $query->whereKeyNot($assignment->getKey()))
            ->count();

        if ($assignmentCount >= 3) {
            throw ValidationException::withMessages([
                'program_studi_id' => ['Satu program studi hanya boleh memiliki maksimal tiga akun kaprodi.'],
            ]);
        }

        if (! $assignment->is_primary) {
            return;
        }

        $hasPrimary = self::query()
            ->where('program_studi_id', $programStudiId)
            ->where('is_primary', true)
            ->when($assignment->exists, fn($query) => $query->whereKeyNot($assignment->getKey()))
            ->exists();

        if ($hasPrimary) {
            throw ValidationException::withMessages([
                'is_primary' => ['Satu program studi hanya boleh memiliki satu kaprodi utama.'],
            ]);
        }
    }

    private static function ensureUserHasKaprodiRole(?User $user): void
    {
        if (! $user instanceof User) {
            return;
        }

        $role = Role::query()->firstOrCreate(['name' => AppRole::Kaprodi->value]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        $user->forceFill(['last_active_role' => AppRole::Kaprodi->value])->save();
    }
}
