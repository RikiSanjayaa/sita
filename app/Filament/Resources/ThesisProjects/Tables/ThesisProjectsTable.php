<?php

namespace App\Filament\Resources\ThesisProjects\Tables;

use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
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
                    ->description(fn(?ThesisProject $record): string => $record?->student?->mahasiswaProfile?->nim ?? '-'),
                TextColumn::make('programStudi.name')
                    ->label('Program Studi')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('current_title')
                    ->label('Judul Aktif')
                    ->state(fn(ThesisProject $record): string => self::resolveCurrentTitle($record)?->title_id ?? '-')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('titles', fn(Builder $builder): Builder => $builder
                            ->where('title_id', 'like', "%{$search}%")
                            ->orWhere('title_en', 'like', "%{$search}%"));
                    })
                    ->wrap()
                    ->limit(60),
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
                    ->formatStateUsing(fn(string $state): string => self::phaseLabel($state)),
                BadgeColumn::make('state')
                    ->label('State')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'on_hold',
                        'gray' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn(string $state): string => self::stateLabel($state)),
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
                    ->placeholder('-'),
                TextColumn::make('sempro_attempts_count')
                    ->label('Sempro')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sidang_attempts_count')
                    ->label('Sidang')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('open_revisions_count')
                    ->label('Revisi Terbuka')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('next_agenda')
                    ->label('Agenda Terdekat')
                    ->state(fn(ThesisProject $record): string => self::formatNextAgenda($record))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('started_at')
                    ->label('Mulai')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
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
