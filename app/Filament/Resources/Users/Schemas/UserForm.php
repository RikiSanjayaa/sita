<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\AppRole;
use App\Models\ProgramStudi;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Role')
                    ->schema([
                        Select::make('role')
                            ->label('Role')
                            ->options(function () {
                                /** @var User|null $user */
                                $user = Auth::user();
                                $allRoles = AppRole::uiValues();

                                if ($user?->hasRole(AppRole::SuperAdmin)) {
                                    return array_combine($allRoles, $allRoles);
                                }

                                // Non-super admins cannot create admins or super admins
                                $restrictedRoles = array_filter($allRoles, fn(string $role) => ! in_array($role, [AppRole::Admin->value, AppRole::SuperAdmin->value], true));

                                return array_combine($restrictedRoles, $restrictedRoles);
                            })
                            ->required()
                            ->live(),
                    ]),
                Section::make('User Account')
                    ->visible(fn(Get $get): bool => filled($get('role')))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->required(fn(string $operation): bool => $operation === 'create')
                            ->dehydrated(fn(?string $state): bool => filled($state))
                            ->maxLength(255),
                        Select::make('prodi')
                            ->label('Prodi')
                            ->options(ProgramStudi::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn(Set $set): mixed => $set('concentration', null))
                            ->required(fn(Get $get): bool => in_array($get('role'), [AppRole::Mahasiswa->value, AppRole::Dosen->value, AppRole::Admin->value], true))
                            ->visible(fn(Get $get): bool => in_array($get('role'), [AppRole::Mahasiswa->value, AppRole::Dosen->value, AppRole::Admin->value], true)),
                        Select::make('concentration')
                            ->label('Konsentrasi')
                            ->options(fn(Get $get): array => self::concentrationOptions($get))
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required(fn(Get $get): bool => in_array($get('role'), [AppRole::Mahasiswa->value, AppRole::Dosen->value], true))
                            ->visible(fn(Get $get): bool => in_array($get('role'), [AppRole::Mahasiswa->value, AppRole::Dosen->value], true))
                            ->disabled(fn(Get $get): bool => blank($get('prodi')))
                            ->helperText('Konsentrasi mengikuti daftar yang diatur pada Program Studi.'),
                    ]),
                Section::make('Mahasiswa Profile')
                    ->visible(fn(Get $get): bool => $get('role') === AppRole::Mahasiswa->value)
                    ->columns(2)
                    ->schema([
                        TextInput::make('nim')
                            ->label('NIM')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('angkatan')
                            ->numeric()
                            ->required()
                            ->minValue(1990)
                            ->maxValue(2100),
                        Toggle::make('is_active')
                            ->label('Status (Aktif/Nonaktif)')
                            ->default(true)
                            ->required(),
                    ]),
                Section::make('Dosen Profile')
                    ->visible(fn(Get $get): bool => $get('role') === AppRole::Dosen->value)
                    ->columns(2)
                    ->schema([
                        TextInput::make('nik')
                            ->label('NIK')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('supervision_quota')
                            ->label('Kuota Bimbingan')
                            ->numeric()
                            ->default(14)
                            ->minValue(1)
                            ->required(fn(): bool => self::isSuperAdminUser())
                            ->visible(fn(): bool => self::isSuperAdminUser()),
                        Toggle::make('is_active')
                            ->default(true)
                            ->required(),
                    ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function concentrationOptions(Get $get): array
    {
        $programStudiId = $get('prodi');

        if (blank($programStudiId)) {
            return [];
        }

        return ProgramStudi::query()
            ->find((int) $programStudiId)
            ?->concentrationOptions() ?? [];
    }

    private static function isSuperAdminUser(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasRole(AppRole::SuperAdmin) ?? false;
    }
}
