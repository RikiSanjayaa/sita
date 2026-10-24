<?php

namespace App\Console\Commands;

use App\Services\ThesisArchivePlan;
use App\Services\ThesisArchiveService;
use Illuminate\Console\Command;
use Throwable;

class ArchiveCompletedThesesCommand extends Command
{
    protected $signature = 'sita:archive-completed-theses {--dry-run : Print what would be deleted without deleting anything}';

    protected $description = 'Permanently delete mentorship chat, schedules and draft documents of theses completed longer than the grace period, keeping the final manuscript and a progress summary';

    public function handle(ThesisArchiveService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $projects = $service->dueProjects();

        $this->line(sprintf(
            '%s grace period %d day(s); %d completed thesis(es) due.',
            $dryRun ? 'DRY RUN (nothing is deleted).' : 'ARCHIVE (deletion is permanent).',
            (int) config('sita.archive_grace_days', 30),
            $projects->count(),
        ));

        $failed = 0;
        foreach ($projects as $project) {
            try {
                $plan = $dryRun ? $service->plan($project) : $service->archive($project);
            } catch (Throwable $exception) {
                $failed++;
                report($exception);
                $this->error(sprintf('Thesis #%d failed and was left untouched: %s', $project->getKey(), $exception->getMessage()));

                continue;
            }

            if (! $plan instanceof ThesisArchivePlan) {
                $this->line(sprintf('Thesis #%d skipped (no longer due).', $project->getKey()));

                continue;
            }

            $this->describe($plan, $dryRun);
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function describe(ThesisArchivePlan $plan, bool $dryRun): void
    {
        $project = $plan->project;
        $this->line('');
        $this->line(sprintf(
            'Thesis #%d, student #%d %s, completed %s',
            $project->getKey(),
            $project->student_user_id,
            $project->student?->name ?? '-',
            $project->completed_at?->toDateString() ?? '-',
        ));

        foreach ($plan->counts() as $label => $count) {
            $this->line(sprintf('  %s %-22s %d', $dryRun ? 'would delete' : 'deleted     ', $label, $count));
        }

        $this->line(sprintf('  kept final manuscript: %s', $plan->keptFinalDocumentId === null ? 'none' : 'thesis_documents #'.$plan->keptFinalDocumentId));
        $this->line(sprintf('  summary: %s', json_encode($plan->summary, JSON_UNESCAPED_SLASHES)));

        foreach ($plan->filesToDelete as $file) {
            $this->line(sprintf('  %s file %s:%s', $dryRun ? 'would delete' : 'deleted     ', $file['disk'], $file['path']));
        }
        foreach ($plan->filesKept as $file) {
            $this->line(sprintf('  keeps file (still referenced) %s:%s', $file['disk'], $file['path']));
        }
    }
}
