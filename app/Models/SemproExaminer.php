<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SemproExaminer extends Model
{
    use HasFactory;

    protected $fillable = [
        'sempro_id',
        'examiner_user_id',
        'examiner_order',
        'decision',
        'decision_notes',
        'decided_at',
        'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'examiner_order' => 'int',
            'decided_at' => 'datetime',
        ];
    }

    public function sempro(): BelongsTo
    {
        return $this->belongsTo(Sempro::class);
    }

    public function examiner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'examiner_user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
