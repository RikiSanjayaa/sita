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
                        TextEntry::make('mahasiswaProfile.concentration')
                            ->label('Konsentrasi')
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
                        TextEntry::make('dosenProfile.concentration')
                            ->label('Konsentrasi')
                            ->placeholder('-'),
                        TextEntry::make('dosenProfile.supervision_quota')
                            ->label('Kuota Bimbingan')
                            ->placeholder('-'),
                        TextEntry::make('dosenProfile.is_active')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn(?bool $state): string => $state ? 'active' : 'inactive'),
                    ]),
                Section::make('Beban Tugas Akhir')
                    ->visible(fn(?User $record): bool => $record?->hasRole(AppRole::Dosen) ?? false)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('active_primary_supervision_count')
                            ->label('Pembimbing 1 Aktif')
                            ->placeholder('0'),
                        TextEntry::make('active_secondary_supervision_count')
                            ->label('Pembimbing 2 Aktif')
                            ->placeholder('0'),
                        TextEntry::make('scheduled_sempro_examiner_count')
                            ->label('Sempro Terjadwal')
                            ->placeholder('0'),
                        TextEntry::make('scheduled_sidang_examiner_count')
                            ->label('Sidang Terjadwal')
                            ->placeholder('0'),
                        TextEntry::make('thesis_load_note')
                            ->label('Catatan')
                            ->state('Kuota pembimbing dapat diatur oleh superadmin. Penetapan pembimbing hanya dapat dilakukan jika konsentrasi dosen dan mahasiswa sama.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
