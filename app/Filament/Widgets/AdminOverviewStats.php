<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\AdminDashboardService;
use App\Support\AcademicTerminology;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class AdminOverviewStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.admin-overview-stats';

    protected int|array|null $columns = ['default' => 2, 'md' => 4];

    protected function getStats(): array
    {
        /** @var User|null $user */
        $user = Auth::user();
        $service = app(AdminDashboardService::class);
        $metrics = $service->metrics($user);

        $terms = AcademicTerminology::neutral();

        return [
            Stat::make('Gap '.$terms['proposalExamShort'].' fase awal', $this->formatPercentage($metrics['needsSempro'], $metrics['earlyStageProjects']))
                ->description("{$metrics['needsSempro']} dari {$metrics['earlyStageProjects']} {$terms['finalWorkLower']} fase awal belum punya agenda {$terms['proposalExamShort']}")
                ->icon('heroicon-m-clock')
                ->extraAttributes(['class' => 'sita-admin-stat', 'data-tone' => 'warning'])
                ->color('warning'),
            Stat::make('Gap '.$terms['finalExam'].' fase lanjut', $this->formatPercentage($metrics['needsSidang'], $metrics['lateStageProjects']))
                ->description("{$metrics['needsSidang']} dari {$metrics['lateStageProjects']} {$terms['finalWorkLower']} fase lanjut belum punya agenda {$terms['finalExam']}")
                ->icon('heroicon-m-academic-cap')
                ->extraAttributes(['class' => 'sita-admin-stat', 'data-tone' => 'primary'])
                ->color('primary'),
            Stat::make('Revisi lewat batas', $metrics['overdueRevisionProjects'])
                ->description("{$metrics['overdueRevisionProjects']} {$terms['finalWorkLower']} aktif revisinya lewat batas")
                ->icon('heroicon-m-exclamation-triangle')
                ->extraAttributes(['class' => 'sita-admin-stat', 'data-tone' => 'danger'])
                ->color('danger'),
            Stat::make('Belum ada pembimbing', $metrics['unassignedLateStageProjects'])
                ->description("{$metrics['unassignedLateStageProjects']} {$terms['finalWorkLower']} fase lanjut belum diberi pembimbing")
                ->icon('heroicon-m-user-group')
                ->extraAttributes(['class' => 'sita-admin-stat', 'data-tone' => 'info'])
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
