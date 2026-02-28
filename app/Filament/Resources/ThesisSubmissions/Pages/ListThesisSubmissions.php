<?php

namespace App\Filament\Resources\ThesisSubmissions\Pages;

use App\Filament\Resources\ThesisSubmissions\ThesisSubmissionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListThesisSubmissions extends ListRecords
{
    protected static string $resource = ThesisSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
