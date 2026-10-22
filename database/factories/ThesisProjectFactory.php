<?php

namespace Database\Factories;

use App\Models\ProgramStudi;
use App\Models\ThesisProject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<ThesisProject>
 */
class ThesisProjectFactory extends Factory
{
    protected $model = ThesisProject::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_user_id' => User::factory()->asMahasiswa(),
            'program_studi_id' => ProgramStudi::factory(),
            'phase' => 'research',
            'state' => 'active',
            'started_at' => now(),
        ];
    }
}
