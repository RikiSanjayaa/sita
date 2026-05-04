<?php

namespace App\Filament\Resources\ThesisProjectEvents\Tables;

use App\Filament\Resources\ThesisProjects\ThesisProjectResource;
use App\Models\ProgramStudi;
use App\Models\ThesisProjectEvent;
use App\Models\User;
use App\Support\Filament\BadgeStyles;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ColumnManagerResetActionPosition;
use Filament\Tables\Enums\FiltersResetActionPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ThesisProjectEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Waktu')
                    ->since()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('label')
                    ->label('Aktivitas')
                    ->searchable()
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('description')
                    ->label('Deskripsi')
                    ->wrap()
                    ->placeholder('-')
                    ->limit(100)
                    ->toggleable(),
                TextColumn::make('project.student.name')
                    ->label('Mahasiswa')
                    ->description(fn(ThesisProjectEvent $record): string => $record->project?->student?->mahasiswaProfile?->nim ?? '-')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('project.programStudi.name')
                    ->label('Prodi')
                    ->badge()
                    ->icon(BadgeStyles::programStudiIcon())
                    ->color(fn(?string $state): string => BadgeStyles::programStudiColor($state))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('project.latestTitle.title_id')
                    ->label('Judul Aktif')
                    ->wrap()
                    ->limit(60)
                    ->toggleable(),
                TextColumn::make('actor.name')
                    ->label('Aktor')
                    ->placeholder('Sistem')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('event_type')
                    ->label('Tipe Event')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => BadgeStyles::thesisEventLabel($state))
                    ->color(fn(string $state): string => BadgeStyles::thesisEventColor($state))
                    ->icon(fn(string $state): string => BadgeStyles::thesisEventIcon($state))
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('event_type')
                    ->label('Tipe Event')
                    ->options(fn(): array => ThesisProjectEvent::query()
                        ->distinct()
                        ->orderBy('event_type')
                        ->pluck('event_type', 'event_type')
                        ->mapWithKeys(fn(string $eventType): array => [$eventType => BadgeStyles::thesisEventLabel($eventType)])
                        ->all())
                    ->native(false),
                SelectFilter::make('program_studi_id')
                    ->label('Program Studi')
                    ->visible(function (): bool {
                        /** @var User|null $user */
                        $user = Auth::user();

                        return $user?->adminProgramStudiId() === null;
                    })
                    ->options(fn(): array => ProgramStudi::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        return $query->whereHas('project', fn(Builder $projectQuery): Builder => $projectQuery->where('program_studi_id', $value));
                    }),
                SelectFilter::make('actor_user_id')
                    ->label('Aktor')
                    ->relationship('actor', 'name')
                    ->native(false)
                    ->searchable()
                    ->preload(),
            ])
            ->filtersFormColumns(2)
            ->filtersFormWidth(Width::FourExtraLarge)
            ->filtersTriggerAction(fn(Action $action): Action => $action->button()->label('Filter Audit'))
            ->filtersApplyAction(fn(Action $action): Action => $action->label('Terapkan Filter'))
            ->filtersResetActionPosition(FiltersResetActionPosition::Footer)
            ->columnManagerColumns(2)
            ->columnManagerResetActionPosition(ColumnManagerResetActionPosition::Footer)
            ->recordActions([
                Action::make('openProject')
                    ->label('Buka Proyek')
                    ->url(fn(ThesisProjectEvent $record): string => ThesisProjectResource::getUrl('view', ['record' => $record->project]))
                    ->icon('heroicon-m-arrow-top-right-on-square'),
            ]);
    }
}
