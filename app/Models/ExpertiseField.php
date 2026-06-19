<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

class ExpertiseField extends Model
{
    /** @use HasFactory<\Database\Factories\ExpertiseFieldFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $field): void {
            if ($field->lecturers()->exists()) {
                throw new LogicException('Bidang keilmuan yang masih digunakan dosen tidak dapat dihapus.');
            }
        });
    }

    public function lecturers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('assigned_by_user_id')
            ->withTimestamps();
    }
}
