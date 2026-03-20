<?php

namespace App\Filament\Resources\CsatResponses\Tables;

use App\Models\CsatResponse;
use App\Models\ProgramStudi;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ColumnManagerResetActionPosition;
use Filament\Tables\Enums\FiltersResetActionPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CsatResponsesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('created_at')
                    ->label('Dikirim')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label('Responden')
                    ->description(fn(CsatResponse $record): string => collect([
                        CsatResponse::respondentRoleLabel($record->respondent_role),
                        $record->programStudi?->name,
                    ])->filter()->implode(' · '))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('score')
                    ->label('Skor')
                    ->badge()
                    ->formatStateUsing(fn(int $state): string => $state.'/5')
                    ->color(fn(int $state): string => self::scoreColor($state))
                    ->sortable(),
                TextColumn::make('kritik')
                    ->label('Kritik')
                    ->placeholder('-')
                    ->limit(80)
                    ->wrap()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('saran')
                    ->label('Saran')
                    ->placeholder('-')
                    ->limit(80)
                    ->wrap()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('score')
                    ->label('Skor')
                    ->options([
                        '1' => '1/5',
                        '2' => '2/5',
                        '3' => '3/5',
                        '4' => '4/5',
                        '5' => '5/5',
                    ]),
                SelectFilter::make('respondent_role')
                    ->label('Peran')
                    ->options(CsatResponse::respondentRoleOptions())
                    ->native(false),
                SelectFilter::make('program_studi_id')
                    ->label('Program Studi')
                    ->options(fn(): array => ProgramStudi::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->visible(function (): bool {
                        /** @var User|null $user */
                        $user = Auth::user();

                        return $user?->hasRole('super_admin') ?? false;
                    })
                    ->native(false)
                    ->searchable()
                    ->preload(),
                Filter::make('last_30_days')
                    ->label('30 hari terakhir')
                    ->toggle()
                    ->query(fn(Builder $query): Builder => $query->recent()),
                Filter::make('submitted_between')
                    ->label('Rentang tanggal')
                    ->schema([
                        DatePicker::make('submitted_from')
                            ->label('Dari tanggal'),
                        DatePicker::make('submitted_until')
                            ->label('Sampai tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['submitted_from'] ?? null),
                                fn(Builder $builder): Builder => $builder->whereDate('created_at', '>=', $data['submitted_from']),
                            )
                            ->when(
                                filled($data['submitted_until'] ?? null),
                                fn(Builder $builder): Builder => $builder->whereDate('created_at', '<=', $data['submitted_until']),
                            );
                    }),
            ])
            ->filtersFormColumns(2)
            ->filtersFormWidth(Width::FourExtraLarge)
            ->filtersTriggerAction(fn(Action $action): Action => $action->button()->label('Filter CSAT'))
            ->filtersApplyAction(fn(Action $action): Action => $action->label('Terapkan Filter'))
            ->filtersResetActionPosition(FiltersResetActionPosition::Footer)
            ->columnManagerColumns(2)
            ->columnManagerResetActionPosition(ColumnManagerResetActionPosition::Footer);
    }

    private static function scoreColor(int $score): string
    {
        return match (true) {
            $score <= 2 => 'danger',
            $score === 3 => 'warning',
            default => 'success',
        };
    }
}
