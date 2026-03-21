<?php

namespace App\Filament\Resources\SystemAnnouncements\Pages;

use App\Filament\Resources\SystemAnnouncements\SystemAnnouncementResource;
use App\Models\SystemAnnouncement;
use App\Models\User;
use App\Services\SystemAnnouncementService;
use App\Services\SystemAuditLogService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateSystemAnnouncement extends CreateRecord
{
    protected static string $resource = SystemAnnouncementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var User|null $user */
        $user = Auth::user();

        return [
            ...$data,
            'program_studi_id' => $user?->adminProgramStudiId() ?? $data['program_studi_id'] ?? null,
            'published_at' => ($data['status'] ?? null) === SystemAnnouncement::STATUS_PUBLISHED ? now() : null,
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ];
    }

    protected function afterCreate(): void
    {
        /** @var SystemAnnouncement $record */
        $record = $this->getRecord();

        $sentCount = 0;

        if ($record->isPublished() && $record->notified_at === null) {
            $sentCount = app(SystemAnnouncementService::class)->publish($record);
        }

        app(SystemAuditLogService::class)->record(
            user: Auth::user(),
            eventType: 'system_announcement_created',
            label: 'Pengumuman sistem dibuat',
            description: 'Pengumuman sistem baru berhasil dibuat.',
            request: request(),
            payload: [
                'system_announcement_id' => $record->id,
                'title' => $record->title,
                'status' => $record->status,
                'sent_count' => $sentCount,
            ],
        );
    }

    protected function getRedirectUrl(): string
    {
        return SystemAnnouncementResource::getUrl('index');
    }
}
