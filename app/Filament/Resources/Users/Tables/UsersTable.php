<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\AppRole;
use App\Filament\Resources\Users\Actions\SendPasswordResetLinkAction;
use App\Models\ProgramStudi;
use App\Models\User;
use App\Support\Filament\BadgeStyles;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\ToggleButtons;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\ColumnManagerResetActionPosition;
use Filament\Tables\Enums\FiltersResetActionPosition;
use Filament\Tables\Filters\Filter;
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
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->state(fn(?User $record): string => $record?->roles->pluck('name')->first() ?? '-')
                    ->formatStateUsing(fn(string $state): string => BadgeStyles::roleLabel($state))
                    ->color(fn(string $state): string => BadgeStyles::roleColor($state))
                    ->icon(fn(string $state): string => BadgeStyles::roleIcon($state))
                    ->toggleable(),
                TextColumn::make('mahasiswaProfile.nim')
                    ->label('NIM')
                    ->searchable()
                    ->visible(fn(HasTable $livewire): bool => self::isTab($livewire, 'mahasiswa'))
                    ->toggleable(),
                TextColumn::make('dosenProfile.nik')
                    ->label('NIK')
                    ->searchable()
                    ->visible(fn(HasTable $livewire): bool => self::isTab($livewire, 'dosen'))
                    ->toggleable(),
                TextColumn::make('prodi')
                    ->label('Prodi')
                    ->state(fn(?User $record): string => $record?->mahasiswaProfile?->programStudi?->name ?? $record?->dosenProfile?->programStudi?->name ?? $record?->adminProfile?->programStudi?->name ?? '-')
                    ->badge()
                    ->icon(BadgeStyles::programStudiIcon())
                    ->color(fn(?string $state): string => BadgeStyles::programStudiColor($state))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('mahasiswaProfile.programStudi', fn($q) => $q->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('dosenProfile.programStudi', fn($q) => $q->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('adminProfile.programStudi', fn($q) => $q->where('name', 'like', "%{$search}%"));
                    })
                    ->toggleable(),
                TextColumn::make('concentration')
                    ->label('Konsentrasi')
                    ->state(fn(?User $record): string => $record?->mahasiswaProfile?->concentration ?? $record?->dosenProfile?->concentration ?? '-')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('mahasiswaProfile', fn(Builder $profileQuery): Builder => $profileQuery->where('concentration', 'like', "%{$search}%"))
                            ->orWhereHas('dosenProfile', fn(Builder $profileQuery): Builder => $profileQuery->where('concentration', 'like', "%{$search}%"));
                    })
                    ->visible(fn(HasTable $livewire): bool => in_array($livewire->activeTab ?? null, ['mahasiswa', 'dosen'], true))
                    ->toggleable(),
                TextColumn::make('active_primary_supervision_count')
                    ->label('B1 Aktif')
                    ->numeric()
                    ->sortable()
                    ->visible(fn(HasTable $livewire): bool => self::isTab($livewire, 'dosen'))
                    ->toggleable(),
                TextColumn::make('active_secondary_supervision_count')
                    ->label('B2 Aktif')
                    ->numeric()
                    ->sortable()
                    ->visible(fn(HasTable $livewire): bool => self::isTab($livewire, 'dosen'))
                    ->toggleable(),
                TextColumn::make('scheduled_sempro_examiner_count')
                    ->label('Sempro')
                    ->numeric()
                    ->sortable()
                    ->visible(fn(HasTable $livewire): bool => self::isTab($livewire, 'dosen'))
                    ->toggleable(),
                TextColumn::make('scheduled_sidang_examiner_count')
                    ->label('Sidang')
                    ->numeric()
                    ->sortable()
                    ->visible(fn(HasTable $livewire): bool => self::isTab($livewire, 'dosen'))
                    ->toggleable(),
                TextColumn::make('dosenProfile.supervision_quota')
                    ->label('Kuota')
                    ->numeric()
                    ->sortable()
                    ->visible(fn(HasTable $livewire): bool => self::isTab($livewire, 'dosen'))
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('role')
                    ->label('Role')
                    ->schema([
                        ToggleButtons::make('value')
                            ->label('Role')
                            ->inline()
                            ->options(self::roleOptions())
                            ->colors(self::roleFilterColors())
                            ->icons(self::roleFilterIcons()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        return $query->whereHas('roles', static fn($roleQuery) => $roleQuery->where('name', $value));
                    })
                    ->indicateUsing(function (array $data): array {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return [];
                        }

                        return ['Role: '.BadgeStyles::roleLabel($value)];
                    }),
                SelectFilter::make('program_studi_id')
                    ->label('Program Studi')
                    ->options(fn(): array => ProgramStudi::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        return $query->where(function (Builder $subQuery) use ($value): void {
                            $subQuery->whereHas('mahasiswaProfile', fn(Builder $profileQuery): Builder => $profileQuery->where('program_studi_id', $value))
                                ->orWhereHas('dosenProfile', fn(Builder $profileQuery): Builder => $profileQuery->where('program_studi_id', $value))
                                ->orWhereHas('adminProfile', fn(Builder $profileQuery): Builder => $profileQuery->where('program_studi_id', $value));
                        });
                    }),
                SelectFilter::make('concentration')
                    ->label('Konsentrasi')
                    ->options(fn(): array => self::concentrationOptions())
                    ->native(false)
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
                Filter::make('active_profiles')
                    ->label('Hanya profil aktif')
                    ->toggle()
                    ->query(function (Builder $query): Builder {
                        return $query->where(function (Builder $subQuery): void {
                            $subQuery->whereHas('mahasiswaProfile', fn(Builder $profileQuery): Builder => $profileQuery->where('is_active', true))
                                ->orWhereHas('dosenProfile', fn(Builder $profileQuery): Builder => $profileQuery->where('is_active', true));
                        });
                    }),
            ])
            ->filtersFormColumns(2)
            ->filtersFormWidth(Width::FiveExtraLarge)
            ->filtersTriggerAction(fn(Action $action): Action => $action->button()->label('Filter Pengguna'))
            ->filtersApplyAction(fn(Action $action): Action => $action->label('Terapkan Filter'))
            ->filtersResetActionPosition(FiltersResetActionPosition::Footer)
            ->columnManagerColumns(2)
            ->columnManagerResetActionPosition(ColumnManagerResetActionPosition::Footer)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                SendPasswordResetLinkAction::make(),
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
    private static function roleOptions(): array
    {
        return collect(AppRole::uiValues())
            ->mapWithKeys(fn(string $role): array => [$role => BadgeStyles::roleLabel($role)])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function roleFilterColors(): array
    {
        return collect(AppRole::uiValues())
            ->mapWithKeys(fn(string $role): array => [$role => BadgeStyles::roleColor($role)])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function roleFilterIcons(): array
    {
        return collect(AppRole::uiValues())
            ->mapWithKeys(fn(string $role): array => [$role => BadgeStyles::roleIcon($role)])
            ->all();
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
