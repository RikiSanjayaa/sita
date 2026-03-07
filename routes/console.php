<?php

use App\Services\LegacyThesisProjectBackfillService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('thesis-projects:backfill {--student-user-id=}', function () {
    $studentUserId = $this->option('student-user-id');

    $result = app(LegacyThesisProjectBackfillService::class)->backfill(
        filled($studentUserId) ? (int) $studentUserId : null,
    );

    $this->info('Legacy thesis workflow backfill completed.');
    $this->table(['Metric', 'Count'], collect($result)
        ->map(fn(int $count, string $label): array => [$label, $count])
        ->values()
        ->all());
})->purpose('Backfill legacy thesis workflow data into thesis_projects tables');
