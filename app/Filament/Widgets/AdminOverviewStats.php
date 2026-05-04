<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\AdminDashboardService;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class AdminOverviewStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        /** @var User|null $user */
        $user = Auth::user();
        $service = app(AdminDashboardService::class);
        $metrics = $service->metrics($user);

        return [
            Stat::make('Gap Sempro Fase Awal', $this->formatPercentage($metrics['needsSempro'], $metrics['earlyStageProjects']))
                ->description("{$metrics['needsSempro']} dari {$metrics['earlyStageProjects']} proyek fase awal belum punya sempro")
                ->descriptionIcon('heroicon-m-clock', IconPosition::Before)
                ->chart($service->weeklyMetricHistory($user, 'needs_sempro'))
                ->color('warning'),
            Stat::make('Gap Sidang Fase Lanjut', $this->formatPercentage($metrics['needsSidang'], $metrics['lateStageProjects']))
                ->description("{$metrics['needsSidang']} dari {$metrics['lateStageProjects']} proyek fase lanjut belum punya sidang")
                ->descriptionIcon('heroicon-m-academic-cap', IconPosition::Before)
                ->chart($service->weeklyMetricHistory($user, 'needs_sidang'))
                ->color('primary'),
            Stat::make('Revisi Lewat Batas', $metrics['overdueRevisionProjects'].' proyek')
                ->description("{$metrics['overdueRevisionProjects']} proyek aktif revisinya lewat batas")
                ->descriptionIcon('heroicon-m-exclamation-triangle', IconPosition::Before)
                ->chart($service->weeklyMetricHistory($user, 'overdue_revision_projects'))
                ->color('danger'),
            Stat::make('Belum Ada Pembimbing', $metrics['unassignedLateStageProjects'].' proyek')
                ->description("{$metrics['unassignedLateStageProjects']} proyek fase lanjut belum diberi pembimbing")
                ->descriptionIcon('heroicon-m-user-group', IconPosition::Before)
                ->chart($service->weeklyMetricHistory($user, 'unassigned_late_stage'))
                ->color('info'),
        ];
    }

    private function formatPercentage(int $numerator, int $denominator): string
    {
        if ($denominator === 0) {
            return '0%';
        }

        return (string) round(($numerator / $denominator) * 100).'%';
    }
}
