<?php

namespace App\Filament\Resources\Sempros\Tables;

use App\Enums\SemproStatus;
use App\Models\Sempro;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SemprosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('submission.student.name')
                    ->label('Mahasiswa')
                    ->searchable()
                    ->sortable()
                    ->description(fn(?Sempro $record): string => $record?->submission?->student?->mahasiswaProfile?->nim ?? '-'),
                TextColumn::make('submission.programStudi.name')
                    ->label('Program Studi')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('submission.title_id')
                    ->label('Judul Skripsi')
                    ->limit(45)
                    ->searchable()
                    ->wrap(),
                BadgeColumn::make('status')
                    ->colors([
                        'gray' => SemproStatus::Draft->value,
                        'info' => SemproStatus::Scheduled->value,
                        'warning' => SemproStatus::RevisionOpen->value,
                        'success' => SemproStatus::Approved->value,
                    ])
                    ->formatStateUsing(fn(string $state): string => self::statusLabel($state)),
                TextColumn::make('scheduled_for')
                    ->label('Jadwal')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('Belum dijadwalkan'),
                TextColumn::make('location')
                    ->label('Lokasi')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('mode')
                    ->label('Mode')
                    ->badge()
                    ->formatStateUsing(fn(?string $state): string => ucfirst($state ?? '-')),
                TextColumn::make('examiners_count')
                    ->label('Penguji')
                    ->counts('examiners')
                    ->sortable(),
                TextColumn::make('approved_at')
                    ->label('Disetujui')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'scheduled' => 'Dijadwalkan',
            'revision_open' => 'Revisi',
            'approved' => 'Selesai',
            default => ucfirst($status),
        };
    }
}
