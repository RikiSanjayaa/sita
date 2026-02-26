<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MahasiswaProfile>
 */
class MahasiswaProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->asMahasiswa(),
            'nim' => fake()->unique()->numerify('##########'),
            'program_studi' => 'Teknik Informatika',
            'angkatan' => (int) now()->format('Y'),
            'status_akademik' => 'aktif',
        ];
    }
}
