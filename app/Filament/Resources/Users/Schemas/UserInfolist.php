<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\IconEntry;
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
                        TextEntry::make('last_active_role')
                            ->badge(),
                        IconEntry::make('browser_notifications_enabled')
                            ->boolean(),
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->dateTime(),
                    ]),
                Section::make('Mahasiswa Profile')
                    ->schema([
                        TextEntry::make('mahasiswaProfile.nim')
                            ->label('NIM')
                            ->placeholder('-'),
                        TextEntry::make('mahasiswaProfile.program_studi')
                            ->label('Program Studi (Prodi)')
                            ->placeholder('-'),
                        TextEntry::make('mahasiswaProfile.angkatan')
                            ->placeholder('-'),
                        TextEntry::make('mahasiswaProfile.status_akademik')
                            ->placeholder('-'),
                    ]),
                Section::make('Dosen Profile')
                    ->schema([
                        TextEntry::make('dosenProfile.nidn')
                            ->label('NIDN')
                            ->placeholder('-'),
                        TextEntry::make('dosenProfile.homebase')
                            ->label('Homebase / Prodi')
                            ->placeholder('-'),
                        IconEntry::make('dosenProfile.is_active')
                            ->boolean(),
                    ]),
            ]);
    }
}
