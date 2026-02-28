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
        $data['prodi'] = $this->record->mahasiswaProfile?->program_studi ?? $this->record->dosenProfile?->homebase;
        $data['angkatan'] = $this->record->mahasiswaProfile?->angkatan;
        $data['nik'] = $this->record->dosenProfile?->nik;
        $data['is_active'] = $this->record->mahasiswaProfile?->is_active ?? $this->record->dosenProfile?->is_active ?? true;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->provisioningData = [
            'role' => $data['role'] ?? null,
            'nim' => $data['nim'] ?? null,
            'prodi' => $data['prodi'] ?? null,
            'angkatan' => $data['angkatan'] ?? null,
            'nik' => $data['nik'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];

        unset(
            $data['role'],
            $data['nim'],
            $data['prodi'],
            $data['angkatan'],
            $data['nik'],
            $data['is_active'],
        );

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var \App\Models\User $user */
        $user = $this->record;

        app(UserProvisioningService::class)->syncRoleAndProfiles(
            $user,
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
