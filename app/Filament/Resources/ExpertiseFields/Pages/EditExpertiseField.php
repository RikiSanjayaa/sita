<?php

namespace App\Filament\Resources\ExpertiseFields\Pages;

use App\Filament\Resources\ExpertiseFields\ExpertiseFieldResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExpertiseField extends EditRecord
{
    protected static string $resource = ExpertiseFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn(): bool => ExpertiseFieldResource::canDelete($this->getRecord())),
        ];
    }
}
