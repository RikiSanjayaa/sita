<?php

namespace App\Filament\Resources\Sempros\Pages;

use App\Filament\Resources\Sempros\SemproResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSempro extends ViewRecord
{
    protected static string $resource = SemproResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
