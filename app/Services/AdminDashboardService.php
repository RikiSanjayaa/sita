<?php

namespace App\Services;

use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisRevision;
use App\Models\User;
use App\Support\AcademicTerminology;
use App\Support\WitaDateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AdminDashboardService
{
    public function metrics(?User $user): array
    {
        $projects = $this->projectsQuery($user);

        $activeProjects = (clone $projects)
            ->where('state', 'active')
            ->count();

        $earlyStageProjects = (clone $projects)
            ->where('state', 'active')
            ->whereIn('phase', ['title_review', 'sempro'])
            ->count();

        $lateStageProjects = (clone $projects)
            ->where('state', 'active')
            ->whereIn('phase', ['research', 'sidang'])
            ->count();

        $needsSempro = (clone $projects)
            ->where('state', 'active')
            ->whereIn('phase', ['title_review', 'sempro'])
            ->whereDoesntHave('semproDefenses', function (Builder $query): void {
                $query->whereIn('status', ['scheduled', 'completed']);
            })
            ->count();

        $needsSidang = (clone $projects)
            ->where('state', 'active')
            ->whereIn('phase', ['research', 'sidang'])
            ->whereDoesntHave('sidangDefenses', function (Builder $query): void {
                $query->whereIn('status', ['scheduled', 'completed']);
            })
            ->count();

        $incompleteSupervisors = (clone $projects)
            ->where('state', 'active')
            ->whereIn('phase', ['research', 'sidang'])
            ->has('activeSupervisorAssignments', '<', 2)
            ->count();

        $openRevisions = ThesisRevision::query()
            ->whereIn('status', ['open', 'submitted'])
            ->whereHas('project', function (Builder $query) use ($user): void {
                $this->scopeProjectsByUser($query, $user);
            })
            ->count();

        $projectsWithOpenRevisions = ThesisRevision::query()
            ->whereIn('status', ['open', 'submitted'])
            ->whereHas('project', function (Builder $query) use ($user): void {
                $this->scopeProjectsByUser($query, $user);
            })
            ->distinct()
            ->count('project_id');

        $overdueRevisionProjects = ThesisRevision::query()
            ->whereIn('status', ['open', 'submitted'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereHas('project', function (Builder $query) use ($user): void {
                $this->scopeProjectsByUser($query, $user);
                $query->where('state', 'active');
            })
            ->distinct()
            ->count('project_id');

        $unassignedLateStageProjects = (clone $projects)
            ->where('state', 'active')
            ->whereIn('phase', ['research', 'sidang'])
            ->doesntHave('activeSupervisorAssignments')
            ->count();

        $upcomingAgenda = (clone $projects)
            ->whereHas('defenses', function (Builder $query): void {
                $query->where('status', 'scheduled')
                    ->whereNotNull('scheduled_for')
                    ->whereBetween('scheduled_for', [now(), now()->copy()->addDays(7)]);
            })
            ->count();

        $completedThisMonth = (clone $projects)
            ->where('state', 'completed')
            ->whereBetween('completed_at', [now()->copy()->startOfMonth(), now()->copy()->endOfMonth()])
            ->count();

        $onHold = (clone $projects)
            ->where('state', 'on_hold')
            ->count();

        return [
            'activeProjects' => $activeProjects,
            'earlyStageProjects' => $earlyStageProjects,
            'lateStageProjects' => $lateStageProjects,
            'needsSempro' => $needsSempro,
            'needsSidang' => $needsSidang,
            'incompleteSupervisors' => $incompleteSupervisors,
            'openRevisions' => $openRevisions,
            'projectsWithOpenRevisions' => $projectsWithOpenRevisions,
            'overdueRevisionProjects' => $overdueRevisionProjects,
            'upcomingAgenda' => $upcomingAgenda,
            'completedThisMonth' => $completedThisMonth,
            'onHold' => $onHold,
            'unassignedLateStageProjects' => $unassignedLateStageProjects,
        ];
    }

    public function phaseDistribution(?User $user): Collection
    {
        $counts = $this->projectsQuery($user)
            ->selectRaw('phase, count(*) as aggregate')
            ->groupBy('phase')
            ->pluck('aggregate', 'phase');

        return collect([
            ['phase' => 'title_review', 'label' => AcademicTerminology::phaseLabel('title_review'), 'color' => 'gray', 'hex' => '#94a3b8'],
            ['phase' => 'sempro', 'label' => AcademicTerminology::phaseLabel('sempro'), 'color' => 'info', 'hex' => '#06b6d4'],
            ['phase' => 'research', 'label' => AcademicTerminology::phaseLabel('research'), 'color' => 'warning', 'hex' => '#f59e0b'],
            ['phase' => 'sidang', 'label' => AcademicTerminology::phaseLabel('sidang'), 'color' => 'primary', 'hex' => '#2563eb'],
            ['phase' => 'completed', 'label' => AcademicTerminology::phaseLabel('completed'), 'color' => 'success', 'hex' => '#16a34a'],
        ])->map(function (array $item) use ($counts): array {
            $item['count'] = (int) ($counts[$item['phase']] ?? 0);

            return $item;
        });
    }

    public function activityTrend(?User $user, int $months = 6): array
    {
        $currentMonths = collect(range($months - 1, 0))
            ->map(fn (int $offset) => now()->copy()->startOfMonth()->subMonths($offset));

        $current = $currentMonths
            ->map(fn ($month): int => $this->projectEventCountForPeriod($user, $month->copy()->startOfMonth(), $month->copy()->endOfMonth()))
            ->all();

        $previous = $currentMonths
            ->map(fn ($month): int => $this->projectEventCountForPeriod($user, $month->copy()->subMonths($months)->startOfMonth(), $month->copy()->subMonths($months)->endOfMonth()))
            ->all();

        return [
            'labels' => $currentMonths->map(fn ($month): string => WitaDateTime::format($month, 'M Y', false))->all(),
            'current' => $current,
            'previous' => $previous,
            'currentTotal' => array_sum($current),
            'previousTotal' => array_sum($previous),
        ];
    }

    private function projectsQuery(?User $user): Builder
    {
        $query = ThesisProject::query();
        $this->scopeProjectsByUser($query, $user);

        return $query;
    }

    private function scopeProjectsByUser(Builder $query, ?User $user): void
    {
        if ($user?->adminProgramStudiId() !== null) {
            $query->where('program_studi_id', $user->adminProgramStudiId());
        }
    }

    private function projectEventCountForPeriod(?User $user, $start, $end): int
    {
        $query = ThesisProjectEvent::query()
            ->whereBetween('occurred_at', [$start, $end]);

        if ($user?->adminProgramStudiId() !== null) {
            $query->whereHas('project', function (Builder $projectQuery) use ($user): void {
                $projectQuery->where('program_studi_id', $user->adminProgramStudiId());
            });
        }

        return $query->count();
    }
}
