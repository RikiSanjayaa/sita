<?php

namespace App\Filament\Resources\ProgramStudis\Pages;

use App\Filament\Resources\ProgramStudis\ProgramStudiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProgramStudis extends ListRecords
{
    protected static string $resource = ProgramStudiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
