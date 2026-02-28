<?php

namespace App\Filament\Resources\ThesisSubmissions\Pages;

use App\Filament\Resources\ThesisSubmissions\ThesisSubmissionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewThesisSubmission extends ViewRecord
{
    protected static string $resource = ThesisSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
