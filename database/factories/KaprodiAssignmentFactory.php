<?php

namespace Database\Factories;

use App\Models\KaprodiAssignment;
use App\Models\ProgramStudi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KaprodiAssignment>
 */
class KaprodiAssignmentFactory extends Factory
{
    protected $model = KaprodiAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_studi_id' => ProgramStudi::factory(),
            'user_id' => User::factory()->state(['last_active_role' => 'kaprodi']),
            'is_primary' => true,
            'capabilities' => KaprodiAssignment::defaultCapabilities(),
        ];
    }
}
