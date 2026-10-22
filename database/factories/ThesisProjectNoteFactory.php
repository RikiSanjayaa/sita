<?php

namespace Database\Factories;

use App\Enums\ThesisNoteVisibility;
use App\Models\ThesisProject;
use App\Models\ThesisProjectNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<ThesisProjectNote>
 */
class ThesisProjectNoteFactory extends Factory
{
    protected $model = ThesisProjectNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => ThesisProject::factory(),
            'author_user_id' => User::factory()->asDosen(),
            'visibility' => ThesisNoteVisibility::Internal,
            'body' => fake()->sentence(),
        ];
    }

    public function internal(): static
    {
        return $this->state(['visibility' => ThesisNoteVisibility::Internal]);
    }

    public function forStudent(): static
    {
        return $this->state(['visibility' => ThesisNoteVisibility::Student]);
    }
}
