<?php

namespace App\Models;

use App\Enums\DegreeLevel;
use App\Support\StudentGuideContent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramStudi extends Model
{
    /** @use HasFactory<\Database\Factories\ProgramStudiFactory> */
    use HasFactory;

    public const DEFAULT_GENERAL_CONCENTRATION = 'Umum';

    /**
     * @var array<int, string>
     */
    public const ILMU_KOMPUTER_CONCENTRATIONS = [
        'Jaringan',
        'Sistem Cerdas',
        'Computer Vision',
    ];

    protected $table = 'program_studis';

    protected $fillable = [
        'faculty_id',
        'name',
        'slug',
        'concentrations',
        'degree_levels',
        'student_guide_content',
        'student_guide_updated_by',
        'student_guide_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'concentrations' => 'array',
            'degree_levels' => 'array',
            'student_guide_content' => 'array',
            'student_guide_updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $programStudi): void {
            $programStudi->faculty_id ??= Faculty::placeholderId();
            $programStudi->degree_levels = self::normalizeDegreeLevels($programStudi->degree_levels);
        });

        static::saving(function (self $programStudi): void {
            $programStudi->degree_levels = self::normalizeDegreeLevels($programStudi->degree_levels);
        });
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function studentGuideUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_guide_updated_by');
    }

    public function mahasiswaProfiles(): HasMany
    {
        return $this->hasMany(MahasiswaProfile::class, 'program_studi_id');
    }

    public function dosenProfiles(): HasMany
    {
        return $this->hasMany(DosenProfile::class, 'program_studi_id');
    }

    public function adminProfiles(): HasMany
    {
        return $this->hasMany(AdminProfile::class, 'program_studi_id');
    }

    public function kaprodiAssignments(): HasMany
    {
        return $this->hasMany(KaprodiAssignment::class, 'program_studi_id');
    }

    public function thesisSubmissions(): HasMany
    {
        return $this->hasMany(ThesisSubmission::class, 'program_studi_id');
    }

    public function thesisProjects(): HasMany
    {
        return $this->hasMany(ThesisProject::class, 'program_studi_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedStudentGuideContent(): array
    {
        return StudentGuideContent::normalize($this->student_guide_content);
    }

    /**
     * @return array<int, string>
     */
    public function concentrationList(): array
    {
        $concentrations = collect($this->concentrations)
            ->map(static fn($value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($concentrations !== []) {
            return $concentrations;
        }

        return self::defaultConcentrationsForSlug($this->slug);
    }

    /**
     * @return array<string, string>
     */
    public function concentrationOptions(): array
    {
        $concentrations = $this->concentrationList();

        return array_combine($concentrations, $concentrations) ?: [];
    }

    /**
     * @return array<int, string>
     */
    public function degreeLevelList(): array
    {
        return self::normalizeDegreeLevels($this->degree_levels);
    }

    /**
     * @return array<string, string>
     */
    public function degreeLevelOptions(): array
    {
        return collect($this->degreeLevelList())
            ->mapWithKeys(static fn(string $level): array => [
                $level => DegreeLevel::from($level)->label(),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function defaultConcentrationsForSlug(?string $slug): array
    {
        if ($slug === 'ilkom') {
            return self::ILMU_KOMPUTER_CONCENTRATIONS;
        }

        return [self::DEFAULT_GENERAL_CONCENTRATION];
    }

    /**
     * @param  mixed  $levels
     * @return array<int, string>
     */
    private static function normalizeDegreeLevels($levels): array
    {
        $normalized = collect(is_array($levels) ? $levels : [])
            ->map(static fn($level): string => strtolower(trim((string) $level)))
            ->filter(static fn(string $level): bool => in_array($level, DegreeLevel::values(), true))
            ->unique()
            ->values()
            ->all();

        return $normalized !== [] ? $normalized : [DegreeLevel::S1->value];
    }
}
