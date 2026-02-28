<?php

namespace App\Filament\Imports;

use App\Enums\AppRole;
use App\Models\User;
use App\Services\UserProvisioningService;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class UserImporter extends Importer
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('email')
                ->requiredMapping()
                ->rules(['required', 'email', 'max:255']),
            ImportColumn::make('role')
                ->requiredMapping()
                ->fillRecordUsing(fn (): null => null)
                ->rules(['required', 'in:mahasiswa,dosen,admin']),
            ImportColumn::make('password')
                ->guess(['password', 'initial_password'])
                ->sensitive()
                ->ignoreBlankState()
                ->fillRecordUsing(function (User $record, ?string $state): void {
                    if (filled($state)) {
                        data_set($record, 'password', $state);
                    }
                }),
            ImportColumn::make('nim')
                ->fillRecordUsing(fn (): null => null)
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('program_studi')
                ->label('Program Studi (Prodi)')
                ->guess(['program_studi', 'prodi'])
                ->fillRecordUsing(fn (): null => null)
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('angkatan')
                ->integer()
                ->fillRecordUsing(fn (): null => null)
                ->rules(['nullable', 'integer', 'between:1990,2100']),
            ImportColumn::make('status_akademik')
                ->fillRecordUsing(fn (): null => null)
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('nidn')
                ->fillRecordUsing(fn (): null => null)
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('homebase')
                ->guess(['homebase', 'prodi'])
                ->fillRecordUsing(fn (): null => null)
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('is_active')
                ->boolean()
                ->fillRecordUsing(fn (): null => null)
                ->rules(['nullable', 'boolean']),
            ImportColumn::make('browser_notifications_enabled')
                ->boolean()
                ->rules(['nullable', 'boolean']),
        ];
    }

    public function resolveRecord(): User
    {
        return User::query()->firstOrNew([
            'email' => (string) ($this->data['email'] ?? ''),
        ]);
    }

    protected function beforeSave(): void
    {
        $role = AppRole::tryFrom((string) ($this->data['role'] ?? ''))?->value;

        if ($role !== null) {
            data_set($this->record, 'last_active_role', $role);
        }

        if (array_key_exists('browser_notifications_enabled', $this->data)) {
            data_set($this->record, 'browser_notifications_enabled', (bool) $this->data['browser_notifications_enabled']);
        }

        if (! $this->record->exists && blank($this->data['password'] ?? null)) {
            data_set($this->record, 'password', $this->resolveDefaultPassword());
        }
    }

    protected function afterSave(): void
    {
        app(UserProvisioningService::class)->syncRoleAndProfiles(
            $this->record,
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

    private function resolveDefaultPassword(): string
    {
        $nim = trim((string) ($this->data['nim'] ?? ''));
        if ($nim !== '') {
            return $nim;
        }

        $nidn = trim((string) ($this->data['nidn'] ?? ''));
        if ($nidn !== '') {
            return $nidn;
        }

        $email = trim((string) ($this->data['email'] ?? ''));
        $username = Str::before($email, '@');
        if ($username !== '') {
            return $username.'123';
        }

        return 'password123';
    }
}
