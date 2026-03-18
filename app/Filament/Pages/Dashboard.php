<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AdminOverviewStats;
use App\Filament\Widgets\AdminWorkloadChart;
use App\Filament\Widgets\ProjectPhaseChart;
use App\Filament\Widgets\ProjectsNeedingAttentionWidget;
use App\Filament\Widgets\RecentProjectEventsWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Enums\Width;

class Dashboard extends BaseDashboard
{
    public function getColumns(): int|array
    {
        return [
            'md' => 1,
            'xl' => 12,
        ];
    }

    public function getTitle(): string
    {
        return 'Radar Operasional Admin';
    }

    public function getSubheading(): string
    {
        return 'Pantau antrian kerja, agenda mendatang, dan aktivitas terbaru tanpa perlu membuka banyak halaman terlebih dahulu.';
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getWidgets(): array
    {
        return [
            AdminOverviewStats::class,
            AdminWorkloadChart::class,
            ProjectPhaseChart::class,
            ProjectsNeedingAttentionWidget::class,
            RecentProjectEventsWidget::class,
        ];
    }
}
