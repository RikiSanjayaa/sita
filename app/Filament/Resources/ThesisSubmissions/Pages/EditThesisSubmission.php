<?php

namespace App\Filament\Resources\ThesisSubmissions\Pages;

use App\Filament\Resources\ThesisSubmissions\ThesisSubmissionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditThesisSubmission extends EditRecord
{
    protected static string $resource = ThesisSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
