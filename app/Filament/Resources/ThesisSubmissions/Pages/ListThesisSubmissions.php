<?php

namespace App\Filament\Resources\ThesisSubmissions\Pages;

use App\Enums\ThesisSubmissionStatus;
use App\Filament\Resources\ThesisSubmissions\ThesisSubmissionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListThesisSubmissions extends ListRecords
{
    protected static string $resource = ThesisSubmissionResource::class;

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
            'menunggu_review' => Tab::make('Menunggu Review')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->where('status', ThesisSubmissionStatus::MenungguPersetujuan->value))
                ->icon('heroicon-m-clock'),
            'sempro_dijadwalkan' => Tab::make('Sempro Dijadwalkan')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->where('status', ThesisSubmissionStatus::SemproDijadwalkan->value))
                ->icon('heroicon-m-calendar'),
            'sempro_selesai' => Tab::make('Sempro Selesai')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->where('status', ThesisSubmissionStatus::SemproSelesai->value))
                ->icon('heroicon-m-check-circle'),
            'pembimbing_ditetapkan' => Tab::make('Pembimbing Ditetapkan')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->where('status', ThesisSubmissionStatus::PembimbingDitetapkan->value))
                ->icon('heroicon-m-user-group'),
        ];
    }
}
