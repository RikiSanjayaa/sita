<?php

namespace Database\Factories;

use App\Models\ProgramStudi;
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
            'program_studi_id' => ProgramStudi::factory(),
            'concentration' => ProgramStudi::DEFAULT_GENERAL_CONCENTRATION,
            'supervision_quota' => 14,
            'is_active' => true,
        ];
    }
}
