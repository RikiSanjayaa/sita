<?php

namespace App\Filament\Resources\ThesisProjects\Pages;

use App\Filament\Resources\ThesisProjects\ThesisProjectResource;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\User;
use App\Services\ThesisProjectAdminService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewThesisProject extends ViewRecord
{
    protected static string $resource = ThesisProjectResource::class;

    protected function getHeaderActions(): array
    {
        /** @var ThesisProject $record */
        $record = $this->record;

        return [
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
                        ->options($this->dosenOptions())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    Select::make('examiner_2')
                        ->label('Penguji 2')
                        ->options($this->dosenOptions())
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
                ->form([
                    Select::make('result')
                        ->label('Hasil')
                        ->options([
                            'pass' => 'Lulus',
                            'pass_with_revision' => 'Lulus dengan Revisi',
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
                        ->options($this->dosenOptions())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    Select::make('pembimbing_2')
                        ->label('Pembimbing 2')
                        ->options($this->dosenOptions())
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
                    Select::make('chair_user_id')
                        ->label('Ketua Sidang')
                        ->options($this->dosenOptions())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    Select::make('secretary_user_id')
                        ->label('Sekretaris Sidang')
                        ->options($this->dosenOptions())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->different('chair_user_id')
                        ->native(false),
                    Select::make('examiner_user_id')
                        ->label('Penguji Sidang')
                        ->options($this->dosenOptions())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->different('chair_user_id')
                        ->different('secretary_user_id')
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
                            examinerAssignments: [
                                'chair_user_id' => (int) $data['chair_user_id'],
                                'secretary_user_id' => (int) $data['secretary_user_id'],
                                'examiner_user_id' => (int) $data['examiner_user_id'],
                            ],
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
    }

    /**
     * @return array<int, string>
     */
    private function dosenOptions(): array
    {
        return User::query()
            ->whereHas('roles', static fn($query) => $query->where('name', 'dosen'))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn(User $user): array => [
                $user->id => $user->name.' ('.($user->dosenProfile?->nik ?? '-').')',
            ])
            ->all();
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
}
