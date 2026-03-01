<?php

namespace App\Filament\Resources\Sempros\Pages;

use App\Enums\AppRole;
use App\Enums\SemproExaminerDecision;
use App\Enums\SemproStatus;
use App\Enums\ThesisSubmissionStatus;
use App\Filament\Resources\Sempros\SemproResource;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipChatThreadParticipant;
use App\Models\Sempro;
use App\Models\User;
use App\Services\MentorshipAssignmentService;
use App\Services\SemproWorkflowService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSempro extends ViewRecord
{
    protected static string $resource = SemproResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Sempro $record */
        $record = $this->record;

        return [
            // -- Action: Jadwalkan Sempro (from draft to scheduled) --
            Action::make('jadwalkan_sempro')
                ->label('Jadwalkan Sempro')
                ->icon('heroicon-m-calendar')
                ->color('info')
                ->visible(fn(): bool => $record->status === SemproStatus::Draft->value)
                ->requiresConfirmation()
                ->modalHeading('Jadwalkan Seminar Proposal')
                ->modalDescription('Pastikan jadwal, lokasi, dan penguji sudah diisi dengan benar sebelum menjadwalkan.')
                ->action(function () use ($record): void {
                    try {
                        app(SemproWorkflowService::class)->scheduleSempro($record);

                        // Auto-create penguji chat thread
                        $this->createPengujiThread($record);

                        Notification::make()
                            ->title('Sempro berhasil dijadwalkan')
                            ->success()
                            ->send();

                        $this->redirect(SemproResource::getUrl('view', ['record' => $record->getKey()]));
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Gagal menjadwalkan sempro')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            // -- Action: Approve Sempro (when all examiners approved) --
            Action::make('approve_sempro')
                ->label('Approve Sempro')
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->visible(fn(): bool => in_array($record->status, [
                    SemproStatus::Scheduled->value,
                    SemproStatus::RevisionOpen->value,
                ], true) && $record->examiners()
                        ->where('decision', SemproExaminerDecision::Approved->value)
                        ->count() >= $record->examiners()->count()
                    && $record->examiners()->count() >= 2)
                ->requiresConfirmation()
                ->modalHeading('Approve Seminar Proposal')
                ->modalDescription('Semua penguji sudah menyetujui. Sempro akan ditandai selesai.')
                ->action(function () use ($record): void {
                    try {
                        app(SemproWorkflowService::class)->approveSempro($record, auth()->id());

                        Notification::make()
                            ->title('Sempro berhasil disetujui')
                            ->success()
                            ->send();

                        $this->redirect(SemproResource::getUrl('view', ['record' => $record->getKey()]));
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Gagal menyetujui sempro')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            // -- Action: Tetapkan Pembimbing (only after sempro approved) --
            Action::make('tetapkan_pembimbing')
                ->label('Tetapkan Pembimbing')
                ->icon('heroicon-m-user-plus')
                ->color('primary')
                ->visible(fn(): bool => $record->status === SemproStatus::Approved->value
                    && $record->submission?->status !== ThesisSubmissionStatus::PembimbingDitetapkan->value)
                ->form([
                    Select::make('pembimbing_1')
                        ->label('Dosen Pembimbing 1')
                        ->options(fn(): array => User::query()
                            ->whereHas('roles', static fn($query) => $query->where('name', AppRole::Dosen->value))
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn(User $u) => [
                                $u->id => $u->name . ' (' . ($u->dosenProfile?->nik ?? '-') . ')',
                            ])
                            ->all())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    Select::make('pembimbing_2')
                        ->label('Dosen Pembimbing 2')
                        ->options(fn(): array => User::query()
                            ->whereHas('roles', static fn($query) => $query->where('name', AppRole::Dosen->value))
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn(User $u) => [
                                $u->id => $u->name . ' (' . ($u->dosenProfile?->nik ?? '-') . ')',
                            ])
                            ->all())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    Textarea::make('notes')
                        ->label('Catatan')
                        ->rows(2),
                ])
                ->modalHeading('Tetapkan Dosen Pembimbing')
                ->modalDescription('Pilih dosen pembimbing 1 dan 2 untuk mahasiswa ini.')
                ->action(function (array $data) use ($record): void {
                    try {
                        $studentUserId = $record->submission?->student_user_id;

                        if ($studentUserId === null) {
                            Notification::make()
                                ->title('Data mahasiswa tidak ditemukan')
                                ->danger()
                                ->send();

                            return;
                        }

                        app(MentorshipAssignmentService::class)->syncStudentAdvisors(
                            studentUserId: $studentUserId,
                            assignedBy: auth()->id(),
                            primaryLecturerUserId: (int) $data['pembimbing_1'],
                            secondaryLecturerUserId: (int) $data['pembimbing_2'],
                            notes: $data['notes'] ?? null,
                        );

                        // Update thesis submission status
                        $record->submission?->forceFill([
                            'status' => ThesisSubmissionStatus::PembimbingDitetapkan->value,
                        ])->save();

                        Notification::make()
                            ->title('Pembimbing berhasil ditetapkan')
                            ->success()
                            ->send();

                        $this->redirect(SemproResource::getUrl('view', ['record' => $record->getKey()]));
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Gagal menetapkan pembimbing')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            EditAction::make(),
        ];
    }

    private function createPengujiThread(Sempro $sempro): void
    {
        $studentUserId = $sempro->submission?->student_user_id;

        if ($studentUserId === null) {
            return;
        }

        // Don't create if already exists
        $exists = MentorshipChatThread::query()
            ->where('type', 'sempro')
            ->where('context_id', $sempro->id)
            ->exists();

        if ($exists) {
            return;
        }

        $thread = MentorshipChatThread::query()->create([
            'student_user_id' => $studentUserId,
            'type' => 'sempro',
            'context_id' => $sempro->id,
            'label' => 'Sempro',
        ]);

        // Add student as participant
        MentorshipChatThreadParticipant::query()->create([
            'thread_id' => $thread->id,
            'user_id' => $studentUserId,
            'role' => 'student',
        ]);

        // Add all examiners as participants
        foreach ($sempro->examiners as $examiner) {
            MentorshipChatThreadParticipant::query()->create([
                'thread_id' => $thread->id,
                'user_id' => $examiner->examiner_user_id,
                'role' => 'examiner',
            ]);
        }

        // System welcome message
        $thread->messages()->create([
            'sender_user_id' => null,
            'message_type' => 'text',
            'message' => 'Thread Seminar Proposal telah dibuat. Silahkan berdiskusi mengenai sempro di sini.',
            'sent_at' => now(),
        ]);
    }
}
