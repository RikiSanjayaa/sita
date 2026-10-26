<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\AppRole;
use App\Models\User;
use App\Support\AcademicTerminology;
use App\Support\Filament\BadgeStyles;
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
                            ->label('Email address')
                            ->copyable(),
                        TextEntry::make('phone_number')
                            ->label('Nomor HP')
                            ->copyable()
                            ->placeholder('Belum diisi'),
                        TextEntry::make('role')
                            ->label('Role')
                            ->badge()
                            ->state(fn(?User $record): string => $record?->roles->pluck('name')->first() ?? '-')
                            ->formatStateUsing(fn(string $state): string => BadgeStyles::roleLabel($state))
                            ->color(fn(string $state): string => BadgeStyles::roleColor($state))
                            ->icon(fn(string $state): string => BadgeStyles::roleIcon($state)),
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
                            ->copyable()
                            ->placeholder('-'),
                        TextEntry::make('mahasiswaProfile.programStudi.name')
                            ->label('Prodi')
                            ->placeholder('-')
                            ->badge()
                            ->color(fn(?string $state): string => BadgeStyles::programStudiColor($state))
                            ->icon(BadgeStyles::programStudiIcon()),
                        TextEntry::make('mahasiswaProfile.degree_level')
                            ->label('Jenjang')
                            ->badge()
                            ->formatStateUsing(fn(?string $state): string => strtoupper($state ?? '-'))
                            ->placeholder('-'),
                        TextEntry::make('mahasiswaProfile.concentration')
                            ->label('Konsentrasi')
                            ->placeholder('-'),
                        TextEntry::make('mahasiswaProfile.angkatan')
                            ->placeholder('-'),
                        TextEntry::make('mahasiswaProfile.is_active')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn(?bool $state): string => BadgeStyles::activeStateLabel($state))
                            ->color(fn(?bool $state): string => BadgeStyles::activeStateColor($state))
                            ->icon(fn(?bool $state): string => BadgeStyles::activeStateIcon($state)),
                        TextEntry::make('mahasiswaProfile.is_obe_curriculum')
                            ->label('Kurikulum OBE')
                            ->badge()
                            ->formatStateUsing(fn(?bool $state): string => $state ? 'OBE' : 'Non-OBE')
                            ->color(fn(?bool $state): string => BadgeStyles::domainStatusColor('curriculum', $state ? 'obe' : 'non_obe')),
                    ]),
                Section::make('Dosen Profile')
                    ->visible(fn(?User $record): bool => $record?->hasRole(AppRole::Dosen) ?? false)
                    ->schema([
                        TextEntry::make('dosenProfile.nik')
                            ->label('NIK')
                            ->placeholder('-'),
                        TextEntry::make('dosenProfile.programStudi.name')
                            ->label('Prodi')
                            ->placeholder('-')
                            ->badge()
                            ->color(fn(?string $state): string => BadgeStyles::programStudiColor($state))
                            ->icon(BadgeStyles::programStudiIcon()),
                        TextEntry::make('dosenProfile.concentration')
                            ->label('Konsentrasi')
                            ->placeholder('-'),
                        TextEntry::make('expertiseFields.name')
                            ->label('Bidang Keilmuan')
                            ->badge()
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('dosenProfile.supervision_quota')
                            ->label('Kuota Bimbingan')
                            ->placeholder('-'),
                        TextEntry::make('dosenProfile.is_active')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn(?bool $state): string => BadgeStyles::activeStateLabel($state))
                            ->color(fn(?bool $state): string => BadgeStyles::activeStateColor($state))
                            ->icon(fn(?bool $state): string => BadgeStyles::activeStateIcon($state)),
                    ]),
                Section::make('Beban '.AcademicTerminology::neutral()['finalWork'])
                    ->visible(fn(?User $record): bool => $record?->hasRole(AppRole::Dosen) ?? false)
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('active_primary_supervision_count')
                            ->label('Pembimbing 1 Aktif')
                            ->placeholder('0'),
                        TextEntry::make('active_secondary_supervision_count')
                            ->label('Pembimbing 2 Aktif')
                            ->placeholder('0'),
                        TextEntry::make('scheduled_sempro_examiner_count')
                            ->label(AcademicTerminology::neutral()['proposalExamShort'].' Terjadwal')
                            ->placeholder('0'),
                        TextEntry::make('scheduled_sidang_examiner_count')
                            ->label(AcademicTerminology::neutral()['finalExamShort'].' Terjadwal')
                            ->placeholder('0'),
                        TextEntry::make('thesis_load_note')
                            ->label('Catatan')
                            ->state('Kuota pembimbing dapat diatur oleh superadmin. Konsentrasi ditampilkan sebagai informasi dan tidak membatasi penetapan pembimbing.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
