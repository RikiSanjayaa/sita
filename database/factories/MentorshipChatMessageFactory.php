<?php

namespace Database\Factories;

use App\Models\MentorshipChatThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MentorshipChatMessage>
 */
class MentorshipChatMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mentorship_chat_thread_id' => MentorshipChatThread::factory(),
            'sender_user_id' => User::factory()->asDosen(),
            'related_document_id' => null,
            'message_type' => 'text',
            'message' => fake()->sentence(),
            'sent_at' => now(),
        ];
    }
}
