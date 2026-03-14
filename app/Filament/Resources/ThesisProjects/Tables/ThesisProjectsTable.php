<?php

namespace App\Filament\Resources\ThesisProjects\Tables;

use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ColumnManagerResetActionPosition;
use Filament\Tables\Enums\FiltersResetActionPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ThesisProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('student.name')
                    ->label('Mahasiswa')
                    ->searchable()
                    ->sortable()
                    ->description(fn(?ThesisProject $record): string => $record?->student?->mahasiswaProfile?->nim ?? '-')
                    ->toggleable(),
                TextColumn::make('programStudi.name')
                    ->label('Program Studi')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('current_title')
                    ->label('Judul Aktif')
                    ->state(fn(ThesisProject $record): string => self::resolveCurrentTitle($record)?->title_id ?? '-')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('titles', fn(Builder $builder): Builder => $builder
                            ->where('title_id', 'like', "%{$search}%")
                            ->orWhere('title_en', 'like', "%{$search}%"));
                    })
                    ->wrap()
                    ->limit(60)
                    ->toggleable(),
                BadgeColumn::make('phase')
                    ->label('Fase')
                    ->colors([
                        'gray' => 'title_review',
                        'info' => 'sempro',
                        'warning' => 'research',
                        'primary' => 'sidang',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn(string $state): string => self::phaseLabel($state))
                    ->toggleable(),
                BadgeColumn::make('state')
                    ->label('State')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'on_hold',
                        'gray' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn(string $state): string => self::stateLabel($state))
                    ->toggleable(),
                TextColumn::make('active_supervisors')
                    ->label('Pembimbing Aktif')
                    ->state(fn(ThesisProject $record): array => $record->activeSupervisorAssignments
                        ->sortBy('role')
                        ->map(fn($assignment): string => sprintf(
                            '%s: %s',
                            $assignment->role === 'primary' ? 'P1' : 'P2',
                            $assignment->lecturer?->name ?? '-',
                        ))
                        ->values()
                        ->all())
                    ->listWithLineBreaks()
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('sempro_attempts_count')
                    ->label('Sempro')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('sidang_attempts_count')
                    ->label('Sidang')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('open_revisions_count')
                    ->label('Revisi Terbuka')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('next_agenda')
                    ->label('Agenda Terdekat')
                    ->state(fn(ThesisProject $record): string => self::formatNextAgenda($record))
                    ->toggleable(),
                TextColumn::make('started_at')
                    ->label('Mulai')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('phase')
                    ->label('Fase')
                    ->options([
                        'title_review' => self::phaseLabel('title_review'),
                        'sempro' => self::phaseLabel('sempro'),
                        'research' => self::phaseLabel('research'),
                        'sidang' => self::phaseLabel('sidang'),
                        'completed' => self::phaseLabel('completed'),
                        'cancelled' => self::phaseLabel('cancelled'),
                    ]),
                SelectFilter::make('state')
                    ->label('State')
                    ->options([
                        'active' => self::stateLabel('active'),
                        'on_hold' => self::stateLabel('on_hold'),
                        'completed' => self::stateLabel('completed'),
                        'cancelled' => self::stateLabel('cancelled'),
                    ]),
                SelectFilter::make('program_studi_id')
                    ->label('Program Studi')
                    ->options(fn(): array => ProgramStudi::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload(),
                Filter::make('missing_supervisors')
                    ->label('Butuh pembimbing tambahan')
                    ->toggle()
                    ->query(fn(Builder $query): Builder => $query
                        ->where('state', 'active')
                        ->whereIn('phase', ['research', 'sidang'])
                        ->has('activeSupervisorAssignments', '<', 2)),
                Filter::make('open_revisions')
                    ->label('Hanya yang punya revisi terbuka')
                    ->toggle()
                    ->query(fn(Builder $query): Builder => $query
                        ->whereHas('revisions', fn(Builder $revisionQuery): Builder => $revisionQuery->whereIn('status', ['open', 'submitted']))),
                Filter::make('upcoming_agenda')
                    ->label('Hanya yang punya agenda terjadwal')
                    ->toggle()
                    ->query(fn(Builder $query): Builder => $query
                        ->whereHas('defenses', fn(Builder $defenseQuery): Builder => $defenseQuery
                            ->where('status', 'scheduled')
                            ->whereNotNull('scheduled_for'))),
            ])
            ->filtersFormColumns(2)
            ->filtersFormWidth(Width::FiveExtraLarge)
            ->filtersTriggerAction(fn(Action $action): Action => $action
                ->button()
                ->label('Filter Workflow'))
            ->filtersApplyAction(fn(Action $action): Action => $action->label('Terapkan Filter'))
            ->filtersResetActionPosition(FiltersResetActionPosition::Footer)
            ->columnManagerColumns(2)
            ->columnManagerResetActionPosition(ColumnManagerResetActionPosition::Footer)
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function phaseLabel(string $phase): string
    {
        return match ($phase) {
            'title_review' => 'Review Judul',
            'sempro' => 'Sempro',
            'research' => 'Riset',
            'sidang' => 'Sidang',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            default => ucwords(str_replace('_', ' ', $phase)),
        };
    }

    public static function stateLabel(string $state): string
    {
        return match ($state) {
            'active' => 'Aktif',
            'on_hold' => 'Ditunda',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            default => ucwords(str_replace('_', ' ', $state)),
        };
    }

    private static function resolveCurrentTitle(ThesisProject $record): ?ThesisProjectTitle
    {
        $approved = $record->titles
            ->where('status', 'approved')
            ->sortByDesc('version_no')
            ->first();

        if ($approved instanceof ThesisProjectTitle) {
            return $approved;
        }

        return $record->latestTitle;
    }

    private static function formatNextAgenda(ThesisProject $record): string
    {
        /** @var ThesisDefense|null $defense */
        $defense = $record->defenses
            ->filter(fn(ThesisDefense $item): bool => $item->scheduled_for !== null && $item->status === 'scheduled')
            ->sortBy('scheduled_for')
            ->first();

        if (! $defense instanceof ThesisDefense) {
            return '-';
        }

        return sprintf(
            '%s #%d - %s',
            strtoupper($defense->type),
            $defense->attempt_no,
            $defense->scheduled_for?->format('d M Y H:i') ?? '-',
        );
    }
}
