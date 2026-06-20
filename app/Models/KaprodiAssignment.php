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
        'capabilities',
    ];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'is_primary' => 'boolean',
        ];
    }

    public const CAPABILITY_MANAGE_SUPERVISORS = 'manage_supervisors';

    public const CAPABILITY_SCHEDULE_SEMPRO = 'schedule_sempro';

    public const CAPABILITY_SCHEDULE_SIDANG = 'schedule_sidang';

    public const CAPABILITY_MANAGE_LECTURER_QUOTA = 'manage_lecturer_quota';

    public const CAPABILITY_VIEW_DOCUMENTS = 'view_documents';

    public const CAPABILITY_DOWNLOAD_DOCUMENTS = 'download_documents';

    /**
     * @return array<string, string>
     */
    public static function capabilityLabels(): array
    {
        return [
            self::CAPABILITY_MANAGE_SUPERVISORS => 'Tetapkan pembimbing',
            self::CAPABILITY_SCHEDULE_SEMPRO => 'Jadwalkan ujian proposal',
            self::CAPABILITY_SCHEDULE_SIDANG => 'Jadwalkan ujian akhir',
            self::CAPABILITY_MANAGE_LECTURER_QUOTA => 'Atur kuota bimbingan',
            self::CAPABILITY_VIEW_DOCUMENTS => 'Lihat dokumen',
            self::CAPABILITY_DOWNLOAD_DOCUMENTS => 'Download dokumen',
        ];
    }

    /**
     * @return array<string, bool>
     */
    public static function defaultCapabilities(): array
    {
        return collect(array_keys(self::capabilityLabels()))
            ->mapWithKeys(static fn(string $capability): array => [$capability => true])
            ->all();
    }

    /**
     * @param  array<int, string>|array<string, bool>|null  $capabilities
     * @return array<string, bool>
     */
    public static function normalizeCapabilities(?array $capabilities): array
    {
        $default = self::defaultCapabilities();

        if ($capabilities === null) {
            return $default;
        }

        $selected = array_is_list($capabilities)
            ? collect($capabilities)->mapWithKeys(static fn(string $capability): array => [$capability => true])->all()
            : $capabilities;

        return collect($default)
            ->mapWithKeys(static fn(bool $enabled, string $capability): array => [
                $capability => (bool) ($selected[$capability] ?? false),
            ])
            ->all();
    }

    public function hasCapability(string $capability): bool
    {
        $capabilities = self::normalizeCapabilities($this->capabilities);

        return (bool) ($capabilities[$capability] ?? false);
    }

    protected static function booted(): void
    {
        static::saving(function (self $assignment): void {
            $assignment->primary_guard = $assignment->is_primary ? 1 : null;
            self::validateAssignment($assignment);
        });

        static::saved(function (self $assignment): void {
            self::ensureUserHasKaprodiLecturerIdentity($assignment);
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

    private static function ensureUserHasKaprodiLecturerIdentity(self $assignment): void
    {
        $user = $assignment->user;

        if (! $user instanceof User) {
            return;
        }

        $roleIds = collect([AppRole::Kaprodi->value, AppRole::Dosen->value])
            ->map(fn(string $role): int => Role::query()->firstOrCreate(['name' => $role])->id)
            ->all();

        $user->roles()->syncWithoutDetaching($roleIds);

        $programStudi = $assignment->programStudi;
        $concentration = $programStudi?->concentrationList()[0] ?? ProgramStudi::DEFAULT_GENERAL_CONCENTRATION;

        $user->dosenProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'program_studi_id' => $assignment->program_studi_id,
                'concentration' => $concentration,
                'supervision_quota' => $user->dosenProfile?->supervision_quota ?? 14,
                'is_active' => true,
            ],
        );

        DosenProgramStudiAssignment::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'program_studi_id' => $assignment->program_studi_id,
                'concentration' => $concentration,
            ],
            [
                'is_primary' => true,
                'is_active' => true,
            ],
        );

        $user->forceFill(['last_active_role' => AppRole::Kaprodi->value])->save();
    }
}
