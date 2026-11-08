<?php

namespace App\Filament\Resources\ThesisProjects\Tables;

use App\Filament\Resources\ThesisProjects\Pages\ListThesisProjects;
use App\Filament\Resources\ThesisProjects\ThesisProjectResource;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Policies\SidangPaperPolicy;
use App\Services\AdminActionQueueService;
use App\Services\SidangPaperService;
use App\Services\SidangScoreService;
use App\Services\ThesisProjectAdminService;
use App\Support\AcademicTerminology;
use App\Support\Filament\BadgeStyles;
use App\Support\WitaDateTime;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ThesisProjectsTable
{
    public static function configure(Table $table): Table
    {
        $isSidangTab = fn (ListThesisProjects $livewire): bool => $livewire->activeTab === ListThesisProjects::SIDANG_PAPER_TAB;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('latestSidangDefense.examiners'))
            ->defaultSort(function (Builder $query, ListThesisProjects $livewire) use ($isSidangTab): Builder {
                if ($isSidangTab($livewire)) {
                    $query->orderBy(ThesisDefense::query()
                        ->selectRaw("case status when 'awaiting_finalization' then 0 when 'scheduled' then 1 else 2 end")
                        ->whereColumn('project_id', 'thesis_projects.id')
                        ->where('type', 'sidang')->latest('attempt_no')->limit(1));
                }

                return $query->latest('started_at');
            }, 'desc')
            ->columns([
                TextColumn::make('student.name')
                    ->label('Mahasiswa')
                    ->wrap($isSidangTab)
                    ->description(fn (ThesisProject $record, ListThesisProjects $livewire): ?string => $isSidangTab($livewire)
                        ? $record->programStudi?->name
                        : null)
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('student.mahasiswaProfile.nim')
                    ->label('NIM')
                    ->copyable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('student.email')
                    ->label('Email Mahasiswa')
                    ->hidden($isSidangTab)
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('student.phone_number')
                    ->label('Nomor HP')
                    ->hidden($isSidangTab)
                    ->copyable()
                    ->description(fn(?ThesisProject $record): ?string => match (true) {
                        blank($record?->student?->phone_number) => null,
                        $record->student->phone_number_confirmed_at === null => 'Belum dikonfirmasi',
                        default => null,
                    })
                    ->placeholder('Belum diisi')
                    ->toggleable(),
                TextColumn::make('programStudi.name')
                    ->label('Program Studi')
                    ->hidden($isSidangTab)
                    ->badge()
                    ->icon(BadgeStyles::programStudiIcon())
                    ->color(fn(?string $state): string => BadgeStyles::programStudiColor($state))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('current_title')
                    ->label('Judul Aktif')
                    ->hidden($isSidangTab)
                    ->state(fn(ThesisProject $record): string => self::resolveCurrentTitle($record)?->title_id ?? '-')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('titles', fn(Builder $builder): Builder => $builder
                            ->whereLike('title_id', "%{$search}%")
                            ->orWhereLike('title_en', "%{$search}%"));
                    })
                    ->wrap()
                    ->limit(60)
                    ->toggleable(),
                TextColumn::make('phase')
                    ->label('Fase')
                    ->hidden($isSidangTab)
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => self::phaseLabel($state))
                    ->color(fn(string $state): string => BadgeStyles::phaseColor($state))
                    ->icon(fn(string $state): string => BadgeStyles::phaseIcon($state))
                    ->toggleable(),
                TextColumn::make('state')
                    ->label('State')
                    ->hidden($isSidangTab)
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
                    ->label(AcademicTerminology::neutral()['proposalExamShort'])
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sidang_attempts_count')
                    ->label(AcademicTerminology::neutral()['finalExam'])
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
                    ->hidden($isSidangTab)
                    ->state(fn(ThesisProject $record): string => self::formatNextAgenda($record))
                    ->toggleable(),
                TextColumn::make('latestSidangDefense.scheduled_for')
                    ->label('Jadwal '.AcademicTerminology::neutral()['finalExamShort'])
                    ->visible($isSidangTab)
                    ->formatStateUsing(fn ($state): ?string => WitaDateTime::translated($state, 'd M Y H:i')),
                TextColumn::make('latestSidangDefense.status')
                    ->label('Status Berkas')
                    ->visible($isSidangTab)
                    ->badge()
                    ->description(fn (ThesisProject $record): ?string => filled($record->latestSidangDefense?->sidang_sk)
                        ? 'SK '.Str::limit($record->latestSidangDefense->sidang_sk, 13) : null)
                    ->tooltip(fn (ThesisProject $record): ?string => $record->latestSidangDefense?->sidang_sk)
                    ->formatStateUsing(fn (string $state): string => self::sidangPaperStatusLabels()[$state] ?? $state)
                    ->color(fn (?string $state): string => BadgeStyles::domainStatusColor('defense', $state)),
                TextColumn::make('mentorship_ticket')
                    ->label('Masa aktif bimbingan')
                    ->hidden($isSidangTab)
                    ->badge()
                    ->state(fn(ThesisProject $record): string => self::mentorshipTicketLabel($record))
                    ->color(fn(ThesisProject $record): string => $record->isMentorshipSuspended() ? 'danger' : 'success')
                    ->toggleable(),
                TextColumn::make('archive_status')
                    ->label('Arsip')
                    ->hidden($isSidangTab)
                    ->badge()
                    ->state(fn(ThesisProject $record): ?string => self::archiveLabel($record))
                    ->color(fn(ThesisProject $record): string => $record->isArchived() ? 'gray' : 'warning')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('started_at')
                    ->label('Mulai')
                    ->formatStateUsing(fn($state): string => WitaDateTime::translated($state, 'd M Y', withLabel: false))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('sidang_paper_status')
                    ->label('Status Berkas')
                    ->visible($isSidangTab)
                    ->options(self::sidangPaperStatusLabels())
                    ->query(function (Builder $query, array $data): Builder {
                        $status = $data['value'] ?? null;

                        return is_string($status) && isset(self::sidangPaperStatusLabels()[$status])
                            ? $query->whereRelation('latestSidangDefense', 'status', $status)
                            : $query;
                    }),
                SelectFilter::make(AdminActionQueueService::FILTER_NAME)
                    ->label('Perlu tindakan')
                    ->options(fn(): array => app(AdminActionQueueService::class)->options())
                    ->query(fn(Builder $query, array $data): Builder => app(AdminActionQueueService::class)
                        ->applyQueue($query, $data['value'] ?? null)),
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
                SelectFilter::make('mentorship_ticket')
                    ->label('Masa aktif bimbingan')
                    ->options([
                        'expired' => 'Masa aktif habis',
                        'unset' => 'Belum diatur',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'expired' => $query
                                ->where('state', 'active')
                                ->whereNotNull('mentorship_valid_until')
                                ->whereDate('mentorship_valid_until', '<', now(WitaDateTime::TIMEZONE)->toDateString()),
                            'unset' => $query->whereNull('mentorship_valid_until'),
                            default => $query,
                        };
                    }),
                SelectFilter::make('archive')
                    ->label('Arsip')
                    ->options([
                        'archived' => 'Diarsipkan',
                        'due' => 'Akan diarsipkan (dalam '.(int) config('sita.archive_grace_days', 30).' hari)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'archived' => $query->whereNotNull('archived_at'),
                            'due' => $query->where('state', 'completed')->whereNull('archived_at'),
                            default => $query,
                        };
                    }),
            ])
            ->filtersFormColumns(2)
            ->filtersFormWidth(Width::FiveExtraLarge)
            ->filtersTriggerAction(fn(Action $action): Action => $action
                ->button()
                ->label('Filter'))
            ->filtersApplyAction(fn(Action $action): Action => $action->label('Terapkan Filter'))
            ->filtersResetActionPosition(FiltersResetActionPosition::Footer)
            ->columnManagerColumns(2)
            ->columnManagerResetActionPosition(ColumnManagerResetActionPosition::Footer)
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('isi_nomor_sk')
                        ->label('Isi No SK')
                        ->icon('heroicon-m-document-text')
                        ->visible($isSidangTab)
                        ->modalHeading('Isi No SK bersama')
                        ->modalDescription('Satu nomor dari Adum untuk mahasiswa terpilih dalam satu prodi. Pilih sidang yang menunggu finalisasi atau sudah selesai.')
                        ->modalSubmitActionLabel('Simpan No SK')
                        ->schema([TextInput::make('sidang_sk')->label('No SK')->required()->maxLength(150)])
                        ->action(function (Collection $records, array $data, BulkAction $action): void {
                            $actor = Auth::user();
                            abort_if($actor === null, 403);
                            try {
                                $count = app(SidangPaperService::class)->assignSk($actor, $records, $data['sidang_sk']);
                            } catch (ValidationException $exception) {
                                Notification::make()->title('No SK belum disimpan')->body($exception->getMessage())->danger()->send();
                                $action->halt();

                                return;
                            }
                            Notification::make()->title('No SK disimpan untuk '.$count.' mahasiswa')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('renew_mentorship_tickets')
                        ->label('Perpanjang masa aktif 1 tahun')
                        ->hidden($isSidangTab)
                        ->icon('heroicon-m-arrow-path')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $userId = Auth::id();

                            if ($userId === null) {
                                return;
                            }

                            $count = app(ThesisProjectAdminService::class)->renewMentorshipTickets($records, $userId);

                            Notification::make()
                                ->title($count.' masa aktif bimbingan diperpanjang 1 tahun')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->recordActions([
                Action::make('cetak_berkas_sidang')
                    ->label('Cetak / PDF')->icon('heroicon-m-printer')->iconButton()->tooltip('Cetak / PDF')->color('gray')
                    ->visible(fn (ThesisProject $record): bool => self::canAccessSidangPaper($record))
                    ->url(fn (ThesisProject $record): string => route('sidang.paper.preview', $record->latestSidangDefense))
                    ->openUrlInNewTab(),
                Action::make('unduh_berkas_sidang')
                    ->label('Unduh Word')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->iconButton()->tooltip('Unduh Word')
                    ->color('gray')
                    ->visible(fn (ThesisProject $record): bool => self::canAccessSidangPaper($record))
                    ->url(fn (ThesisProject $record): string => route('sidang.paper.download', $record->latestSidangDefense)),
                Action::make('tindakan_berkas_sidang')
                    ->label(fn (ThesisProject $record): string => match (self::sidangPaperActionStatus($record)) {
                        'awaiting_finalization' => 'Tinjau & Finalisasi',
                        'scheduled' => 'Input Nilai',
                        default => 'Lihat Berkas',
                    })
                    ->icon(fn (ThesisProject $record): string => match (self::sidangPaperActionStatus($record)) {
                        'awaiting_finalization' => 'heroicon-m-check-circle',
                        'scheduled' => 'heroicon-m-pencil-square',
                        default => 'heroicon-m-eye',
                    })
                    ->visible(fn (ThesisProject $record): bool => self::canAccessSidangPaper($record))
                    ->url(function (ThesisProject $record): string {
                        $defense = $record->latestSidangDefense;
                        $status = self::sidangPaperActionStatus($record);

                        return ThesisProjectResource::getUrl('sidang-paper', ['record' => $record, 'defense' => $defense, 'dari' => 'daftar'])
                            .(in_array($status, ['scheduled', 'awaiting_finalization'], true) ? '#nilai-dari-berkas' : '');
                    }),
                Action::make('approve_title')
                    ->label('Setujui')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->visible(fn(ThesisProject $record): bool => self::canDecideTitleReview($record))
                    ->form([
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->default(fn (ThesisProject $record): string => 'Judul dan '.mb_strtolower(AcademicTerminology::forProject($record)['proposalDocument']).' disetujui. Mahasiswa dapat lanjut ke '.AcademicTerminology::forProject($record)['proposalPhase'].'.')
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
                    ->modalDescription(AcademicTerminology::neutral()['finalWork'].' akan ditandai dibatalkan dan mahasiswa dapat mengajukan judul baru.')
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
                Action::make('renew_mentorship_ticket')
                    ->label('Perpanjang masa aktif')
                    ->icon('heroicon-m-arrow-path')
                    ->color('warning')
                    ->visible(fn(ThesisProject $record): bool => $record->isMentorshipSuspended())
                    ->requiresConfirmation()
                    ->modalHeading('Perpanjang masa aktif bimbingan?')
                    ->modalDescription('Masa aktif diperpanjang 1 tahun dari hari ini, dan bimbingan mahasiswa berjalan lagi.')
                    ->action(function (ThesisProject $record): void {
                        $userId = Auth::id();

                        if ($userId === null) {
                            return;
                        }

                        app(ThesisProjectAdminService::class)->renewMentorshipTicket($record, $userId);

                        Notification::make()
                            ->title('Masa aktif bimbingan diperpanjang 1 tahun')
                            ->success()
                            ->send();
                    }),
                Action::make('edit')
                    ->label('Edit')
                    ->hidden($isSidangTab)
                    ->icon('heroicon-m-pencil-square')
                    ->url(fn(ThesisProject $record): string => ThesisProjectResource::getUrl('edit', ['record' => $record])),
                ViewAction::make()->hidden($isSidangTab),
            ]);
    }

    private static function sidangPaperStatusLabels(): array
    {
        return [
            'scheduled' => 'Terjadwal',
            'awaiting_finalization' => 'Menunggu Finalisasi',
            'completed' => 'Selesai',
        ];
    }

    private static function sidangPaperActionStatus(ThesisProject $record): ?string
    {
        $defense = $record->latestSidangDefense;
        if ($record->state !== 'active' || $defense === null) {
            return null;
        }

        return $defense->status === 'scheduled' && ! app(SidangScoreService::class)->hasPaperPanel($defense)
            ? null
            : $defense->status;
    }

    private static function canAccessSidangPaper(ThesisProject $record): bool
    {
        $defense = $record->latestSidangDefense;
        $user = Auth::user();

        return $user !== null && $record->state !== 'cancelled'
            && $defense?->scheduled_for !== null
            && in_array($defense->status, ListThesisProjects::SIDANG_PAPER_STATUSES, true)
            && app(SidangPaperPolicy::class)->manage($user, (int) $record->program_studi_id);
    }

    public static function mentorshipTicketLabel(ThesisProject $record): string
    {
        if ($record->isMentorshipSuspended()) {
            return 'Masa aktif habis';
        }

        if ($record->mentorship_valid_until === null) {
            return 'Belum diatur';
        }

        return 'Berlaku s.d. '.WitaDateTime::translated($record->mentorship_valid_until, 'd M Y', withLabel: false);
    }

    public static function archiveLabel(ThesisProject $record): ?string
    {
        if ($record->isArchived()) {
            return 'Diarsipkan '.WitaDateTime::translated($record->archived_at, 'd M Y', withLabel: false);
        }

        $dueAt = $record->archiveDueAt();

        if ($dueAt === null) {
            return null;
        }

        $days = (int) ceil(now()->diffInDays($dueAt, false));

        return $days > 0 ? 'Diarsipkan dalam '.$days.' hari' : 'Menunggu arsip';
    }

    public static function phaseLabel(string $phase): string
    {
        return match ($phase) {
            'title_review', 'sempro', 'research', 'sidang', 'completed' => AcademicTerminology::phaseLabel($phase),
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
