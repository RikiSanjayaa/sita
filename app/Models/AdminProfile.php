<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminProfile extends Model
{
  /** @use HasFactory<\Database\Factories\AdminProfileFactory> */
  use HasFactory;

  /**
   * @var list<string>
   */
  protected $fillable = [
    'user_id',
    'program_studi_id',
  ];

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  public function programStudi(): BelongsTo
  {
    return $this->belongsTo(ProgramStudi::class, 'program_studi_id');
  }
}
