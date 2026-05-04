<?php

namespace App\Filament\Resources\ThesisProjects\Pages;

use App\Filament\Resources\ThesisProjects\ThesisProjectResource;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\ThesisProjectAdminService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
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

        $workflowActions = [
            Action::make('schedule_sempro')
                ->label('Jadwalkan Sempro')
                ->icon('heroicon-m-calendar')
                ->color('info')
                ->visible(fn(): bool => $record->state === 'active' && in_array($record->phase, ['title_review', 'sempro'], true))
                ->form([
                    DateTimePicker::make('scheduled_for')
                        ->label('Jadwal')
                        ->required(),
                    TextInput::make('location')
                        ->label('Lokasi')
                        ->required()
                        ->maxLength(255),
                    Select::make('mode')
                        ->label('Mode')
                        ->options([
                            'offline' => 'Offline',
                            'online' => 'Online',
                            'hybrid' => 'Hybrid',
                        ])
                        ->default('offline')
                        ->required()
                        ->native(false),
                    Select::make('examiner_1')
                        ->label('Penguji 1')
                        ->options(fn(): array => $this->dosenOptions($record))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    Select::make('examiner_2')
                        ->label('Penguji 2')
                        ->options(fn(): array => $this->dosenOptions($record))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->different('examiner_1')
                        ->native(false),
                ])
                ->action(function (array $data) use ($record): void {
                    $userId = Auth::id();

                    if ($userId === null) {
                        return;
                    }

                    try {
                        app(ThesisProjectAdminService::class)->scheduleSempro(
                            project: $record,
                            scheduledBy: $userId,
                            scheduledFor: (string) $data['scheduled_for'],
                            location: (string) $data['location'],
                            mode: (string) $data['mode'],
                            examinerUserIds: [(int) $data['examiner_1'], (int) $data['examiner_2']],
                        );

                        Notification::make()
                            ->title('Sempro berhasil dijadwalkan')
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
                ->label('Catat Hasil Sempro')
                ->icon('heroicon-m-check-badge')
                ->color('success')
                ->visible(fn(): bool => $record->state === 'active' && $this->latestSempro($record) !== null)
                ->disabled(fn(): bool => $this->latestSempro($record)?->status !== 'awaiting_finalization')
                ->tooltip(fn(): ?string => $this->finalizeSemproTooltip($record))
                ->form([
                    Select::make('result')
                        ->label('Hasil')
                        ->options([
                            'pass' => 'Lulus',
                            'pass_with_revision' => 'Lulus dengan Revisi',
                            'fail' => 'Tidak Lulus',
                        ])
                        ->required()
                        ->native(false),
                    DateTimePicker::make('revision_due_at')
                        ->label('Batas Revisi'),
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
                ->visible(fn(): bool => $record->state === 'active' && in_array($record->phase, ['research', 'sidang'], true))
                ->form([
                    Select::make('pembimbing_1')
                        ->label('Pembimbing 1')
                        ->options(fn(): array => $this->supervisorOptions($record))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    Select::make('pembimbing_2')
                        ->label('Pembimbing 2')
                        ->options(fn(): array => $this->supervisorOptions($record))
                        ->searchable()
                        ->preload()
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
                ->label('Jadwalkan Sidang')
                ->icon('heroicon-m-clipboard-document-check')
                ->color('warning')
                ->visible(fn(): bool => $record->state === 'active' && in_array($record->phase, ['research', 'sidang'], true))
                ->form([
                    DateTimePicker::make('scheduled_for')
                        ->label('Jadwal Sidang')
                        ->required(),
                    TextInput::make('location')
                        ->label('Lokasi')
                        ->required()
                        ->maxLength(255),
                    Select::make('mode')
                        ->label('Mode')
                        ->options([
                            'offline' => 'Offline',
                            'online' => 'Online',
                            'hybrid' => 'Hybrid',
                        ])
                        ->default('offline')
                        ->required()
                        ->native(false),
                    Textarea::make('active_supervisors')
                        ->label('Pembimbing Aktif')
                        ->default(fn(): string => $this->activeSupervisorSummary($record))
                        ->disabled()
                        ->dehydrated(false)
                        ->rows(2),
                    Select::make('additional_examiner_user_ids')
                        ->label('Dosen Penguji Sidang')
                        ->multiple()
                        ->options(fn(): array => $this->sidangAdditionalExaminerOptions($record))
                        ->default(fn(): array => $this->defaultSidangAdditionalExaminerUserIds($record))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->minItems(1)
                        ->helperText('Pembimbing aktif otomatis masuk panel sidang. Pilih minimal satu dosen penguji tambahan.')
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
                        app(ThesisProjectAdminService::class)->scheduleSidang(
                            project: $record,
                            createdBy: $userId,
                            scheduledFor: (string) $data['scheduled_for'],
                            location: (string) $data['location'],
                            mode: (string) $data['mode'],
                            panelUserIds: array_merge(
                                $this->requiredSidangPanelUserIds($record),
                                array_map(static fn($id): int => (int) $id, $data['additional_examiner_user_ids'] ?? []),
                            ),
                            notes: $data['notes'] ?? null,
                        );

                        Notification::make()
                            ->title('Sidang berhasil dijadwalkan')
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
                ->label('Selesaikan Sidang')
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->visible(fn(): bool => $record->state === 'active' && $this->latestSidang($record) !== null)
                ->disabled(fn(): bool => $this->latestSidang($record)?->status !== 'awaiting_finalization')
                ->tooltip(fn(): ?string => $this->completeSidangTooltip($record))
                ->form([
                    Select::make('result')
                        ->label('Hasil Sidang')
                        ->options([
                            'pass' => 'Lulus',
                            'pass_with_revision' => 'Lulus dengan Revisi',
                            'fail' => 'Tidak Lulus',
                        ])
                        ->required()
                        ->native(false),
                    DateTimePicker::make('revision_due_at')
                        ->label('Batas Revisi'),
                    Textarea::make('revision_notes')
                        ->label('Catatan Revisi')
                        ->rows(2),
                    Textarea::make('notes')
                        ->label('Catatan Sidang')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) use ($record): void {
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
                            ->title('Sidang berhasil diperbarui')
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
     * @return array<int, string>
     */
    private function dosenOptions(ThesisProject $project): array
    {
        return User::query()
            ->whereHas('roles', static fn($query) => $query->where('name', 'dosen'))
            ->whereHas('dosenProfile', function ($query) use ($project): void {
                $query->where('program_studi_id', $project->program_studi_id)
                    ->where('is_active', true);
            })
            ->with('dosenProfile')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn(User $user): array => [
                $user->id => sprintf('%s (%s) - %s', $user->name, $user->dosenProfile?->nik ?? '-', $user->dosenProfile?->concentration ?? '-'),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function supervisorOptions(ThesisProject $project): array
    {
        $studentConcentration = $project->student?->mahasiswaProfile?->concentration;

        return User::query()
            ->whereHas('roles', static fn($query) => $query->where('name', 'dosen'))
            ->whereHas('dosenProfile', function ($query) use ($project, $studentConcentration): void {
                $query->where('program_studi_id', $project->program_studi_id)
                    ->where('is_active', true)
                    ->where('concentration', $studentConcentration);
            })
            ->with('dosenProfile')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn(User $user): array => [
                $user->id => sprintf(
                    '%s (%s) - %s - %d/%d aktif',
                    $user->name,
                    $user->dosenProfile?->nik ?? '-',
                    $user->dosenProfile?->concentration ?? '-',
                    $this->activeThesisStudentCountForLecturer($user->id),
                    max(1, (int) ($user->dosenProfile?->supervision_quota ?? 14)),
                ),
            ])
            ->all();
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

        $availableIds = collect(array_keys($this->sidangAdditionalExaminerOptions($project)))
            ->map(static fn($id): int => (int) $id)
            ->values();

        $extraExaminerId = $availableIds->first();

        return collect()
            ->when($extraExaminerId !== null, fn($ids) => $ids->push($extraExaminerId))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function sidangAdditionalExaminerOptions(ThesisProject $project): array
    {
        $requiredIds = collect($this->requiredSidangPanelUserIds($project));

        return collect($this->dosenOptions($project))
            ->reject(fn($_label, $id): bool => $requiredIds->contains((int) $id))
            ->all();
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

    private function activeThesisStudentCountForLecturer(int $lecturerUserId): int
    {
        return ThesisSupervisorAssignment::query()
            ->with('project')
            ->where('lecturer_user_id', $lecturerUserId)
            ->where('status', 'active')
            ->whereHas('project', static fn($query) => $query->where('state', 'active'))
            ->get()
            ->map(static fn(ThesisSupervisorAssignment $assignment): ?int => $assignment->project?->student_user_id)
            ->filter()
            ->unique()
            ->count();
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
