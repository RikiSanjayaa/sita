<?php

namespace App\Filament\Resources\SystemAnnouncements\Tables;

use App\Enums\AppRole;
use App\Models\SystemAnnouncement;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SystemAnnouncementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => $state === SystemAnnouncement::STATUS_PUBLISHED ? 'Published' : 'Draft')
                    ->color(fn(string $state): string => $state === SystemAnnouncement::STATUS_PUBLISHED ? 'success' : 'gray'),
                TextColumn::make('target_roles')
                    ->label('Target')
                    ->state(fn(SystemAnnouncement $record): string => collect($record->normalizedTargetRoles())
                        ->map(fn(string $role): string => match ($role) {
                            AppRole::Mahasiswa->value => 'Mahasiswa',
                            AppRole::Dosen->value => 'Dosen',
                            AppRole::Admin->value => 'Admin',
                            AppRole::SuperAdmin->value => 'Super Admin',
                            default => $role,
                        })
                        ->implode(', '))
                    ->wrap(),
                TextColumn::make('programStudi.name')
                    ->label('Program Studi')
                    ->placeholder('Semua Program Studi'),
                TextColumn::make('published_at')
                    ->label('Published')
                    ->since()
                    ->placeholder('Belum'),
                TextColumn::make('notified_at')
                    ->label('Dikirim')
                    ->since()
                    ->placeholder('Belum'),
                TextColumn::make('createdBy.name')
                    ->label('Dibuat Oleh')
                    ->placeholder('-'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([]);
    }
}
