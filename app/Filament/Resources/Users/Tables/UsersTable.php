<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\AppRole;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->state(fn(?User $record): string => $record?->roles->pluck('name')->first() ?? '-'),
                TextColumn::make('mahasiswaProfile.nim')
                    ->label('NIM')
                    ->searchable()
                    ->visible(fn(HasTable $livewire): bool => self::isTab($livewire, 'mahasiswa')),
                TextColumn::make('dosenProfile.nik')
                    ->label('NIK')
                    ->searchable()
                    ->visible(fn(HasTable $livewire): bool => self::isTab($livewire, 'dosen')),
                TextColumn::make('prodi')
                    ->label('Prodi')
                    ->state(fn(?User $record): string => $record?->mahasiswaProfile?->programStudi?->name ?? $record?->dosenProfile?->programStudi?->name ?? $record?->adminProfile?->programStudi?->name ?? '-')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('mahasiswaProfile.programStudi', fn($q) => $q->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('dosenProfile.programStudi', fn($q) => $q->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('adminProfile.programStudi', fn($q) => $q->where('name', 'like', "%{$search}%"));
                    }),
                TextColumn::make('active_bimbingan_count')
                    ->label('Bimbingan Aktif')
                    ->numeric()
                    ->sortable()
                    ->visible(fn(HasTable $livewire): bool => self::isTab($livewire, 'dosen')),
                TextColumn::make('finished_bimbingan_count')
                    ->label('Bimbingan Selesai')
                    ->numeric()
                    ->sortable()
                    ->visible(fn(HasTable $livewire): bool => self::isTab($livewire, 'dosen'))
                    ->toggleable(isToggledHiddenByDefault: true),
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

                        return $query->whereHas('roles', static fn($roleQuery) => $roleQuery->where('name', $value));
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

    private static function isTab(HasTable $livewire, string $tab): bool
    {
        $activeTab = $livewire->activeTab ?? null;

        return $activeTab === $tab;
    }
}
