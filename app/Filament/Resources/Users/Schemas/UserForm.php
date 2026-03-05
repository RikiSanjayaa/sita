<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\AppRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

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
                                $user = auth()->user();
                                $allRoles = AppRole::uiValues();

                                if ($user?->hasRole(AppRole::SuperAdmin)) {
                                    return array_combine($allRoles, $allRoles);
                                }

                                // Non-super admins cannot create admins or super admins
                                $restrictedRoles = array_filter($allRoles, fn(string $role) => !in_array($role, [AppRole::Admin->value, AppRole::SuperAdmin->value], true));

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
                            ->relationship('mahasiswaProfile.programStudi', 'name')
                            ->getOptionLabelFromRecordUsing(fn($record) => $record?->name)
                            ->options(\App\Models\ProgramStudi::all()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(fn(Get $get): bool => in_array($get('role'), [AppRole::Mahasiswa->value, AppRole::Dosen->value, AppRole::Admin->value], true))
                            ->visible(fn(Get $get): bool => in_array($get('role'), [AppRole::Mahasiswa->value, AppRole::Dosen->value, AppRole::Admin->value], true)),
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
                        Toggle::make('is_active')
                            ->default(true)
                            ->required(),
                    ]),
            ]);
    }
}
