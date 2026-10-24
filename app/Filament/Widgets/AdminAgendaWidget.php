<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ThesisProjects\ThesisProjectResource;
use App\Models\ThesisDefense;
use App\Models\User;
use App\Support\AcademicTerminology;
use App\Support\WitaDateTime;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AdminAgendaWidget extends TableWidget
{
    public const DAYS_AHEAD = 7;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $table
            ->heading('Agenda ujian '.self::DAYS_AHEAD.' hari ke depan')
            ->query(self::agendaQuery($user))
            ->defaultSort('scheduled_for')
            ->paginated(false)
            ->emptyStateHeading('Tidak ada ujian terjadwal')
            ->emptyStateDescription('Tidak ada ujian yang dijadwalkan dalam '.self::DAYS_AHEAD.' hari ke depan.')
            ->emptyStateIcon('heroicon-o-calendar')
            ->columns([
                TextColumn::make('scheduled_for')
                    ->label('Waktu')
                    ->state(fn (ThesisDefense $record): string => WitaDateTime::translated($record->scheduled_for, 'D, j M Y H:i'))
                    ->description(fn (ThesisDefense $record): ?string => $record->scheduled_until === null
                        ? null
                        : 'sampai '.WitaDateTime::translated($record->scheduled_until, 'H:i'))
                    ->extraHeaderAttributes(['class' => 'whitespace-nowrap'])
                    ->wrap(),
                TextColumn::make('project.student.name')
                    ->label('Mahasiswa')
                    ->description(fn (ThesisDefense $record): string => $record->project?->student?->mahasiswaProfile?->nim ?? '-'),
                TextColumn::make('type')
                    ->label('Ujian')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'sidang' ? 'primary' : 'info')
                    ->formatStateUsing(fn (ThesisDefense $record): string => AcademicTerminology::defenseLabel(
                        AcademicTerminology::forProject($record->project),
                        $record->type,
                    )),
                TextColumn::make('location')
                    ->label('Lokasi')
                    ->state(fn (ThesisDefense $record): string => trim(implode(' - ', array_filter([$record->location, $record->mode]))) ?: '-')
                    ->wrap(),
            ])
            ->recordActions([
                Action::make('openProject')
                    ->label('Buka')
                    ->url(fn (ThesisDefense $record): string => ThesisProjectResource::getUrl('view', ['record' => $record->project]))
                    ->icon('heroicon-m-arrow-top-right-on-square'),
            ]);
    }

    /**
     * Scheduled ujian from now through the next seven days, scoped to the admin's program studi.
     *
     * @return Builder<ThesisDefense>
     */
    public static function agendaQuery(?User $user): Builder
    {
        $query = ThesisDefense::query()
            ->with(['project.student.mahasiswaProfile', 'project.programStudi'])
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_for')
            ->whereBetween('scheduled_for', [now(), now()->copy()->addDays(self::DAYS_AHEAD)]);

        if ($user?->adminProgramStudiId() !== null) {
            $query->whereHas('project', fn (Builder $project): Builder => $project
                ->where('program_studi_id', $user->adminProgramStudiId()));
        }

        return $query;
    }
}
