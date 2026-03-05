<?php

namespace Database\Factories;

use App\Models\AdminProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminProfile>
 */
class AdminProfileFactory extends Factory
{
    protected $model = AdminProfile::class;



    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->asAdmin(),
            'program_studi_id' => \App\Models\ProgramStudi::factory(),
        ];
    }
}
