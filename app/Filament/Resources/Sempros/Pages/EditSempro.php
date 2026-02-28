<?php

namespace App\Filament\Resources\Sempros\Pages;

use App\Enums\SemproStatus;
use App\Filament\Resources\Sempros\SemproResource;
use App\Services\SemproWorkflowService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSempro extends EditRecord
{
    protected static string $resource = SemproResource::class;

    protected array $examinerUserIds = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['examiner_user_ids'] = $this->record->examiners()
            ->orderBy('examiner_order')
            ->get()
            ->map(static fn ($examiner): array => [
                'user_id' => $examiner->examiner_user_id,
            ])
            ->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->examinerUserIds = collect($data['examiner_user_ids'] ?? [])
            ->pluck('user_id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        unset($data['examiner_user_ids']);

        return $data;
    }

    protected function afterSave(): void
    {
        $userId = auth()->id();

        if ($userId === null) {
            return;
        }

        $workflow = app(SemproWorkflowService::class);

        $workflow->assignExaminers($this->record, $this->examinerUserIds, $userId);

        if ($this->record->scheduled_for !== null && $this->record->status === SemproStatus::Draft->value) {
            $workflow->scheduleSempro($this->record);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
