<?php

namespace App\Models;

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
        'name',
        'slug',
        'concentrations',
        'student_guide_content',
        'student_guide_updated_by',
        'student_guide_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'concentrations' => 'array',
            'student_guide_content' => 'array',
            'student_guide_updated_at' => 'datetime',
        ];
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
    public static function defaultConcentrationsForSlug(?string $slug): array
    {
        if ($slug === 'ilkom') {
            return self::ILMU_KOMPUTER_CONCENTRATIONS;
        }

        return [self::DEFAULT_GENERAL_CONCENTRATION];
    }
}
