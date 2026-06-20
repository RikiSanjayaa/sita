<?php

namespace App\Models;

use App\Enums\AppRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class SystemAnnouncement extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const TARGET_ALL = 'all';

    public const TARGET_FACULTIES = 'faculties';

    public const TARGET_PROGRAMS = 'programs';

    protected $fillable = [
        'title',
        'body',
        'target_roles',
        'target_scope',
        'program_studi_id',
        'target_faculty_ids',
        'target_program_studi_ids',
        'status',
        'published_at',
        'notified_at',
        'expires_at',
        'action_url',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'target_roles' => 'array',
            'target_faculty_ids' => 'array',
            'target_program_studi_ids' => 'array',
            'published_at' => 'datetime',
            'notified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class, 'program_studi_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return array<int, string>
     */
    public function normalizedTargetRoles(): array
    {
        $validRoles = AppRole::uiValues();

        return collect($this->target_roles)
            ->map(static fn($role): string => trim((string) $role))
            ->filter(static fn(string $role): bool => in_array($role, $validRoles, true))
            ->unique()
            ->values()
            ->all();
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * @return array<int, int>
     */
    public function resolvedProgramStudiIds(): array
    {
        if ($this->program_studi_id !== null) {
            return [(int) $this->program_studi_id];
        }

        if ($this->target_scope === self::TARGET_FACULTIES) {
            return ProgramStudi::query()
                ->whereIn('faculty_id', $this->normalizedIds($this->target_faculty_ids))
                ->pluck('id')
                ->map(static fn($id): int => (int) $id)
                ->all();
        }

        if ($this->target_scope === self::TARGET_PROGRAMS) {
            return $this->normalizedIds($this->target_program_studi_ids);
        }

        return [];
    }

    public function audienceLabel(): string
    {
        if ($this->program_studi_id !== null) {
            return $this->programStudi?->name ?? 'Program studi tidak tersedia';
        }

        if ($this->target_scope === self::TARGET_FACULTIES) {
            $names = Faculty::query()
                ->whereIn('id', $this->normalizedIds($this->target_faculty_ids))
                ->orderBy('name')
                ->pluck('name');

            return $this->prefixedAudienceLabel('Fakultas', $names);
        }

        if ($this->target_scope === self::TARGET_PROGRAMS) {
            $names = ProgramStudi::query()
                ->whereIn('id', $this->normalizedIds($this->target_program_studi_ids))
                ->orderBy('name')
                ->pluck('name');

            return $this->prefixedAudienceLabel('Prodi', $names);
        }

        return 'Semua Fakultas & Prodi';
    }

    /**
     * @param  mixed  $ids
     * @return array<int, int>
     */
    private function normalizedIds($ids): array
    {
        return collect(is_array($ids) ? $ids : [])
            ->map(static fn($id): int => (int) $id)
            ->filter(static fn(int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, string>  $names
     */
    private function prefixedAudienceLabel(string $prefix, Collection $names): string
    {
        return $names->isEmpty()
            ? $prefix.': belum dipilih'
            : $prefix.': '.$names->implode(', ');
    }
}
