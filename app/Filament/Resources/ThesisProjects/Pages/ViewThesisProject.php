<?php

namespace App\Filament\Resources\ThesisProjects\Pages;

use App\Filament\Resources\ThesisProjects\ThesisProjectResource;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisSupervisorAssignment;
use App\Services\LecturerSearchService;
use App\Services\ThesisProjectAdminService;
use App\Support\AcademicTerminology;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\Auth;

class ViewThesisProject extends ViewRecord
{
    protected static string $resource = ThesisProjectResource::class;

    protected ?Alignment $headerActionsAlignment = Alignment::End;

    protected function getHeaderActions(): array
    {
        /** @var ThesisProject $record */
        $record = $this->record;
        $terms = AcademicTerminology::forProject($record);

        $workflowActions = [
            Action::make('approve_title')
                ->label('Setujui Judul')
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->visible(fn(): bool => $this->canDecideTitleReview($record))
                ->form([
                    Textarea::make('notes')
                        ->label('Catatan')
                        ->default('Judul dan proposal disetujui. Mahasiswa dapat lanjut ke tahap sempro.')
                        ->rows(3),
                ])
                ->action(function (array $data) use ($record): void {
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

                        $this->redirect(ThesisProjectResource::getUrl('view', ['record' => $record->getKey()]));
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Gagal menyetujui judul')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('reject_title')
                ->label('Tidak Setujui Judul')
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->visible(fn(): bool => $this->canDecideTitleReview($record))
                ->requiresConfirmation()
                ->modalHeading('Tolak pengajuan judul?')
                ->modalDescription('Proyek akan ditandai dibatalkan dan mahasiswa dapat mengajukan judul baru.')
                ->form([
                    Textarea::make('notes')
                        ->label('Alasan')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) use ($record): void {
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

                        $this->redirect(ThesisProjectResource::getUrl('view', ['record' => $record->getKey()]));
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Gagal menolak judul')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('schedule_sempro')
                ->label(fn(): string => $this->latestEditableSempro($record) instanceof ThesisDefense
                    ? 'Ubah Jadwal '.$terms['proposalExamShort']
                    : 'Jadwalkan '.$terms['proposalExamShort'])
                ->icon('heroicon-m-calendar')
                ->color('info')
                ->visible(fn(): bool => $this->canScheduleDefense($record, 'sempro'))
                ->form([
                    Grid::make([
                        'default' => 1,
                        'md' => 3,
                    ])
                        ->schema([
                            DatePicker::make('scheduled_starts_on')
                                ->label('Tanggal Mulai')
                                ->required()
                                ->default(fn(): ?string => $this->latestEditableSempro($record)?->scheduled_for?->format('Y-m-d'))
                                ->native(false),
                            DatePicker::make('scheduled_ends_on')
                                ->label('Tanggal Akhir (Opsional)')
                                ->hintIcon(
                                    'heroicon-m-question-mark-circle',
                                    'Isi hanya jika jadwal masih berupa rentang tanggal. Kosongkan jika jadwal sudah pasti pada tanggal mulai.',
                                )
                                ->afterOrEqual('scheduled_starts_on')
                                ->default(fn(): ?string => $this->latestEditableSempro($record)?->scheduled_until?->format('Y-m-d'))
                                ->native(false),
                            TimePicker::make('scheduled_at_time')
                                ->label('Jam')
                                ->seconds(false)
                                ->required()
                                ->default(fn(): ?string => $this->latestEditableSempro($record)?->scheduled_for?->format('H:i'))
                                ->native(false),
                        ]),
                    TextInput::make('location')
                        ->label('Lokasi')
                        ->default(fn(): ?string => $this->latestEditableSempro($record)?->location)
                        ->required()
                        ->maxLength(255),
                    Select::make('mode')
                        ->label('Mode')
                        ->options([
                            'offline' => 'Offline',
                            'online' => 'Online',
                            'hybrid' => 'Hybrid',
                        ])
                        ->default(fn(): string => $this->latestEditableSempro($record)?->mode ?? 'offline')
                        ->required()
                        ->native(false),
                    Select::make('examiner_1')
                        ->label('Penguji 1')
                        ->hintIcon(
                            'heroicon-m-question-mark-circle',
                            'Wajib diisi. Pencarian mencakup seluruh dosen aktif dan dapat menggunakan nama, NIK, prodi, konsentrasi, atau bidang keilmuan.',
                        )
                        ->searchable()
                        ->getSearchResultsUsing(fn(string $search): array => app(LecturerSearchService::class)->filamentOptions($record, $search, 'examiner'))
                        ->getOptionLabelUsing(fn($value): ?string => app(LecturerSearchService::class)->filamentOptionLabel($record, $value, 'examiner'))
                        ->searchPrompt('Ketik minimal 2 karakter nama, NIK, prodi, konsentrasi, atau bidang keilmuan')
                        ->required()
                        ->helperText('Minimal 1 penguji. D3 umumnya 1 penguji; S1/S2 disarankan 2, mengikuti kebijakan prodi.')
                        ->native(false),
                    Select::make('examiner_2')
                        ->label('Penguji 2 (Opsional)')
                        ->hintIcon(
                            'heroicon-m-question-mark-circle',
                            'Opsional. Jika dipilih, harus berbeda dari Penguji 1. D3 umumnya menggunakan satu penguji; kebijakan akhir mengikuti prodi.',
                        )
                        ->searchable()
                        ->getSearchResultsUsing(fn(string $search): array => app(LecturerSearchService::class)->filamentOptions($record, $search, 'examiner'))
                        ->getOptionLabelUsing(fn($value): ?string => app(LecturerSearchService::class)->filamentOptionLabel($record, $value, 'examiner'))
                        ->searchPrompt('Ketik minimal 2 karakter untuk mencari dosen')
                        ->nullable()
                        ->different('examiner_1')
                        ->native(false),
                ])
                ->action(function (array $data) use ($record, $terms): void {
                    $userId = Auth::id();

                    if ($userId === null) {
                        return;
                    }

                    try {
                        $scheduledFor = $data['scheduled_starts_on'].' '.$data['scheduled_at_time'];
                        $scheduledUntil = filled($data['scheduled_ends_on'] ?? null)
                            ? $data['scheduled_ends_on'].' '.$data['scheduled_at_time']
                            : null;

                        app(ThesisProjectAdminService::class)->scheduleSempro(
                            project: $record,
                            scheduledBy: $userId,
                            scheduledFor: $scheduledFor,
                            location: (string) $data['location'],
                            mode: (string) $data['mode'],
                            examinerUserIds: collect([
                                $data['examiner_1'],
                                $data['examiner_2'] ?? null,
                            ])->filter(fn($id): bool => filled($id))
                                ->map(fn($id): int => (int) $id)
                                ->values()
                                ->all(),
                            scheduledUntil: $scheduledUntil,
                        );

                        Notification::make()
                            ->title($terms['proposalExamShort'].' berhasil dijadwalkan')
                            ->success()
                            ->send();

                        $this->redirect(ThesisProjectResource::getUrl('view', ['record' => $record->getKey()]));
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Gagal menjadwalkan sempro')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('finalize_sempro')
                ->label('Catat Hasil '.$terms['proposalExamShort'])
                ->icon('heroicon-m-check-badge')
                ->color('success')
                ->visible(fn(): bool => $record->state === 'active' && $this->latestSempro($record) !== null)
                ->disabled(fn(): bool => $this->latestSempro($record)?->status !== 'awaiting_finalization')
                ->tooltip(fn(): ?string => $this->finalizeSemproTooltip($record))
                ->form([
                    Select::make('result')
                        ->label('Hasil')
                        ->options([
                            'pass_with_revision' => 'Lulus',
                            'fail' => 'Tidak Lulus',
                        ])
                        ->required()
                        ->native(false),
                    DateTimePicker::make('revision_due_at')
                        ->label('Batas Revisi')
                        ->helperText('Wajib diisi jika hasil Lulus.')
                        ->required(fn(Get $get): bool => $get('result') === 'pass_with_revision'),
                    Textarea::make('notes')
                        ->label('Catatan')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) use ($record): void {
                    $userId = Auth::id();

                    if ($userId === null) {
                        return;
                    }

                    try {
                        app(ThesisProjectAdminService::class)->finalizeSempro(
                            project: $record,
                            decidedBy: $userId,
                            result: (string) $data['result'],
                            notes: (string) $data['notes'],
                            revisionDueAt: filled($data['revision_due_at'] ?? null) ? (string) $data['revision_due_at'] : null,
                        );

                        Notification::make()
                            ->title('Hasil sempro berhasil dicatat')
                            ->success()
                            ->send();

                        $this->redirect(ThesisProjectResource::getUrl('view', ['record' => $record->getKey()]));
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Gagal mencatat hasil sempro')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('assign_supervisors')
                ->label('Tetapkan Pembimbing')
                ->icon('heroicon-m-user-plus')
                ->color('primary')
                ->visible(fn(): bool => $record->state === 'active')
                ->form([
                    Select::make('pembimbing_1')
                        ->label('Pembimbing 1')
                        ->hintIcon(
                            'heroicon-m-question-mark-circle',
                            'Semua dosen aktif dapat dicari. Konsentrasi dan bidang keilmuan merupakan informasi pendukung, bukan syarat kesamaan dengan mahasiswa.',
                        )
                        ->searchable()
                        ->getSearchResultsUsing(fn(string $search): array => app(LecturerSearchService::class)->filamentOptions($record, $search, 'supervisor'))
                        ->getOptionLabelUsing(fn($value): ?string => app(LecturerSearchService::class)->filamentOptionLabel($record, $value, 'supervisor'))
                        ->searchPrompt('Ketik minimal 2 karakter nama, NIK, prodi, konsentrasi, atau bidang keilmuan')
                        ->required()
                        ->native(false),
                    Select::make('pembimbing_2')
                        ->label('Pembimbing 2')
                        ->hintIcon(
                            'heroicon-m-question-mark-circle',
                            'Harus berbeda dari Pembimbing 1. Dosen yang sudah mencapai kuota tidak dapat menerima mahasiswa baru.',
                        )
                        ->searchable()
                        ->getSearchResultsUsing(fn(string $search): array => app(LecturerSearchService::class)->filamentOptions($record, $search, 'supervisor'))
                        ->getOptionLabelUsing(fn($value): ?string => app(LecturerSearchService::class)->filamentOptionLabel($record, $value, 'supervisor'))
                        ->searchPrompt('Ketik minimal 2 karakter untuk mencari dosen')
                        ->required()
                        ->different('pembimbing_1')
                        ->native(false),
                    Textarea::make('notes')
                        ->label('Catatan')
                        ->rows(2),
                ])
                ->action(function (array $data) use ($record): void {
                    $userId = Auth::id();

                    if ($userId === null) {
                        return;
                    }

                    try {
                        app(ThesisProjectAdminService::class)->assignSupervisors(
                            project: $record,
                            assignedBy: $userId,
                            primaryLecturerUserId: (int) $data['pembimbing_1'],
                            secondaryLecturerUserId: (int) $data['pembimbing_2'],
                            notes: $data['notes'] ?? null,
                        );

                        Notification::make()
                            ->title('Pembimbing berhasil diperbarui')
                            ->success()
                            ->send();

                        $this->redirect(ThesisProjectResource::getUrl('view', ['record' => $record->getKey()]));
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Gagal memperbarui pembimbing')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('schedule_sidang')
                ->label(fn(): string => $this->latestEditableSidang($record) instanceof ThesisDefense
                    ? 'Ubah Jadwal '.$terms['finalExam']
                    : 'Jadwalkan '.$terms['finalExam'])
                ->icon('heroicon-m-clipboard-document-check')
                ->color('warning')
                ->visible(fn(): bool => $this->canScheduleDefense($record, 'sidang'))
                ->form([
                    Grid::make([
                        'default' => 1,
                        'md' => 3,
                    ])
                        ->schema([
                            DatePicker::make('scheduled_starts_on')
                                ->label('Tanggal Mulai')
                                ->required()
                                ->default(fn(): ?string => $this->latestEditableSidang($record)?->scheduled_for?->format('Y-m-d'))
                                ->native(false),
                            DatePicker::make('scheduled_ends_on')
                                ->label('Tanggal Akhir (Opsional)')
                                ->hintIcon(
                                    'heroicon-m-question-mark-circle',
                                    'Isi hanya jika jadwal masih berupa rentang tanggal. Kosongkan jika jadwal sudah pasti pada tanggal mulai.',
                                )
                                ->afterOrEqual('scheduled_starts_on')
                                ->default(fn(): ?string => $this->latestEditableSidang($record)?->scheduled_until?->format('Y-m-d'))
                                ->native(false),
                            TimePicker::make('scheduled_at_time')
                                ->label('Jam Tetap')
                                ->seconds(false)
                                ->required()
                                ->default(fn(): ?string => $this->latestEditableSidang($record)?->scheduled_for?->format('H:i'))
                                ->native(false),
                        ]),
                    TextInput::make('location')
                        ->label('Lokasi')
                        ->default(fn(): ?string => $this->latestEditableSidang($record)?->location)
                        ->required()
                        ->maxLength(255),
                    Select::make('mode')
                        ->label('Mode')
                        ->options([
                            'offline' => 'Offline',
                            'online' => 'Online',
                            'hybrid' => 'Hybrid',
                        ])
                        ->default(fn(): string => $this->latestEditableSidang($record)?->mode ?? 'offline')
                        ->required()
                        ->native(false),
                    Textarea::make('active_supervisors')
                        ->label('Pembimbing Aktif')
                        ->default(fn(): string => $this->activeSupervisorSummary($record) ?: 'Belum ada pembimbing aktif')
                        ->disabled()
                        ->dehydrated(false)
                        ->rows(2),
                    Select::make('additional_examiner_user_ids')
                        ->label('Dosen Penguji '.$terms['finalExam'])
                        ->hintIcon(
                            'heroicon-m-question-mark-circle',
                            'Pembimbing aktif otomatis masuk panel. Pilih dosen tambahan yang belum ada di panel; bidang keilmuan ditampilkan sebagai pertimbangan.',
                        )
                        ->multiple()
                        ->default(fn(): array => $this->defaultSidangAdditionalExaminerUserIds($record))
                        ->searchable()
                        ->getSearchResultsUsing(fn(string $search): array => app(LecturerSearchService::class)->filamentOptions($record, $search, 'examiner'))
                        ->getOptionLabelsUsing(fn(array $values): array => app(LecturerSearchService::class)->filamentOptionLabels($record, $values, 'examiner'))
                        ->searchPrompt('Ketik minimal 2 karakter untuk mencari dosen')
                        ->required()
                        ->minItems(fn(): int => $this->requiredSidangPanelUserIds($record) === [] ? 2 : 1)
                        ->helperText(
                            $this->requiredSidangPanelUserIds($record) === []
                                ? 'Belum ada pembimbing aktif. Pilih minimal dua dosen sebagai panel sidang.'
                                : 'Pembimbing aktif otomatis masuk panel sidang. Pilih minimal satu dosen penguji tambahan.'
                        )
                        ->native(false),
                    Textarea::make('notes')
                        ->label('Catatan')
                        ->rows(2),
                ])
                ->action(function (array $data) use ($record, $terms): void {
                    $userId = Auth::id();

                    if ($userId === null) {
                        return;
                    }

                    try {
                        $scheduledFor = $data['scheduled_starts_on'].' '.$data['scheduled_at_time'];
                        $scheduledUntil = filled($data['scheduled_ends_on'] ?? null)
                            ? $data['scheduled_ends_on'].' '.$data['scheduled_at_time']
                            : null;

                        app(ThesisProjectAdminService::class)->scheduleSidang(
                            project: $record,
                            createdBy: $userId,
                            scheduledFor: $scheduledFor,
                            location: (string) $data['location'],
                            mode: (string) $data['mode'],
                            panelUserIds: array_merge(
                                $this->requiredSidangPanelUserIds($record),
                                array_map(static fn($id): int => (int) $id, $data['additional_examiner_user_ids'] ?? []),
                            ),
                            notes: $data['notes'] ?? null,
                            scheduledUntil: $scheduledUntil,
                        );

                        Notification::make()
                            ->title($terms['finalExam'].' berhasil dijadwalkan')
                            ->success()
                            ->send();

                        $this->redirect(ThesisProjectResource::getUrl('view', ['record' => $record->getKey()]));
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Gagal menjadwalkan sidang')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('complete_sidang')
                ->label('Selesaikan '.$terms['finalExam'])
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->visible(fn(): bool => $record->state === 'active' && $this->latestSidang($record) !== null)
                ->disabled(fn(): bool => $this->latestSidang($record)?->status !== 'awaiting_finalization')
                ->tooltip(fn(): ?string => $this->completeSidangTooltip($record))
                ->form([
                    Select::make('result')
                        ->label('Hasil '.$terms['finalExam'])
                        ->options([
                            'pass' => 'Lulus',
                            'pass_with_revision' => 'Lulus dengan Syarat',
                            'fail' => 'Tidak Lulus',
                        ])
                        ->required()
                        ->native(false),
                    DateTimePicker::make('revision_due_at')
                        ->label('Batas Revisi')
                        ->required(fn(Get $get): bool => $get('result') === 'pass_with_revision'),
                    Textarea::make('revision_notes')
                        ->label('Catatan Revisi')
                        ->required(fn(Get $get): bool => $get('result') === 'pass_with_revision')
                        ->rows(2),
                    Textarea::make('notes')
                        ->label('Catatan '.$terms['finalExam'])
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) use ($record, $terms): void {
                    $userId = Auth::id();

                    if ($userId === null) {
                        return;
                    }

                    try {
                        app(ThesisProjectAdminService::class)->completeSidang(
                            project: $record,
                            decidedBy: $userId,
                            result: (string) $data['result'],
                            notes: (string) $data['notes'],
                            revisionNotes: $data['revision_notes'] ?? null,
                            revisionDueAt: filled($data['revision_due_at'] ?? null) ? (string) $data['revision_due_at'] : null,
                        );

                        Notification::make()
                            ->title($terms['finalExam'].' berhasil diperbarui')
                            ->success()
                            ->send();

                        $this->redirect(ThesisProjectResource::getUrl('view', ['record' => $record->getKey()]));
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Gagal memperbarui sidang')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];

        return [
            Action::make('edit')
                ->label('Edit Proyek')
                ->icon('heroicon-m-pencil-square')
                ->url(fn(): string => ThesisProjectResource::getUrl('edit', ['record' => $record])),
            ActionGroup::make($workflowActions)
                ->label('Aksi Workflow')
                ->icon('heroicon-m-bolt')
                ->color('gray')
                ->button(),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function requiredSidangPanelUserIds(ThesisProject $project): array
    {
        return $project->activeSupervisorAssignments
            ->sortBy('role')
            ->pluck('lecturer_user_id')
            ->filter()
            ->map(static fn($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function defaultSidangAdditionalExaminerUserIds(ThesisProject $project): array
    {
        $latestSidang = $this->latestSidang($project);

        $existingExaminerIds = collect($latestSidang?->examiners ?? [])
            ->where('role', 'examiner')
            ->pluck('lecturer_user_id')
            ->map(static fn($id): int => (int) $id)
            ->filter(static fn(int $id): bool => $id > 0)
            ->values();

        if ($existingExaminerIds->isNotEmpty()) {
            return $existingExaminerIds->all();
        }

        return [];
    }

    private function activeSupervisorSummary(ThesisProject $project): string
    {
        $project->loadMissing('activeSupervisorAssignments.lecturer');

        return $project->activeSupervisorAssignments
            ->sortBy('role')
            ->map(function (ThesisSupervisorAssignment $assignment, int $index): string {
                return sprintf('Pembimbing %d: %s', $index + 1, $assignment->lecturer?->name ?? '-');
            })
            ->implode(PHP_EOL);
    }

    private function latestSempro(ThesisProject $project): ?ThesisDefense
    {
        return $project->defenses
            ->where('type', 'sempro')
            ->sortByDesc('attempt_no')
            ->first();
    }

    private function latestSidang(ThesisProject $project): ?ThesisDefense
    {
        return $project->defenses
            ->where('type', 'sidang')
            ->sortByDesc('attempt_no')
            ->first();
    }

    private function latestEditableSempro(ThesisProject $project): ?ThesisDefense
    {
        return $this->latestEditableDefense($project, 'sempro');
    }

    private function latestEditableSidang(ThesisProject $project): ?ThesisDefense
    {
        return $this->latestEditableDefense($project, 'sidang');
    }

    private function latestEditableDefense(ThesisProject $project, string $type): ?ThesisDefense
    {
        $defense = $type === 'sempro'
            ? $this->latestSempro($project)
            : $this->latestSidang($project);

        if (! $defense instanceof ThesisDefense) {
            return null;
        }

        return in_array($defense->status, ['draft', 'scheduled'], true)
            ? $defense
            : null;
    }

    private function canScheduleDefense(ThesisProject $project, string $type): bool
    {
        if ($project->state !== 'active') {
            return false;
        }

        $defense = $type === 'sempro'
            ? $this->latestSempro($project)
            : $this->latestSidang($project);

        if (! $defense instanceof ThesisDefense) {
            return true;
        }

        if (in_array($defense->status, ['draft', 'scheduled'], true)) {
            return true;
        }

        return $defense->status === 'completed' && $defense->result === 'fail';
    }

    private function canDecideTitleReview(ThesisProject $project): bool
    {
        $project->loadMissing(['latestTitle', 'defenses', 'activeSupervisorAssignments']);

        return $project->state === 'active'
            && $project->phase === 'title_review'
            && $project->latestTitle?->status === 'submitted'
            && $project->defenses->isEmpty()
            && $project->activeSupervisorAssignments->isEmpty();
    }

    private function finalizeSemproTooltip(ThesisProject $project): ?string
    {
        $defense = $this->latestSempro($project);

        if (! $defense instanceof ThesisDefense) {
            return null;
        }

        if ($defense->status === 'awaiting_finalization') {
            return null;
        }

        if ($defense->status === 'scheduled') {
            $decidedCount = $defense->examiners->where('decision', '!=', 'pending')->count();

            return $decidedCount === 0
                ? 'Belum ada nilai dari dosen penguji.'
                : 'Belum semua dosen penguji mengirim keputusan.';
        }

        if ($defense->status === 'completed') {
            return 'Hasil sempro sudah pernah dicatat. Jadwalkan ulang jika perlu attempt baru.';
        }

        return 'Hasil sempro belum bisa dicatat pada status saat ini.';
    }

    private function completeSidangTooltip(ThesisProject $project): ?string
    {
        $defense = $this->latestSidang($project);

        if (! $defense instanceof ThesisDefense) {
            return null;
        }

        if ($defense->status === 'awaiting_finalization') {
            return null;
        }

        if ($defense->status === 'scheduled') {
            $decidedCount = $defense->examiners->where('decision', '!=', 'pending')->count();

            return $decidedCount === 0
                ? 'Belum ada nilai dari panel sidang.'
                : 'Belum semua panel sidang mengirim keputusan.';
        }

        if ($defense->status === 'completed') {
            return 'Hasil sidang sudah pernah dicatat. Jadwalkan ulang jika perlu attempt baru.';
        }

        return 'Hasil sidang belum bisa dicatat pada status saat ini.';
    }
}
