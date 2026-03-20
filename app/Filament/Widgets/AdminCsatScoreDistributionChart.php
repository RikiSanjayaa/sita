<?php

namespace App\Filament\Widgets;

use App\Models\CsatResponse;
use App\Models\User;
use App\Support\CsatPeriod;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class AdminCsatScoreDistributionChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = [
        'xl' => 6,
    ];

    protected ?string $heading = 'Distribusi Nilai CSAT';

    protected ?string $maxHeight = '16rem';

    public string $preset = CsatPeriod::THIS_MONTH;

    public function mount(): void
    {
        $this->preset = CsatPeriod::sanitize($this->preset);
    }

    protected function getData(): array
    {
        /** @var User|null $user */
        $user = Auth::user();
        $range = CsatPeriod::range($this->preset);

        $counts = CsatResponse::query()
            ->visibleToAdmin($user)
            ->whereBetween('created_at', [$range['start'], $range['end']])
            ->selectRaw('score, COUNT(*) as aggregate')
            ->groupBy('score')
            ->pluck('aggregate', 'score');

        $labels = [];
        $data = [];

        foreach (range(1, 5) as $score) {
            $labels[] = sprintf('%d/5', $score);
            $data[] = (int) ($counts[$score] ?? 0);
        }

        return [
            'datasets' => [[
                'label' => 'Respons',
                'data' => $data,
                'backgroundColor' => [
                    '#ef4444',
                    '#f97316',
                    '#f59e0b',
                    '#10b981',
                    '#0f766e',
                ],
                'borderWidth' => 0,
                'hoverOffset' => 10,
            ]],
            'labels' => $labels,
        ];
    }

    public function getDescription(): ?string
    {
        /** @var User|null $user */
        $user = Auth::user();
        $range = CsatPeriod::range($this->preset);
        $totalResponses = CsatResponse::query()
            ->visibleToAdmin($user)
            ->whereBetween('created_at', [$range['start'], $range['end']])
            ->count();

        $windowLabel = mb_strtolower(CsatPeriod::label($this->preset));

        if ($totalResponses === 0) {
            return sprintf('Belum ada respons baru pada periode %s.', $windowLabel);
        }

        return sprintf(
            'Komposisi %d respons pada periode %s, dari skor terendah sampai tertinggi.',
            $totalResponses,
            $windowLabel,
        );
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                plugins: {
                    legend: {
                        display: true,
                        // position: 'bottom',
                        labels: {
                            usePointStyle: true,
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const label = context.label ?? '';
                                const value = Number(context.raw ?? 0);
                                const data = context.dataset?.data ?? [];
                                const total = data.reduce((sum, item) => sum + Number(item ?? 0), 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';

                                return `${label}: ${percentage}%`;
                            },
                        },
                    },
                },
            }
            JS);
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
