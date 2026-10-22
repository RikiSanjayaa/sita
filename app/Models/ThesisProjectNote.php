<?php

namespace App\Models;

use App\Enums\ThesisNoteVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThesisProjectNote extends Model
{
    /** @use HasFactory<\Database\Factories\ThesisProjectNoteFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'author_user_id',
        'visibility',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => ThesisNoteVisibility::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ThesisProject::class, 'project_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
