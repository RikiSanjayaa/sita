<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramStudi extends Model
{
    /** @use HasFactory<\Database\Factories\ProgramStudiFactory> */
    use HasFactory;

    protected $table = 'program_studis';

    protected $fillable = [
        'name',
        'slug',
    ];

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
}
