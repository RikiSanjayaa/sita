<?php

namespace App\Filament\Resources\Sempros\Schemas;

use App\Enums\SemproExaminerDecision;
use App\Models\Sempro;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SemproInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Mahasiswa & Judul')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('submission.student.name')
                            ->label('Mahasiswa'),
                        TextEntry::make('submission.student.mahasiswaProfile.nim')
                            ->label('NIM')
                            ->placeholder('-'),
                        TextEntry::make('submission.programStudi.name')
                            ->label('Program Studi')
                            ->placeholder('-'),
                        TextEntry::make('submission.title_id')
                            ->label('Judul (ID)')
                            ->columnSpanFull(),
                        TextEntry::make('submission.title_en')
                            ->label('Judul (EN)')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
                Section::make('Detail Jadwal')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                'draft' => 'Draft',
                                'scheduled' => 'Dijadwalkan',
                                'revision_open' => 'Revisi',
                                'approved' => 'Selesai',
                                default => ucfirst($state),
                            }),
                        TextEntry::make('mode')
                            ->formatStateUsing(fn(?string $state): string => ucfirst($state ?? '-')),
                        TextEntry::make('scheduled_for')
                            ->label('Jadwal')
                            ->dateTime('d M Y H:i')
                            ->placeholder('Belum dijadwalkan'),
                        TextEntry::make('location')
                            ->label('Lokasi')
                            ->placeholder('-'),
                        TextEntry::make('revision_due_at')
                            ->label('Batas Revisi')
                            ->dateTime('d M Y H:i')
                            ->placeholder('-'),
                    ]),
                Section::make('Dosen Penguji & Keputusan')
                    ->schema([
                        RepeatableEntry::make('examiners')
                            ->label('')
                            ->schema([
                                TextEntry::make('examiner.name')
                                    ->label('Nama Penguji'),
                                TextEntry::make('examiner_order')
                                    ->label('Urutan'),
                                TextEntry::make('decision')
                                    ->label('Keputusan')
                                    ->badge()
                                    ->color(fn(?string $state): string => match ($state) {
                                        SemproExaminerDecision::Approved->value => 'success',
                                        SemproExaminerDecision::NeedsRevision->value => 'warning',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn(?string $state): string => match ($state) {
                                        'pending' => 'Pending',
                                        'needs_revision' => 'Perlu Revisi',
                                        'approved' => 'Disetujui',
                                        default => $state ?? '-',
                                    }),
                                TextEntry::make('score')
                                    ->label('Nilai')
                                    ->placeholder('-'),
                                TextEntry::make('decision_notes')
                                    ->label('Catatan')
                                    ->placeholder('-'),
                            ])
                            ->columns(5),
                    ]),
                Section::make('Revisi')
                    ->schema([
                        RepeatableEntry::make('revisions')
                            ->label('')
                            ->schema([
                                TextEntry::make('notes')
                                    ->label('Catatan Revisi'),
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge(),
                                TextEntry::make('due_at')
                                    ->label('Batas')
                                    ->dateTime('d M Y')
                                    ->placeholder('-'),
                                TextEntry::make('resolved_at')
                                    ->label('Diselesaikan')
                                    ->dateTime('d M Y')
                                    ->placeholder('-'),
                                TextEntry::make('requestedBy.name')
                                    ->label('Diminta Oleh')
                                    ->placeholder('-'),
                            ])
                            ->columns(5),
                    ])
                    ->visible(fn(?Sempro $record): bool => ($record?->revisions?->count() ?? 0) > 0),
                Section::make('Status Akhir')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('approved_at')
                            ->label('Tanggal Disetujui')
                            ->dateTime('d M Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('approvedBy.name')
                            ->label('Disetujui Oleh')
                            ->placeholder('-'),
                        TextEntry::make('createdBy.name')
                            ->label('Dibuat Oleh')
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->label('Tanggal Dibuat')
                            ->dateTime('d M Y H:i'),
                    ]),
            ]);
    }
}
