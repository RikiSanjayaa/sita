<?php

namespace App\Filament\Widgets;

use App\Models\CsatResponse;
use App\Models\User;
use App\Support\CsatPeriod;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class AdminCsatScoreSummary extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = null;

    protected ?string $description = null;

    public string $preset = CsatPeriod::THIS_MONTH;

    public function mount(): void
    {
        $this->preset = CsatPeriod::sanitize($this->preset);
    }

    protected function getStats(): array
    {
        /** @var User|null $user */
        $user = Auth::user();
        $range = CsatPeriod::range($this->preset);
        $responses = CsatResponse::query()
            ->visibleToAdmin($user)
            ->whereBetween('created_at', [$range['start'], $range['end']])
            ->get(['score']);

        $totalResponses = $responses->count();
        $satisfiedResponses = $responses->whereIn('score', [4, 5])->count();
        $positiveResponses = $responses->whereIn('score', [3, 4, 5])->count();
        $unsatisfiedResponses = $responses->whereIn('score', [1, 2])->count();
        $csatScore = $totalResponses > 0
            ? round(($satisfiedResponses / $totalResponses) * 100, 1)
            : 0.0;

        return [
            Stat::make('Skor CSAT', number_format($csatScore, 1).'%')
                ->description('Respons puas (4-5) / total respons')
                ->descriptionIcon('heroicon-m-face-smile', IconPosition::Before)
                ->color($this->scoreColor($csatScore)),
            Stat::make('Netral ke Atas', (string) $positiveResponses)
                ->description('Respons netral ke atas: 3-5')
                ->descriptionIcon('heroicon-m-hand-thumb-up', IconPosition::Before)
                ->color('success'),
            Stat::make('Kurang Puas', (string) $unsatisfiedResponses)
                ->description('Respons kurang puas: 1-2')
                ->descriptionIcon('heroicon-m-hand-thumb-down', IconPosition::Before)
                ->color($unsatisfiedResponses > 0 ? 'danger' : 'gray'),
        ];
    }

    private function scoreColor(float $score): string
    {
        return match (true) {
            $score >= 80 => 'success',
            $score >= 70 => 'info',
            $score >= 50 => 'warning',
            default => 'danger',
        };
    }
}
