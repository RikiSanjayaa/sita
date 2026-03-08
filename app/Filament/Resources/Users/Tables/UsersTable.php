<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\AppRole;
use App\Models\ProgramStudi;
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
                TextColumn::make('concentration')
                    ->label('Konsentrasi')
                    ->state(fn(?User $record): string => $record?->mahasiswaProfile?->concentration ?? $record?->dosenProfile?->concentration ?? '-')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('mahasiswaProfile', fn(Builder $profileQuery): Builder => $profileQuery->where('concentration', 'like', "%{$search}%"))
                            ->orWhereHas('dosenProfile', fn(Builder $profileQuery): Builder => $profileQuery->where('concentration', 'like', "%{$search}%"));
                    })
                    ->visible(fn(HasTable $livewire): bool => in_array($livewire->activeTab ?? null, ['mahasiswa', 'dosen'], true)),
                TextColumn::make('active_primary_supervision_count')
                    ->label('B1 Aktif')
                    ->numeric()
                    ->sortable()
                    ->visible(fn(HasTable $livewire): bool => self::isTab($livewire, 'dosen')),
                TextColumn::make('active_secondary_supervision_count')
                    ->label('B2 Aktif')
                    ->numeric()
                    ->sortable()
                    ->visible(fn(HasTable $livewire): bool => self::isTab($livewire, 'dosen')),
                TextColumn::make('scheduled_sempro_examiner_count')
                    ->label('Sempro')
                    ->numeric()
                    ->sortable()
                    ->visible(fn(HasTable $livewire): bool => self::isTab($livewire, 'dosen')),
                TextColumn::make('scheduled_sidang_examiner_count')
                    ->label('Sidang')
                    ->numeric()
                    ->sortable()
                    ->visible(fn(HasTable $livewire): bool => self::isTab($livewire, 'dosen')),
                TextColumn::make('dosenProfile.supervision_quota')
                    ->label('Kuota')
                    ->numeric()
                    ->sortable()
                    ->visible(fn(HasTable $livewire): bool => self::isTab($livewire, 'dosen')),
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
                SelectFilter::make('concentration')
                    ->label('Konsentrasi')
                    ->options(fn(): array => self::concentrationOptions())
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        return $query->where(function (Builder $subQuery) use ($value): void {
                            $subQuery->whereHas('mahasiswaProfile', fn(Builder $profileQuery): Builder => $profileQuery->where('concentration', $value))
                                ->orWhereHas('dosenProfile', fn(Builder $profileQuery): Builder => $profileQuery->where('concentration', $value));
                        });
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

    /**
     * @return array<string, string>
     */
    private static function concentrationOptions(): array
    {
        return ProgramStudi::query()
            ->get()
            ->flatMap(static fn(ProgramStudi $programStudi): array => $programStudi->concentrationList())
            ->filter()
            ->unique()
            ->values()
            ->mapWithKeys(static fn(string $concentration): array => [$concentration => $concentration])
            ->all();
    }
}
