<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThesisDefenseExaminer extends Model
{
    use HasFactory;

    protected $fillable = [
        'defense_id',
        'lecturer_user_id',
        'legacy_sempro_examiner_id',
        'role',
        'order_no',
        'decision',
        'score',
        'notes',
        'decided_at',
        'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'legacy_sempro_examiner_id' => 'integer',
            'order_no' => 'integer',
            'score' => 'decimal:2',
            'decided_at' => 'datetime',
        ];
    }

    public function defense(): BelongsTo
    {
        return $this->belongsTo(ThesisDefense::class, 'defense_id');
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
