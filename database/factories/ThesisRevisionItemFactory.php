<?php

namespace Database\Factories;

use App\Models\ThesisRevision;
use App\Models\ThesisRevisionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ThesisRevisionItem>
 */
class ThesisRevisionItemFactory extends Factory
{
    protected $model = ThesisRevisionItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'revision_id' => ThesisRevision::factory(),
            'round_opened' => 1,
            'location' => 'Bab 3, hlm. '.fake()->numberBetween(1, 80),
            'description' => fake()->sentence(),
            'priority' => null,
            'status' => ThesisRevisionItem::STATUS_OPEN,
            'sort_order' => 0,
        ];
    }
}
