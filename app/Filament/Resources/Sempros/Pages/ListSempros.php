<?php

namespace App\Filament\Resources\Sempros\Pages;

use App\Enums\SemproStatus;
use App\Filament\Resources\Sempros\SemproResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSempros extends ListRecords
{
    protected static string $resource = SemproResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua'),
            'draft' => Tab::make('Draft')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->where('status', SemproStatus::Draft->value))
                ->icon('heroicon-m-pencil-square'),
            'dijadwalkan' => Tab::make('Dijadwalkan')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->where('status', SemproStatus::Scheduled->value))
                ->icon('heroicon-m-calendar'),
            'revisi' => Tab::make('Revisi')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->where('status', SemproStatus::RevisionOpen->value))
                ->icon('heroicon-m-arrow-path'),
            'selesai' => Tab::make('Selesai')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->where('status', SemproStatus::Approved->value))
                ->icon('heroicon-m-check-circle'),
        ];
    }
}
