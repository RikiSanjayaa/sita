<?php

namespace App\Filament\Widgets;

use App\Models\CsatResponse;
use App\Models\User;
use App\Support\CsatPeriod;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class AdminCsatScoreCountChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = [
        'xl' => 6,
    ];

    protected ?string $heading = 'Jumlah Respons per Skor';

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

        $data = [];

        foreach (range(1, 5) as $score) {
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
                'borderRadius' => 8,
                // 'barThickness' => 26,
            ]],
            'labels' => ['1/5', '2/5', '3/5', '4/5', '5/5'],
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

        if ($totalResponses === 0) {
            return sprintf('Belum ada respons pada periode %s.', mb_strtolower(CsatPeriod::label($this->preset)));
        }

        return sprintf(
            'Jumlah respons mentah untuk tiap skor pada periode %s.',
            mb_strtolower(CsatPeriod::label($this->preset)),
        );
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
                'y' => [
                    'grid' => [
                        'display' => false,
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
