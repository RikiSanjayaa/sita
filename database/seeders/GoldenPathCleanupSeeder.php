<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Runs after tests/e2e/specs/golden-path.spec.ts (also when it fails): removes
 * the golden path student and everything it owns. See GoldenPathScenarioSeeder::cleanup().
 */
class GoldenPathCleanupSeeder extends Seeder
{
    public function run(): void
    {
        GoldenPathScenarioSeeder::cleanup();
    }
}
