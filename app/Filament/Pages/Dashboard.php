<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AdminOverviewStats;
use App\Filament\Widgets\ProjectsNeedingAttentionWidget;
use App\Filament\Widgets\RecentProjectEventsWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getColumns(): int|array
    {
        return [
            'md' => 2,
            'xl' => 12,
        ];
    }

    public function getTitle(): string
    {
        return 'Dashboard Admin';
    }

    public function getWidgets(): array
    {
        return [
            AdminOverviewStats::class,
            ProjectsNeedingAttentionWidget::class,
            RecentProjectEventsWidget::class,
        ];
    }
}
