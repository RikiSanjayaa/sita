<?php

namespace Database\Factories;

use App\Models\DosenProfile;
use App\Models\DosenProgramStudiAssignment;
use App\Models\ProgramStudi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DosenProfile>
 */
class DosenProfileFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (DosenProfile $profile): void {
            if ($profile->program_studi_id === null || $profile->concentration === null) {
                return;
            }

            DosenProgramStudiAssignment::query()->updateOrCreate(
                [
                    'user_id' => $profile->user_id,
                    'program_studi_id' => $profile->program_studi_id,
                    'concentration' => $profile->concentration,
                ],
                [
                    'is_primary' => true,
                    'is_active' => $profile->is_active,
                ],
            );
        });
    }

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
