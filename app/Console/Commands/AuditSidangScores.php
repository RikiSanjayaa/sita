<?php

namespace App\Console\Commands;

use App\Models\ThesisDefense;
use App\Services\SidangScoreService;
use Illuminate\Console\Command;

class AuditSidangScores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sidang:audit-scores';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List legacy sidang score conflicts as JSON lines without changing data';

    /**
     * Execute the console command.
     */
    public function handle(SidangScoreService $scores): int
    {
        ThesisDefense::query()->where('type', 'sidang')
            ->with('examiners')->eachById(function (ThesisDefense $defense) use ($scores): void {
                foreach ($scores->legacyIssues($defense) as $issue) {
                    $this->line(json_encode($issue, JSON_THROW_ON_ERROR));
                }
            });

        return self::SUCCESS;
    }
}
