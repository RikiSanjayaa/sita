<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MentorshipSchedule>
 */
class MentorshipScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $requestedFor = fake()->dateTimeBetween('-2 days', '+3 days');

        return [
            'student_user_id' => User::factory()->asMahasiswa(),
            'lecturer_user_id' => User::factory()->asDosen(),
            'mentorship_assignment_id' => null,
            'topic' => fake()->sentence(3),
            'status' => fake()->randomElement(['pending', 'approved', 'rescheduled', 'rejected']),
            'requested_for' => $requestedFor,
            'scheduled_for' => $requestedFor,
            'location' => fake()->randomElement(['Ruang Dosen', 'Google Meet']),
            'student_note' => null,
            'lecturer_note' => null,
            'created_by_user_id' => User::factory()->asMahasiswa(),
        ];
    }
}
