<?php

namespace App\Filament\Resources\SystemAuditLogs\Tables;

use App\Models\SystemAuditLog;
use App\Models\User;
use App\Support\Filament\BadgeStyles;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ColumnManagerResetActionPosition;
use Filament\Tables\Enums\FiltersResetActionPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SystemAuditLogsTable
{
    /**
     * Event types whose description carries detail (target role, prodi name, reset outcome) absent from the label.
     * Shown as a line under the activity label; other descriptions remain available as a tooltip.
     */
    private const array DETAIL_EVENT_TYPES = [
        'role_switched',
        'student_guide_updated',
        'password_reset_requested',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->paginated([10, 15, 25, 50, 100])
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Waktu')
                    ->since()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('label')
                    ->label('Aktivitas')
                    ->description(fn(SystemAuditLog $record): ?string => in_array($record->event_type, self::DETAIL_EVENT_TYPES, true) ? $record->description : null)
                    ->tooltip(fn(SystemAuditLog $record): ?string => $record->description)
                    ->searchable()
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label('Pengguna')
                    ->description(fn(SystemAuditLog $record): string => $record->email ?? '-')
                    ->placeholder('Anonim')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('event_type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => BadgeStyles::auditEventLabel($state))
                    ->color(fn(string $state): string => BadgeStyles::auditEventColor($state))
                    ->icon(fn(string $state): string => BadgeStyles::auditEventIcon($state))
                    ->toggleable(),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('event_type')
                    ->label('Jenis Audit')
                    ->options(fn(): array => SystemAuditLog::query()
                        ->distinct()
                        ->orderBy('event_type')
                        ->pluck('event_type', 'event_type')
                        ->mapWithKeys(fn(string $eventType): array => [$eventType => BadgeStyles::auditEventLabel($eventType)])
                        ->all())
                    ->native(false),
                SelectFilter::make('user_id')
                    ->label('Pengguna')
                    ->visible(function (): bool {
                        /** @var User|null $user */
                        $user = Auth::user();

                        return $user?->hasRole('super_admin') ?? false;
                    })
                    ->relationship('user', 'name')
                    ->native(false)
                    ->searchable()
                    ->preload(),
            ])
            ->filtersFormColumns(2)
            ->filtersFormWidth(Width::FourExtraLarge)
            ->filtersTriggerAction(fn(Action $action): Action => $action->button()->label('Filter Audit Sistem'))
            ->filtersApplyAction(fn(Action $action): Action => $action->label('Terapkan Filter'))
            ->filtersResetActionPosition(FiltersResetActionPosition::Footer)
            ->columnManagerColumns(2)
            ->columnManagerResetActionPosition(ColumnManagerResetActionPosition::Footer);
    }
}
