<?php

namespace App\Filament\Resources\ExpertiseFields\Pages;

use App\Filament\Resources\ExpertiseFields\ExpertiseFieldResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExpertiseFields extends ListRecords
{
    protected static string $resource = ExpertiseFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
