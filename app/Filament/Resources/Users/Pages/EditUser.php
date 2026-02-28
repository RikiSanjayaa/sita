<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\AppRole;
use App\Filament\Resources\Users\UserResource;
use App\Services\UserProvisioningService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected array $provisioningData = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role'] = $this->record->roles->pluck('name')->first() ?? $this->record->last_active_role ?? AppRole::Mahasiswa->value;
        $data['nim'] = $this->record->mahasiswaProfile?->nim;
        $data['program_studi'] = $this->record->mahasiswaProfile?->program_studi;
        $data['angkatan'] = $this->record->mahasiswaProfile?->angkatan;
        $data['status_akademik'] = $this->record->mahasiswaProfile?->status_akademik;
        $data['nidn'] = $this->record->dosenProfile?->nidn;
        $data['homebase'] = $this->record->dosenProfile?->homebase;
        $data['is_active'] = $this->record->dosenProfile?->is_active ?? true;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->provisioningData = [
            'role' => $data['role'] ?? null,
            'nim' => $data['nim'] ?? null,
            'program_studi' => $data['program_studi'] ?? null,
            'angkatan' => $data['angkatan'] ?? null,
            'status_akademik' => $data['status_akademik'] ?? null,
            'nidn' => $data['nidn'] ?? null,
            'homebase' => $data['homebase'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];

        unset(
            $data['role'],
            $data['nim'],
            $data['program_studi'],
            $data['angkatan'],
            $data['status_akademik'],
            $data['nidn'],
            $data['homebase'],
            $data['is_active'],
        );

        return $data;
    }

    protected function afterSave(): void
    {
        app(UserProvisioningService::class)->syncRoleAndProfiles(
            $this->record,
            $this->provisioningData,
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
