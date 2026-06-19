<?php

namespace Database\Factories;

use App\Models\Faculty;
use App\Models\ProgramStudi;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProgramStudi>
 */
class ProgramStudiFactory extends Factory
{
    protected $model = ProgramStudi::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'faculty_id' => Faculty::factory(),
            'name' => $name,
            'slug' => function (array $attributes): string {
                return Str::slug((string) ($attributes['name'] ?? 'program-studi'));
            },
            'concentrations' => [ProgramStudi::DEFAULT_GENERAL_CONCENTRATION],
            'degree_levels' => ['s1'],
        ];
    }
}
