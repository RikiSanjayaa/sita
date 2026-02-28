<?php

namespace App\Filament\Resources\Sempros\Pages;

use App\Filament\Resources\Sempros\SemproResource;
use App\Services\SemproWorkflowService;
use Filament\Resources\Pages\CreateRecord;

class CreateSempro extends CreateRecord
{
    protected static string $resource = SemproResource::class;

    protected array $examinerUserIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->examinerUserIds = collect($data['examiner_user_ids'] ?? [])
            ->pluck('user_id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        unset($data['examiner_user_ids']);

        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $userId = auth()->id();

        if ($userId === null) {
            return;
        }

        $workflow = app(SemproWorkflowService::class);

        $workflow->assignExaminers($this->record, $this->examinerUserIds, $userId);

        if ($this->record->scheduled_for !== null) {
            $workflow->scheduleSempro($this->record);
        }
    }
}
