<?php

namespace Database\Factories;

use App\Models\ThesisRevision;
use App\Models\ThesisRevisionRound;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ThesisRevisionRound>
 */
class ThesisRevisionRoundFactory extends Factory
{
    protected $model = ThesisRevisionRound::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'revision_id' => ThesisRevision::factory(),
            'round_no' => 1,
            'opened_at' => now(),
            'status' => ThesisRevisionRound::STATUS_AWAITING_STUDENT,
        ];
    }
}
