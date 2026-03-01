<?php

namespace App\Filament\Imports;

use App\Enums\AppRole;
use App\Models\User;
use App\Services\UserProvisioningService;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Select;
use Illuminate\Support\Number;

class UserImporter extends Importer
{
    protected static ?string $model = User::class;

    public static function getOptionsFormComponents(): array
    {
        return [
            Select::make('import_type')
                ->label('Import Type')
                ->options([
                    AppRole::Mahasiswa->value => 'Mahasiswa',
                    AppRole::Dosen->value => 'Dosen',
                    AppRole::Admin->value => 'Admin',
                ])
                ->required()
                ->native(false),
        ];
    }

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->exampleHeader('name')
                ->examples(['Muhammad Akbar'])
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('email')
                ->exampleHeader('email')
                ->examples(['mahasiswa@sita.test'])
                ->requiredMapping()
                ->rules(['required', 'email', 'max:255']),
            ImportColumn::make('role')
                ->exampleHeader('role')
                ->examples(['mahasiswa'])
                ->guess(['role'])
                ->fillRecordUsing(fn(): null => null)
                ->rules(['nullable', 'in:mahasiswa,dosen,admin']),
            ImportColumn::make('password')
                ->exampleHeader('password')
                ->examples([''])
                ->guess(['password', 'initial_password'])
                ->sensitive()
                ->rules(['required', 'string', 'min:8'])
                ->fillRecordUsing(function (User $record, ?string $state): void {
                    if (filled($state)) {
                        data_set($record, 'password', $state);
                    }
                }),
            ImportColumn::make('nim')
                ->exampleHeader('nim')
                ->examples(['2210510001'])
                ->guess(['nim'])
                ->fillRecordUsing(fn(): null => null)
                ->rules(['nullable', 'required_if:role,mahasiswa', 'string', 'max:255']),
            ImportColumn::make('prodi')
                ->exampleHeader('prodi')
                ->examples(['Informatika'])
                ->guess(['prodi', 'program_studi', 'homebase'])
                ->fillRecordUsing(fn(): null => null)
                ->rules(['nullable', 'required_if:role,mahasiswa,dosen', 'string', 'max:255']),
            ImportColumn::make('angkatan')
                ->exampleHeader('angkatan')
                ->examples(['2022'])
                ->guess(['angkatan'])
                ->integer()
                ->fillRecordUsing(fn(): null => null)
                ->rules(['nullable', 'required_if:role,mahasiswa', 'integer', 'between:1990,2100']),

            ImportColumn::make('nik')
                ->exampleHeader('nik')
                ->examples(['7301010101010001'])
                ->guess(['nik', 'no_ktp', 'nidn'])
                ->fillRecordUsing(fn(): null => null)
                ->rules(['nullable', 'required_if:role,dosen', 'string', 'max:255']),

        ];
    }

    public function resolveRecord(): User
    {
        return User::query()->firstOrNew([
            'email' => (string) ($this->data['email'] ?? ''),
        ]);
    }

    protected function beforeValidate(): void
    {
        $importType = (string) ($this->options['import_type'] ?? '');
        $rowRole = (string) ($this->data['role'] ?? '');

        if ($rowRole === '' && in_array($importType, AppRole::uiValues(), true)) {
            $this->data['role'] = $importType;
        }
    }

    protected function beforeSave(): void
    {
        $role = AppRole::tryFrom((string) ($this->data['role'] ?? ''))?->value;

        if ($role !== null) {
            data_set($this->record, 'last_active_role', $role);
        }
    }

    protected function afterSave(): void
    {
        /** @var User $user */
        $user = $this->record;

        app(UserProvisioningService::class)->syncRoleAndProfiles(
            $user,
            $this->data,
        );
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your user import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
