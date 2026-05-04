<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\AdminDashboardService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class ProjectPhaseChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = [
        'xl' => 6,
    ];

    protected ?string $heading = 'Sebaran Fase Proyek';

    protected ?string $maxHeight = '20rem';

    protected function getData(): array
    {
        /** @var User|null $user */
        $user = Auth::user();
        $items = app(AdminDashboardService::class)->phaseDistribution($user);

        return [
            'datasets' => [[
                'label' => 'Proyek',
                'data' => $items->pluck('count')->all(),
                'backgroundColor' => $items->pluck('hex')->all(),
                'borderWidth' => 0,
                'hoverOffset' => 10,
            ]],
            'labels' => $items->pluck('label')->all(),
        ];
    }

    public function getDescription(): ?string
    {
        /** @var User|null $user */
        $user = Auth::user();
        $items = app(AdminDashboardService::class)->phaseDistribution($user);

        return sprintf(
            '%d proyek dalam scope admin saat ini, dibagi menurut fase utama yang sedang berjalan.',
            $items->sum('count'),
        );
    }

    protected function getOptions(): array
    {
        return [
            'cutout' => '68%',
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'usePointStyle' => true,
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
