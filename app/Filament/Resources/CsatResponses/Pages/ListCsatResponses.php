<?php

namespace App\Filament\Resources\CsatResponses\Pages;

use App\Filament\Resources\CsatResponses\CsatResponseResource;
use App\Filament\Widgets\AdminCsatScoreCountChart;
use App\Filament\Widgets\AdminCsatScoreDistributionChart;
use App\Filament\Widgets\AdminCsatScoreSummary;
use App\Models\CsatResponse;
use App\Support\CsatPeriod;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCsatResponses extends ListRecords
{
    protected static string $resource = CsatResponseResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua Respons'),
            'low-score' => Tab::make('Skor Rendah')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query
                    ->where('score', '<=', CsatResponse::LOW_SCORE_THRESHOLD)),
            'mahasiswa' => Tab::make('Mahasiswa')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query
                    ->where('respondent_role', 'mahasiswa')),
            'dosen' => Tab::make('Dosen')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query
                    ->where('respondent_role', 'dosen')),
        ];
    }

    protected function getHeaderActions(): array
    {
        $preset = $this->getChartPreset();

        return [
            ActionGroup::make(
                collect(CsatPeriod::options())
                    ->map(fn(string $label, string $itemPreset): Action => Action::make("preset-{$itemPreset}")
                        ->label($label)
                        ->icon($preset === $itemPreset ? 'heroicon-m-check' : 'heroicon-m-calendar-days')
                        ->url(request()->fullUrlWithQuery(['preset' => $itemPreset])))
                    ->all(),
            )
                ->label('Periode: '.CsatPeriod::label($preset))
                ->icon('heroicon-m-calendar-days')
                ->button()
                ->color('gray'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        $preset = $this->getChartPreset();

        return [
            AdminCsatScoreSummary::make([
                'preset' => $preset,
            ]),
            AdminCsatScoreCountChart::make([
                'preset' => $preset,
            ]),
            AdminCsatScoreDistributionChart::make([
                'preset' => $preset,
            ]),
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return [
            'md' => 1,
            'xl' => 12,
        ];
    }

    private function getChartPreset(): string
    {
        return CsatPeriod::sanitize(request()->query('preset'));
    }
}
