<?php

namespace App\Filament\Resources\ThesisProjects\Tables;

use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Services\ThesisProjectAdminService;
use App\Support\Filament\BadgeStyles;
use App\Support\WitaDateTime;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ColumnManagerResetActionPosition;
use Filament\Tables\Enums\FiltersResetActionPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

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
                TextColumn::make('student.email')
                    ->label('Email Mahasiswa')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('programStudi.name')
                    ->label('Program Studi')
                    ->badge()
                    ->icon(BadgeStyles::programStudiIcon())
                    ->color(fn(?string $state): string => BadgeStyles::programStudiColor($state))
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
                TextColumn::make('phase')
                    ->label('Fase')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => self::phaseLabel($state))
                    ->color(fn(string $state): string => BadgeStyles::phaseColor($state))
                    ->icon(fn(string $state): string => BadgeStyles::phaseIcon($state))
                    ->toggleable(),
                TextColumn::make('state')
                    ->label('State')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => self::stateLabel($state))
                    ->color(fn(string $state): string => BadgeStyles::stateColor($state))
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
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sempro_attempts_count')
                    ->label('Proposal')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sidang_attempts_count')
                    ->label('Ujian Akhir')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('open_revisions_count')
                    ->label('Revisi Terbuka')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('next_agenda')
                    ->label('Agenda Terdekat')
                    ->state(fn(ThesisProject $record): string => self::formatNextAgenda($record))
                    ->toggleable(),
                TextColumn::make('started_at')
                    ->label('Mulai')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('phase')
                    ->label('Fase')
                    ->schema([
                        ToggleButtons::make('value')
                            ->label('Fase')
                            ->inline()
                            ->options(self::phaseOptions())
                            ->colors(self::phaseFilterColors())
                            ->icons(self::phaseFilterIcons()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        return $query->where('phase', $value);
                    })
                    ->indicateUsing(function (array $data): array {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return [];
                        }

                        return ['Fase: '.self::phaseLabel($value)];
                    }),
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
                    ->native(false)
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
                Action::make('approve_title')
                    ->label('Setujui')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->visible(fn(ThesisProject $record): bool => self::canDecideTitleReview($record))
                    ->form([
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->default('Judul dan proposal disetujui. Mahasiswa dapat lanjut ke tahap sempro.')
                            ->rows(3),
                    ])
                    ->action(function (ThesisProject $record, array $data): void {
                        $userId = Auth::id();

                        if ($userId === null) {
                            return;
                        }

                        try {
                            app(ThesisProjectAdminService::class)->approveTitleReview(
                                project: $record,
                                decidedBy: $userId,
                                notes: $data['notes'] ?? null,
                            );

                            Notification::make()
                                ->title('Judul berhasil disetujui')
                                ->success()
                                ->send();
                        } catch (\Throwable $exception) {
                            Notification::make()
                                ->title('Gagal menyetujui judul')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('reject_title')
                    ->label('Tolak')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->visible(fn(ThesisProject $record): bool => self::canDecideTitleReview($record))
                    ->requiresConfirmation()
                    ->modalHeading('Tolak pengajuan judul?')
                    ->modalDescription('Proyek akan ditandai dibatalkan dan mahasiswa dapat mengajukan judul baru.')
                    ->form([
                        Textarea::make('notes')
                            ->label('Alasan')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (ThesisProject $record, array $data): void {
                        $userId = Auth::id();

                        if ($userId === null) {
                            return;
                        }

                        try {
                            app(ThesisProjectAdminService::class)->rejectTitleReview(
                                project: $record,
                                decidedBy: $userId,
                                notes: (string) $data['notes'],
                            );

                            Notification::make()
                                ->title('Judul ditandai tidak disetujui')
                                ->success()
                                ->send();
                        } catch (\Throwable $exception) {
                            Notification::make()
                                ->title('Gagal menolak judul')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-m-pencil-square')
                    ->url(fn(ThesisProject $record): string => \App\Filament\Resources\ThesisProjects\ThesisProjectResource::getUrl('edit', ['record' => $record])),
                ViewAction::make(),
            ]);
    }

    public static function phaseLabel(string $phase): string
    {
        return match ($phase) {
            'title_review' => 'Review Judul',
            'sempro' => 'Proposal',
            'research' => 'Riset',
            'sidang' => 'Ujian Akhir',
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

    /**
     * @return array<string, string>
     */
    public static function phaseOptions(): array
    {
        return [
            'title_review' => self::phaseLabel('title_review'),
            'sempro' => self::phaseLabel('sempro'),
            'research' => self::phaseLabel('research'),
            'sidang' => self::phaseLabel('sidang'),
            'completed' => self::phaseLabel('completed'),
            'cancelled' => self::phaseLabel('cancelled'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function phaseFilterColors(): array
    {
        return collect(array_keys(self::phaseOptions()))
            ->mapWithKeys(fn(string $phase): array => [$phase => BadgeStyles::phaseColor($phase)])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function phaseFilterIcons(): array
    {
        return collect(array_keys(self::phaseOptions()))
            ->mapWithKeys(fn(string $phase): array => [$phase => BadgeStyles::phaseIcon($phase)])
            ->all();
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

    private static function canDecideTitleReview(ThesisProject $record): bool
    {
        return $record->state === 'active'
            && $record->phase === 'title_review'
            && $record->latestTitle?->status === 'submitted'
            && $record->defenses->isEmpty()
            && $record->activeSupervisorAssignments->isEmpty();
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
            WitaDateTime::translatedDateRange($defense->scheduled_for, $defense->scheduled_until),
        );
    }
}
