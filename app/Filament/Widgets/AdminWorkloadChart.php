<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\AdminDashboardService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class AdminWorkloadChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = [
        'xl' => 6,
    ];

    protected ?string $heading = 'Aktivitas Operasional 6 Bulan';

    protected ?string $maxHeight = '20rem';

    protected ?array $trend = null;

    protected function getData(): array
    {
        $trend = $this->getTrend();

        return [
            'datasets' => [
                [
                    'label' => '6 bulan terakhir',
                    'data' => $trend['current'],
                    'borderColor' => '#2563eb',
                    'backgroundColor' => 'rgba(37, 99, 235, 0.14)',
                    'fill' => true,
                    'tension' => 0.35,
                    'pointRadius' => 3,
                    'pointHoverRadius' => 5,
                    'borderWidth' => 2,
                ],
                [
                    'label' => '6 bulan sebelumnya',
                    'data' => $trend['previous'],
                    'borderColor' => '#94a3b8',
                    'backgroundColor' => 'rgba(148, 163, 184, 0)',
                    'borderDash' => [6, 6],
                    'fill' => false,
                    'tension' => 0.35,
                    'pointRadius' => 2,
                    'pointHoverRadius' => 4,
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $trend['labels'],
        ];
    }

    public function getDescription(): ?string
    {
        $trend = $this->getTrend();
        $delta = $trend['currentTotal'] - $trend['previousTotal'];

        if ($delta === 0) {
            return "{$trend['currentTotal']} aktivitas proyek, ritmenya stabil dibanding 6 bulan sebelumnya.";
        }

        $direction = $delta > 0 ? 'naik' : 'turun';

        return sprintf(
            '%d aktivitas proyek, %s %d dibanding 6 bulan sebelumnya.',
            $trend['currentTotal'],
            $direction,
            abs($delta),
        );
    }

    protected function getOptions(): array
    {
        return [
            'interaction' => [
                'intersect' => false,
                'mode' => 'index',
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'usePointStyle' => true,
                    ],
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getTrend(): array
    {
        if (is_array($this->trend)) {
            return $this->trend;
        }

        /** @var User|null $user */
        $user = Auth::user();

        return $this->trend = app(AdminDashboardService::class)->activityTrend($user);
    }
}
