<?php

namespace App\Filament\Resources\ThesisProjects\Pages;

use App\Filament\Resources\ThesisProjects\ThesisProjectResource;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Models\User;
use App\Services\ThesisProjectAdminEditService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EditThesisProject extends EditRecord
{
    protected static string $resource = ThesisProjectResource::class;

    /**
     * @var array{title_id:string,title_en:string,proposal_summary:string}
     */
    private array $coreSubmissionData = [
        'title_id' => '',
        'title_en' => '-',
        'proposal_summary' => '',
    ];

    private ?UploadedFile $proposalFile = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var ThesisProject $record */
        $record = $this->record;

        $data['student_name'] = $record->student?->name ?? '-';
        $data['student_nim'] = $record->student?->mahasiswaProfile?->nim ?? '-';
        $data['program_studi_name'] = $record->programStudi?->name ?? '-';
        $currentTitle = $this->resolveCurrentTitle($record);
        $currentProposal = $this->resolveCurrentProposalDocument($record, $currentTitle);

        $data['active_title'] = $currentTitle?->title_id ?? '-';
        $data['title_id'] = $currentTitle?->title_id ?? '';
        $data['title_en'] = $currentTitle?->title_en === '-' ? '' : ($currentTitle?->title_en ?? '');
        $data['proposal_summary'] = $currentTitle?->proposal_summary ?? '';
        $data['current_proposal_file_name'] = $currentProposal?->file_name ?? '-';

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $proposalFile = $data['proposal_file'] ?? null;

        if (is_array($proposalFile)) {
            $proposalFile = $proposalFile[0] ?? null;
        }

        $this->proposalFile = $proposalFile instanceof UploadedFile
            ? $proposalFile
            : null;

        $this->coreSubmissionData = [
            'title_id' => (string) ($data['title_id'] ?? ''),
            'title_en' => (string) ($data['title_en'] ?? '-'),
            'proposal_summary' => (string) ($data['proposal_summary'] ?? ''),
        ];

        unset(
            $data['student_name'],
            $data['student_nim'],
            $data['program_studi_name'],
            $data['active_title'],
            $data['title_id'],
            $data['title_en'],
            $data['proposal_summary'],
            $data['current_proposal_file_name'],
            $data['proposal_file'],
        );

        if (($data['state'] ?? null) === 'completed') {
            $data['phase'] = 'completed';
            $data['cancelled_at'] = null;
        }

        if (($data['state'] ?? null) === 'cancelled') {
            $data['phase'] = 'cancelled';
            $data['completed_at'] = null;
        }

        if (in_array($data['state'] ?? null, ['active', 'on_hold'], true)) {
            $data['completed_at'] = null;
            $data['cancelled_at'] = null;
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var ThesisProject $record */
        $user = Auth::user();

        return DB::transaction(function () use ($data, $record, $user): Model {
            $record->update($data);

            if ($user instanceof User) {
                app(ThesisProjectAdminEditService::class)->syncCoreSubmission(
                    $user,
                    $record,
                    $this->coreSubmissionData,
                    $this->proposalFile,
                );
            }

            return $record->refresh();
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->requiresConfirmation()
            ->modalHeading('Simpan perubahan proyek tugas akhir?')
            ->modalDescription('Perubahan judul dan proposal akan langsung berlaku. Jika data inti proyek diubah, mahasiswa akan otomatis menerima notifikasi.')
            ->modalSubmitActionLabel('Ya, simpan perubahan');
    }

    protected function getRedirectUrl(): string
    {
        return ThesisProjectResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Proyek tugas akhir berhasil diperbarui';
    }

    private function resolveCurrentTitle(ThesisProject $record): ?ThesisProjectTitle
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

    private function resolveCurrentProposalDocument(
        ThesisProject $record,
        ?ThesisProjectTitle $currentTitle,
    ): ?ThesisDocument {
        $query = $record->documents()
            ->where('kind', 'proposal');

        if ($currentTitle instanceof ThesisProjectTitle) {
            $document = (clone $query)
                ->where('title_version_id', $currentTitle->id)
                ->latest('id')
                ->first();

            if ($document instanceof ThesisDocument) {
                return $document;
            }
        }

        return $query->latest('id')->first();
    }
}
