<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\UserProvisioningService;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected array $provisioningData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->provisioningData = [
            'role' => $data['role'] ?? null,
            'nim' => $data['nim'] ?? null,
            'prodi' => $data['prodi'] ?? null,
            'concentration' => $data['concentration'] ?? null,
            'angkatan' => $data['angkatan'] ?? null,
            'nik' => $data['nik'] ?? null,
            'supervision_quota' => $data['supervision_quota'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];

        unset(
            $data['role'],
            $data['nim'],
            $data['prodi'],
            $data['concentration'],
            $data['angkatan'],
            $data['nik'],
            $data['supervision_quota'],
            $data['is_active'],
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var \App\Models\User $user */
        $user = $this->record;

        app(UserProvisioningService::class)->syncRoleAndProfiles(
            $user,
            $this->provisioningData,
        );
    }
}
