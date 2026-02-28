<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\AppRole;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('last_active_role')
                    ->badge()
                    ->sortable(),
                TextColumn::make('mahasiswaProfile.nim')
                    ->label('NIM')
                    ->searchable(),
                TextColumn::make('dosenProfile.nidn')
                    ->label('NIDN')
                    ->searchable(),
                TextColumn::make('mahasiswaProfile.program_studi')
                    ->label('Prodi (Mahasiswa)')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('dosenProfile.homebase')
                    ->label('Homebase (Dosen)')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('browser_notifications_enabled')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options(array_combine(AppRole::uiValues(), AppRole::uiValues()))
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        return $query->whereHas('roles', static fn ($roleQuery) => $roleQuery->where('name', $value));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
