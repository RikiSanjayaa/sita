<?php

namespace Database\Factories;

use App\Enums\AdvisorType;
use App\Enums\AssignmentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MentorshipAssignment>
 */
class MentorshipAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_user_id' => User::factory()->asMahasiswa(),
            'lecturer_user_id' => User::factory()->asDosen(),
            'advisor_type' => fake()->randomElement([AdvisorType::Primary->value, AdvisorType::Secondary->value]),
            'status' => AssignmentStatus::Active->value,
            'assigned_by' => User::factory()->asAdmin(),
            'started_at' => now(),
            'ended_at' => null,
            'notes' => null,
        ];
    }
}
