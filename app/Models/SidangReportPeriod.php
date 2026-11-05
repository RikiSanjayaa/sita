<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SidangReportPeriod extends Model
{
    protected $fillable = ['program_studi_id', 'semester', 'start_year', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return ['start_year' => 'integer', 'starts_on' => 'immutable_date', 'ends_on' => 'immutable_date'];
    }

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class);
    }

    public function label(): string
    {
        return 'Semester '.ucfirst($this->semester).' '.$this->start_year.'/'.($this->start_year + 1);
    }
}
