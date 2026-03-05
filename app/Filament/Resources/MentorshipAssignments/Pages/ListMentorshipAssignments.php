<?php

namespace App\Filament\Resources\MentorshipAssignments\Pages;

use App\Enums\AssignmentStatus;
use App\Filament\Resources\MentorshipAssignments\MentorshipAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMentorshipAssignments extends ListRecords
{
    protected static string $resource = MentorshipAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Pembimbingan'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua'),
            'aktif' => Tab::make('Aktif')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->where('status', AssignmentStatus::Active->value))
                ->icon('heroicon-m-check-circle'),
            'selesai' => Tab::make('Selesai')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->where('status', AssignmentStatus::Ended->value))
                ->icon('heroicon-m-archive-box'),
        ];
    }
}
