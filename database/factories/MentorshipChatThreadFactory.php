<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MentorshipChatThread>
 */
class MentorshipChatThreadFactory extends Factory
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
            'is_escalated' => false,
            'escalated_at' => null,
        ];
    }
}
