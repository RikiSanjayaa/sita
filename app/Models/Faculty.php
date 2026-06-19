<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Faculty extends Model
{
    /** @use HasFactory<\Database\Factories\FacultyFactory> */
    use HasFactory;

    public const PLACEHOLDER_CODE = 'UNASSIGNED';

    protected $fillable = [
        'name',
        'code',
        'slug',
        'is_active',
        'is_placeholder',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_placeholder' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $faculty): void {
            if ($faculty->is_placeholder) {
                throw new LogicException('Fakultas placeholder tidak dapat dihapus.');
            }

            if ($faculty->programStudis()->exists()) {
                throw new LogicException('Fakultas yang masih memiliki program studi tidak dapat dihapus.');
            }
        });
    }

    public function programStudis(): HasMany
    {
        return $this->hasMany(ProgramStudi::class);
    }

    public static function placeholderId(): int
    {
        return (int) self::query()
            ->where('is_placeholder', true)
            ->firstOrFail()
            ->getKey();
    }
}
