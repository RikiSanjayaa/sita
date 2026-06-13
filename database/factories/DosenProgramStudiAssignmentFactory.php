<?php

namespace Database\Factories;

use App\Models\ProgramStudi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DosenProgramStudiAssignment>
 */
class DosenProgramStudiAssignmentFactory extends Factory
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
            'program_studi_id' => ProgramStudi::factory(),
            'concentration' => ProgramStudi::DEFAULT_GENERAL_CONCENTRATION,
            'is_primary' => true,
            'is_active' => true,
        ];
    }
}
