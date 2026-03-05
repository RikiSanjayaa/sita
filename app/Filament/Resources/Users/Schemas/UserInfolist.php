<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\AppRole;
use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Account')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('email')
                            ->label('Email address'),
                        TextEntry::make('role')
                            ->label('Role')
                            ->badge()
                            ->state(fn(?User $record): string => $record?->roles->pluck('name')->first() ?? '-'),
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->dateTime(),
                    ]),
                Section::make('Mahasiswa Profile')
                    ->visible(fn(?User $record): bool => $record?->hasRole(AppRole::Mahasiswa) ?? false)
                    ->schema([
                        TextEntry::make('mahasiswaProfile.nim')
                            ->label('NIM')
                            ->placeholder('-'),
                        TextEntry::make('mahasiswaProfile.programStudi.name')
                            ->label('Prodi')
                            ->placeholder('-'),
                        TextEntry::make('mahasiswaProfile.angkatan')
                            ->placeholder('-'),
                        TextEntry::make('mahasiswaProfile.is_active')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn(?bool $state): string => $state ? 'active' : 'inactive'),
                    ]),
                Section::make('Dosen Profile')
                    ->visible(fn(?User $record): bool => $record?->hasRole(AppRole::Dosen) ?? false)
                    ->schema([
                        TextEntry::make('dosenProfile.nik')
                            ->label('NIK')
                            ->placeholder('-'),
                        TextEntry::make('dosenProfile.programStudi.name')
                            ->label('Prodi')
                            ->placeholder('-'),
                        TextEntry::make('dosenProfile.is_active')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn(?bool $state): string => $state ? 'active' : 'inactive'),
                    ]),
            ]);
    }
}
