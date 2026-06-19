<?php

namespace App\Filament\Resources\ProgramStudis\Tables;

use App\Enums\DegreeLevel;
use App\Models\Faculty;
use App\Models\ProgramStudi;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ColumnManagerResetActionPosition;
use Filament\Tables\Enums\FiltersResetActionPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProgramStudisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('faculty.name')
                    ->label('Fakultas')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('degree_levels')
                    ->label('Jenjang')
                    ->state(fn(ProgramStudi $record): array => array_values($record->degreeLevelOptions()))
                    ->badge()
                    ->color('info')
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('concentrations')
                    ->label('Konsentrasi')
                    ->state(fn(ProgramStudi $record): array => $record->concentrationList())
                    ->badge()
                    ->color('success')
                    ->searchable()
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('kaprodi')
                    ->label('Kaprodi')
                    ->state(fn(ProgramStudi $record): array => self::kaprodiNames($record))
                    ->badge()
                    ->color(fn(string $state, ProgramStudi $record): string => self::kaprodiBadgeColor($state, $record))
                    ->searchable(query: function ($query, string $search) {
                        return $query->whereHas('kaprodiAssignments.user', function ($userQuery) use ($search): void {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                    })
                    ->placeholder('Belum ditetapkan')
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('faculty_id')
                    ->label('Fakultas')
                    ->options(fn(): array => Faculty::query()
                        ->where('is_placeholder', false)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('degree_level')
                    ->label('Jenjang')
                    ->options(DegreeLevel::options())
                    ->query(fn($query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereJsonContains('degree_levels', $data['value'])
                        : $query),
                SelectFilter::make('concentration')
                    ->label('Konsentrasi')
                    ->options(fn(): array => ProgramStudi::query()
                        ->get()
                        ->flatMap(static fn(ProgramStudi $programStudi): array => $programStudi->concentrationList())
                        ->filter()
                        ->unique()
                        ->values()
                        ->mapWithKeys(static fn(string $concentration): array => [$concentration => $concentration])
                        ->all())
                    ->searchable()
                    ->preload()
                    ->query(fn($query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereJsonContains('concentrations', $data['value'])
                        : $query),
            ])
            ->filtersFormColumns(1)
            ->filtersFormWidth(Width::Large)
            ->filtersTriggerAction(fn(Action $action): Action => $action->button()->label('Filter Prodi'))
            ->filtersApplyAction(fn(Action $action): Action => $action->label('Terapkan Filter'))
            ->filtersResetActionPosition(FiltersResetActionPosition::Footer)
            ->columnManagerColumns(2)
            ->columnManagerResetActionPosition(ColumnManagerResetActionPosition::Footer)
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function kaprodiNames(ProgramStudi $record): array
    {
        return $record->kaprodiAssignments
            ->sortByDesc('is_primary')
            ->map(fn($assignment): string => $assignment->user?->name ?? '-')
            ->filter()
            ->values()
            ->all();
    }

    private static function kaprodiBadgeColor(string $name, ProgramStudi $record): string
    {
        $primaryName = $record->kaprodiAssignments
            ->firstWhere('is_primary', true)
            ?->user?->name;

        return $name === $primaryName ? 'danger' : 'info';
    }
}
