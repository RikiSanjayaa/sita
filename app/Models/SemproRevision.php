<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SemproRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'sempro_id',
        'notes',
        'status',
        'due_at',
        'resolved_at',
        'requested_by_user_id',
        'resolved_by_user_id',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function sempro(): BelongsTo
    {
        return $this->belongsTo(Sempro::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
