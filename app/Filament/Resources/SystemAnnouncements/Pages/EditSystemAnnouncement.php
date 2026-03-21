<?php

namespace App\Filament\Resources\SystemAnnouncements\Pages;

use App\Filament\Resources\SystemAnnouncements\SystemAnnouncementResource;
use App\Models\SystemAnnouncement;
use App\Models\User;
use App\Services\SystemAnnouncementService;
use App\Services\SystemAuditLogService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditSystemAnnouncement extends EditRecord
{
    protected static string $resource = SystemAnnouncementResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var User|null $user */
        $user = Auth::user();

        return [
            ...$data,
            'program_studi_id' => $user?->adminProgramStudiId() ?? $data['program_studi_id'] ?? null,
            'published_at' => ($data['status'] ?? null) === SystemAnnouncement::STATUS_PUBLISHED
                ? ($this->record->published_at ?? now())
                : null,
            'updated_by' => $user?->id,
        ];
    }

    protected function afterSave(): void
    {
        /** @var SystemAnnouncement $record */
        $record = $this->getRecord();

        $sentCount = 0;

        if ($record->isPublished() && $record->notified_at === null) {
            $sentCount = app(SystemAnnouncementService::class)->publish($record);
        }

        app(SystemAuditLogService::class)->record(
            user: Auth::user(),
            eventType: 'system_announcement_updated',
            label: 'Pengumuman sistem diperbarui',
            description: 'Pengumuman sistem berhasil diperbarui.',
            request: request(),
            payload: [
                'system_announcement_id' => $record->id,
                'title' => $record->title,
                'status' => $record->status,
                'sent_count' => $sentCount,
            ],
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return SystemAnnouncementResource::getUrl('index');
    }
}
