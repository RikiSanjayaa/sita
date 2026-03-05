<?php

namespace App\Filament\Resources\ThesisSubmissions\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ThesisSubmissionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Mahasiswa')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('student.name')
                            ->label('Nama Mahasiswa'),
                        TextEntry::make('student.mahasiswaProfile.nim')
                            ->label('NIM')
                            ->placeholder('-'),
                        TextEntry::make('programStudi.name')
                            ->label('Program Studi')
                            ->placeholder('-'),
                    ]),
                Section::make('Detail Proposal')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('title_id')
                            ->label('Judul (ID)')
                            ->columnSpanFull(),
                        TextEntry::make('title_en')
                            ->label('Judul (EN)')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('proposal_summary')
                            ->label('Ringkasan')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('proposal_file_path')
                            ->label('File Proposal')
                            ->formatStateUsing(fn(?string $state): string => $state === null ? '-' : 'Download Proposal')
                            ->url(fn(?string $state): ?string => $state === null ? null : asset('storage/'.$state))
                            ->openUrlInNewTab()
                            ->placeholder('-'),
                    ]),
                Section::make('Status & Approval')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn(string $state): string => ucwords(str_replace('_', ' ', $state))),
                        IconEntry::make('is_active')
                            ->label('Status Aktif')
                            ->boolean(),
                        TextEntry::make('submitted_at')
                            ->label('Tanggal Submit')
                            ->dateTime('d M Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('approved_at')
                            ->label('Tanggal Disetujui')
                            ->dateTime('d M Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('approvedBy.name')
                            ->label('Disetujui Oleh')
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->dateTime('d M Y H:i')
                            ->placeholder('-'),
                    ]),
            ]);
    }
}
