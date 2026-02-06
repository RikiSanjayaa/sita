<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MentorshipDocument>
 */
class MentorshipDocumentFactory extends Factory
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
            'mentorship_assignment_id' => null,
            'title' => fake()->sentence(3),
            'file_name' => 'dokumen_ta_v'.fake()->numberBetween(1, 5).'.pdf',
            'file_url' => null,
            'file_size_kb' => fake()->numberBetween(150, 1500),
            'status' => fake()->randomElement(['submitted', 'needs_revision', 'approved']),
            'revision_notes' => null,
            'reviewed_at' => null,
            'uploaded_by_user_id' => User::factory()->asMahasiswa(),
        ];
    }
}
