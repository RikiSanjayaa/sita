<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\AppRole;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Account')
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
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->maxLength(255),
                        Select::make('role')
                            ->label('Role')
                            ->options(array_combine(AppRole::uiValues(), AppRole::uiValues()))
                            ->required(),
                        Toggle::make('browser_notifications_enabled')
                            ->default(true)
                            ->required(),
                    ]),
                Section::make('Mahasiswa Profile')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nim')
                            ->label('NIM')
                            ->maxLength(255),
                        TextInput::make('program_studi')
                            ->label('Program Studi (Prodi)')
                            ->maxLength(255),
                        TextInput::make('angkatan')
                            ->numeric()
                            ->minValue(1990)
                            ->maxValue(2100),
                        TextInput::make('status_akademik')
                            ->maxLength(255),
                    ]),
                Section::make('Dosen Profile')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nidn')
                            ->label('NIDN')
                            ->maxLength(255),
                        TextInput::make('homebase')
                            ->label('Homebase / Prodi')
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->default(true)
                            ->required(),
                    ]),
            ]);
    }
}
