<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ThesisProjects\Tables\ThesisProjectsTable;
use App\Filament\Resources\ThesisProjects\ThesisProjectResource;
use App\Models\ThesisProject;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ProjectsNeedingAttentionWidget extends TableWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = [
        'xl' => 7,
    ];

    protected ?string $pollingInterval = null;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Proyek yang Perlu Tindak Lanjut')
            ->query($this->buildTableQuery())
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('student.name')
                    ->label('Mahasiswa')
                    ->description(fn(ThesisProject $record): string => $record->student?->mahasiswaProfile?->nim ?? '-')
                    ->searchable(),
                TextColumn::make('phase')
                    ->label('Fase')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => ThesisProjectsTable::phaseLabel($state)),
                TextColumn::make('attention_reason')
                    ->label('Perlu Dicek')
                    ->state(fn(ThesisProject $record): string => $this->attentionReason($record))
                    ->wrap(),
                TextColumn::make('next_agenda')
                    ->label('Agenda Terdekat')
                    ->state(fn(ThesisProject $record): string => $this->nextAgenda($record))
                    ->placeholder('-'),
            ])
            ->recordActions([
                Action::make('openProject')
                    ->label('Buka Proyek')
                    ->url(fn(ThesisProject $record): string => ThesisProjectResource::getUrl('view', ['record' => $record]))
                    ->icon('heroicon-m-arrow-top-right-on-square'),
            ]);
    }

    private function buildTableQuery(): Builder
    {
        $query = ThesisProject::query()
            ->with([
                'student.mahasiswaProfile',
                'activeSupervisorAssignments',
                'defenses',
            ])
            ->withCount([
                'revisions as open_revisions_count' => fn(Builder $builder): Builder => $builder
                    ->whereIn('status', ['open', 'submitted']),
            ])
            ->where('state', 'active')
            ->where(function (Builder $query): void {
                $query->where(function (Builder $subQuery): void {
                    $subQuery->whereIn('phase', ['research', 'sidang'])
                        ->has('activeSupervisorAssignments', '<', 2);
                })->orWhereHas('revisions', function (Builder $revisionQuery): void {
                    $revisionQuery->whereIn('status', ['open', 'submitted']);
                })->orWhere(function (Builder $subQuery): void {
                    $subQuery->whereIn('phase', ['title_review', 'sempro'])
                        ->whereDoesntHave('semproDefenses', function (Builder $defenseQuery): void {
                            $defenseQuery->whereIn('status', ['scheduled', 'completed']);
                        });
                })->orWhere(function (Builder $subQuery): void {
                    $subQuery->whereIn('phase', ['research', 'sidang'])
                        ->whereDoesntHave('sidangDefenses', function (Builder $defenseQuery): void {
                            $defenseQuery->whereIn('status', ['scheduled', 'completed']);
                        });
                });
            });

        /** @var User|null $user */
        $user = Auth::user();
        $prodiId = $user?->adminProgramStudiId();

        if ($prodiId !== null) {
            $query->where('program_studi_id', $prodiId);
        }

        return $query;
    }

    private function attentionReason(ThesisProject $record): string
    {
        if ($record->open_revisions_count > 0) {
            return 'Masih ada revisi terbuka yang perlu dipantau.';
        }

        if (in_array($record->phase, ['research', 'sidang'], true) && $record->activeSupervisorAssignments->count() < 2) {
            return 'Pembimbing aktif belum lengkap untuk fase ini.';
        }

        if (in_array($record->phase, ['title_review', 'sempro'], true) && $record->defenses->where('type', 'sempro')->whereIn('status', ['scheduled', 'completed'])->isEmpty()) {
            return 'Belum ada sempro yang dijadwalkan atau diselesaikan.';
        }

        if (in_array($record->phase, ['research', 'sidang'], true) && $record->defenses->where('type', 'sidang')->whereIn('status', ['scheduled', 'completed'])->isEmpty()) {
            return 'Belum ada sidang yang dijadwalkan atau diselesaikan.';
        }

        return 'Perlu pengecekan manual dari admin.';
    }

    private function nextAgenda(ThesisProject $record): string
    {
        $defense = $record->defenses
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_for')
            ->sortBy('scheduled_for')
            ->first();

        if ($defense === null) {
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
