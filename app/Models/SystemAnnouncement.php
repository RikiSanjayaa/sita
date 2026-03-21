<?php

namespace App\Models;

use App\Enums\AppRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemAnnouncement extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'title',
        'body',
        'target_roles',
        'program_studi_id',
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
}
