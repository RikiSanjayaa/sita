<?php

namespace App\Filament\Resources\ThesisSubmissions\Pages;

use App\Filament\Resources\ThesisSubmissions\ThesisSubmissionResource;
use App\Services\LegacyThesisProjectBackfillService;
use Filament\Resources\Pages\CreateRecord;

class CreateThesisSubmission extends CreateRecord
{
    protected static string $resource = ThesisSubmissionResource::class;

    protected function afterCreate(): void
    {
        app(LegacyThesisProjectBackfillService::class)->backfill($this->record->student_user_id);
    }
}
