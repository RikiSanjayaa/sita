<?php

use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use Database\Seeders\ThesisWorkflowSeeder;
use Database\Seeders\UserSeeder;

test('thesis workflow seeder backfills broad project scenarios', function (): void {
    /** @var \Tests\TestCase $this */
    $this->seed(UserSeeder::class);
    $this->seed(ThesisWorkflowSeeder::class);

    $bagasProject = ThesisProject::query()
        ->whereHas('student', fn($query) => $query->where('email', 'bagas@sita.test'))
        ->firstOrFail();

    expect(ThesisProject::query()->count())->toBe(9)
        ->and(ThesisProject::query()->whereHas('student', fn($query) => $query->where('email', 'farhan@sita.test'))->exists())->toBeFalse()
        ->and(ThesisProject::query()->whereHas('student', fn($query) => $query->where('email', 'laila@sita.test'))->count())->toBe(2)
        ->and(ThesisProject::query()->where('phase', 'sidang')->exists())->toBeTrue()
        ->and(ThesisProject::query()->where('phase', 'completed')->exists())->toBeTrue()
        ->and(ThesisDefense::query()->where('type', 'sidang')->count())->toBe(2)
        ->and(ThesisDefense::query()->where('project_id', $bagasProject->id)->where('type', 'sempro')->count())->toBe(2)
        ->and(ThesisProjectEvent::query()->count())->toBeGreaterThan(0);
});
