<?php

namespace Database\Factories;

use App\Models\ThesisProject;
use App\Models\ThesisRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ThesisRevision>
 */
class ThesisRevisionFactory extends Factory
{
    protected $model = ThesisRevision::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => ThesisProject::factory(),
            'status' => 'open',
            'notes' => fake()->sentence(),
        ];
    }
}
