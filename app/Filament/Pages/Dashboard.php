<?php

namespace App\Filament\Pages;

use App\Enums\AppRole;
use App\Filament\Widgets\AdminOverviewStats;
use App\Filament\Widgets\AdminWorkloadChart;
use App\Filament\Widgets\ProjectPhaseChart;
use App\Filament\Widgets\ProjectsNeedingAttentionWidget;
use App\Filament\Widgets\RecentProjectEventsWidget;
use App\Models\User;
use Filament\Facades\Filament;
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
        return 'Dashboard Admin';
    }

    public function getHeading(): string
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            return 'Dashboard Admin';
        }

        $user->loadMissing(['roles', 'adminProfile.programStudi']);

        $roleLabel = 'Admin';

        if ($user->hasRole(AppRole::SuperAdmin)) {
            $roleLabel = 'Super Admin';
        } elseif (filled($user->adminProfile?->programStudi?->name)) {
            $roleLabel = 'Admin '.$user->adminProfile->programStudi->name;
        }

        return sprintf('Selamat datang, %s (%s)', $user->name, $roleLabel);
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
