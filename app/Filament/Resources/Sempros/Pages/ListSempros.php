<?php

namespace App\Filament\Resources\Sempros\Pages;

use App\Filament\Resources\Sempros\SemproResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSempros extends ListRecords
{
    protected static string $resource = SemproResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
