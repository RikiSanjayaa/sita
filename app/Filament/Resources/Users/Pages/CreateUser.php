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

    protected function afterCreate(): void
    {
        app(UserProvisioningService::class)->syncRoleAndProfiles(
            $this->record,
            $this->provisioningData,
        );
    }
}
