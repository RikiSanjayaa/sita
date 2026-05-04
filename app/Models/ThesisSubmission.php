<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThesisSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_user_id',
        'program_studi_id',
        'title_id',
        'title_en',
        'proposal_summary',
        'proposal_file_path',
        'status',
        'is_active',
        'submitted_at',
        'approved_at',
        'approved_by',
    ];

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class, 'program_studi_id');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function sempros(): HasMany
    {
        return $this->hasMany(Sempro::class);
    }
}
