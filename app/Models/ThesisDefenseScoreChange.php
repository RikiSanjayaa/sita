<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThesisDefenseScoreChange extends Model
{
    protected $fillable = ['examiner_id', 'actor_user_id', 'source', 'old_score', 'new_score'];

    protected function casts(): array
    {
        return ['old_score' => 'decimal:2', 'new_score' => 'decimal:2'];
    }
}
