<?php

namespace App\Filament\Resources\ThesisProjects\Pages;

use App\Filament\Resources\ThesisProjects\ThesisProjectResource;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditThesisProject extends EditRecord
{
    protected static string $resource = ThesisProjectResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var ThesisProject $record */
        $record = $this->record;

        $data['student_name'] = $record->student?->name ?? '-';
        $data['student_nim'] = $record->student?->mahasiswaProfile?->nim ?? '-';
        $data['program_studi_name'] = $record->programStudi?->name ?? '-';
        $data['active_title'] = $this->resolveCurrentTitle($record)?->title_id ?? '-';

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset(
            $data['student_name'],
            $data['student_nim'],
            $data['program_studi_name'],
            $data['active_title'],
        );

        if (($data['state'] ?? null) === 'completed') {
            $data['phase'] = 'completed';
            $data['cancelled_at'] = null;
        }

        if (($data['state'] ?? null) === 'cancelled') {
            $data['phase'] = 'cancelled';
            $data['completed_at'] = null;
        }

        if (in_array($data['state'] ?? null, ['active', 'on_hold'], true)) {
            $data['completed_at'] = null;
            $data['cancelled_at'] = null;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return ThesisProjectResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Proyek tugas akhir berhasil diperbarui';
    }

    private function resolveCurrentTitle(ThesisProject $record): ?ThesisProjectTitle
    {
        $approved = $record->titles
            ->where('status', 'approved')
            ->sortByDesc('version_no')
            ->first();

        if ($approved instanceof ThesisProjectTitle) {
            return $approved;
        }

        return $record->latestTitle;
    }
}
