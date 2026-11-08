<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\UserProvisioningService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateUser extends CreateRecord
{
    protected ?bool $hasDatabaseTransactions = true;

    protected static string $resource = UserResource::class;

    protected array $provisioningData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->provisioningData = [
            'role' => $data['role'] ?? null,
            'nim' => $data['nim'] ?? null,
            'prodi' => $data['prodi'] ?? null,
            'degree_level' => $data['degree_level'] ?? null,
            'concentration' => $data['concentration'] ?? null,
            'expertise_field_ids' => $data['expertise_field_ids'] ?? [],
            'academic_assignments' => $data['academic_assignments'] ?? [],
            'angkatan' => $data['angkatan'] ?? null,
            'nik' => $data['nik'] ?? null,
            'sidang_nidn' => $data['sidang_nidn'] ?? null,
            'supervision_quota' => $data['supervision_quota'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'is_obe_curriculum' => (bool) ($data['is_obe_curriculum'] ?? false),
        ];

        unset(
            $data['role'],
            $data['nim'],
            $data['prodi'],
            $data['degree_level'],
            $data['concentration'],
            $data['expertise_field_ids'],
            $data['academic_assignments'],
            $data['angkatan'],
            $data['nik'],
            $data['sidang_nidn'],
            $data['supervision_quota'],
            $data['is_active'],
            $data['is_obe_curriculum'],
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var \App\Models\User $user */
        $user = $this->record;

        try {
            app(UserProvisioningService::class)->syncRoleAndProfiles($user, $this->provisioningData);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $field): array => ['data.'.$field => $messages])->all());
        }
    }
}
