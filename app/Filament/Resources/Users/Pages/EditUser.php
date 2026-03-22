<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\AppRole;
use App\Filament\Resources\Users\Actions\SendPasswordResetLinkAction;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\UserProvisioningService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected array $provisioningData = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role'] = $this->record->roles->pluck('name')->first() ?? $this->record->last_active_role ?? AppRole::Mahasiswa->value;
        $data['nim'] = $this->record->mahasiswaProfile?->nim;
        $data['prodi'] = $this->record->mahasiswaProfile?->program_studi_id
            ?? $this->record->dosenProfile?->program_studi_id
            ?? $this->record->adminProfile?->program_studi_id;
        $data['concentration'] = $this->record->mahasiswaProfile?->concentration ?? $this->record->dosenProfile?->concentration;
        $data['angkatan'] = $this->record->mahasiswaProfile?->angkatan;
        $data['nik'] = $this->record->dosenProfile?->nik;
        $data['supervision_quota'] = $this->record->dosenProfile?->supervision_quota;
        $data['is_active'] = $this->record->mahasiswaProfile?->is_active ?? $this->record->dosenProfile?->is_active ?? true;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var User|null $authenticatedUser */
        $authenticatedUser = Auth::user();

        if ($authenticatedUser?->is($this->record)) {
            $data['email'] = $this->record->email;
        }

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
            SendPasswordResetLinkAction::make(fn(): \App\Models\User => $this->record),
            DeleteAction::make(),
        ];
    }
}
