<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DosenProfile>
 */
class DosenProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->asDosen(),
            'nik' => fake()->unique()->numerify('################'),
            'program_studi_id' => \App\Models\ProgramStudi::factory(),
            'is_active' => true,
        ];
    }
}
