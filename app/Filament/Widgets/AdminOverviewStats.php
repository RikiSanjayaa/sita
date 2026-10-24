<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\AdminDashboardService;
use App\Support\AcademicTerminology;
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

        $terms = AcademicTerminology::neutral();

        return [
            Stat::make('Gap '.$terms['proposalExamShort'].' Fase Awal', $this->formatPercentage($metrics['needsSempro'], $metrics['earlyStageProjects']))
                ->description("{$metrics['needsSempro']} dari {$metrics['earlyStageProjects']} {$terms['finalWorkLower']} fase awal belum punya agenda {$terms['proposalExamShort']}")
                ->descriptionIcon('heroicon-m-clock', IconPosition::Before)
                ->color('warning'),
            Stat::make('Gap '.$terms['finalExam'].' Fase Lanjut', $this->formatPercentage($metrics['needsSidang'], $metrics['lateStageProjects']))
                ->description("{$metrics['needsSidang']} dari {$metrics['lateStageProjects']} {$terms['finalWorkLower']} fase lanjut belum punya agenda {$terms['finalExam']}")
                ->descriptionIcon('heroicon-m-academic-cap', IconPosition::Before)
                ->color('primary'),
            Stat::make('Revisi Lewat Batas', $metrics['overdueRevisionProjects'].' '.$terms['finalWorkLower'])
                ->description("{$metrics['overdueRevisionProjects']} {$terms['finalWorkLower']} aktif revisinya lewat batas")
                ->descriptionIcon('heroicon-m-exclamation-triangle', IconPosition::Before)
                ->color('danger'),
            Stat::make('Belum Ada Pembimbing', $metrics['unassignedLateStageProjects'].' '.$terms['finalWorkLower'])
                ->description("{$metrics['unassignedLateStageProjects']} {$terms['finalWorkLower']} fase lanjut belum diberi pembimbing")
                ->descriptionIcon('heroicon-m-user-group', IconPosition::Before)
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
