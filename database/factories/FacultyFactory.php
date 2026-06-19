<?php

namespace Database\Factories;

use App\Models\Faculty;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Faculty>
 */
class FacultyFactory extends Factory
{
    protected $model = Faculty::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => Str::title($name),
            'code' => fake()->unique()->bothify('FAC-###??'),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9999),
            'is_active' => true,
            'is_placeholder' => false,
        ];
    }
}
