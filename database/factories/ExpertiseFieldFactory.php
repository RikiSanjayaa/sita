<?php

namespace Database\Factories;

use App\Models\ExpertiseField;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExpertiseField>
 */
class ExpertiseFieldFactory extends Factory
{
    protected $model = ExpertiseField::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9999),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
