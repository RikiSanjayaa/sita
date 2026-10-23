<?php

namespace App\Services;

use App\Models\ThesisDefense;
use App\Models\ThesisProject;

class ThesisProjectWorkflowResolver
{
    /**
     * Resolves the workflow key shown to the mahasiswa for a thesis project.
     *
     * The project relations used here (defenses, revisions, activeSupervisorAssignments,
     * latestTitle) must be loaded by the caller.
     */
    public function resolveKey(ThesisProject $project): string
    {
        $project->loadMissing('latestTitle');

        if ($project->state === 'cancelled') {
            return $project->latestTitle?->status === 'rejected'
                ? 'title_rejected'
                : 'project_cancelled';
        }

        $hasOpenRevisions = $project->revisions->whereIn('status', ['open', 'submitted'])->isNotEmpty();
        $hasActiveSupervisors = $project->activeSupervisorAssignments->isNotEmpty();

        /** @var ThesisDefense|null $latestSidang */
        $latestSidang = $project->defenses
            ->where('type', 'sidang')
            ->sortByDesc('attempt_no')
            ->first();

        if ($latestSidang instanceof ThesisDefense) {
            $sidangKey = match ($latestSidang->status) {
                'scheduled' => 'sidang_scheduled',
                'awaiting_finalization' => 'sidang_waiting_result',
                'completed' => match ($latestSidang->result) {
                    'pass' => 'completed',
                    'pass_with_revision' => $hasOpenRevisions ? 'sidang_revision' : 'completed',
                    'fail' => 'sidang_failed',
                    default => 'sidang_scheduled',
                },
                default => null,
            };

            if ($sidangKey !== null) {
                return $sidangKey;
            }
        }

        /** @var ThesisDefense|null $latestSempro */
        $latestSempro = $project->defenses
            ->where('type', 'sempro')
            ->sortByDesc('attempt_no')
            ->first();

        if ($latestSempro instanceof ThesisDefense) {
            $semproKey = match (true) {
                $latestSempro->status === 'scheduled' => 'sempro_scheduled',
                $latestSempro->status === 'awaiting_finalization' => 'sempro_waiting_result',
                $latestSempro->status === 'completed' && $latestSempro->result === 'pass_with_revision' => $hasOpenRevisions
                    ? 'sempro_revision'
                    : ($hasActiveSupervisors ? 'research_in_progress' : 'sempro_passed'),
                $latestSempro->status === 'completed' && $latestSempro->result === 'pass' => $hasActiveSupervisors
                    ? 'research_in_progress'
                    : 'sempro_passed',
                $latestSempro->status === 'completed' && $latestSempro->result === 'fail' => 'sempro_failed',
                default => null,
            };

            if ($semproKey !== null) {
                return $semproKey;
            }
        }

        if ($project->phase === 'title_review') {
            return 'title_review_pending';
        }

        if ($project->latestTitle?->status === 'approved') {
            return 'title_approved';
        }

        return $hasActiveSupervisors ? 'research_in_progress' : 'title_review_pending';
    }
}
