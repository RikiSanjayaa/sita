<?php

namespace App\Filament\Resources\StudentGuides\Pages;

use App\Filament\Resources\StudentGuides\StudentGuideResource;
use App\Models\ProgramStudi;
use App\Services\SystemAuditLogService;
use App\Support\StudentGuideContent;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EditStudentGuide extends EditRecord
{
    protected static string $resource = StudentGuideResource::class;

    /**
     * @var array<int, string>
     */
    protected array $templatePathsToDelete = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var ProgramStudi $record */
        $record = $this->record;

        return array_merge($data, StudentGuideContent::toFormData($record->student_guide_content));
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var ProgramStudi $record */
        $record = $this->record;
        $content = StudentGuideContent::fromFormData($data);

        $this->templatePathsToDelete = $this->removedTemplatePaths($record->student_guide_content, $content);

        return [
            'student_guide_content' => $content,
            'student_guide_updated_by' => Auth::id(),
            'student_guide_updated_at' => now(),
        ];
    }

    protected function afterSave(): void
    {
        $disk = Storage::disk('public');

        foreach ($this->templatePathsToDelete as $path) {
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }

        /** @var ProgramStudi $record */
        $record = $this->getRecord();

        app(SystemAuditLogService::class)->record(
            user: Auth::user(),
            eventType: 'student_guide_updated',
            label: 'Panduan mahasiswa diperbarui',
            description: 'Panduan mahasiswa untuk prodi '.$record->name.' berhasil diperbarui.',
            request: request(),
            payload: [
                'program_studi_id' => $record->id,
                'program_studi_name' => $record->name,
                'summary' => StudentGuideContent::summary($record->student_guide_content),
            ],
        );
    }

    protected function getRedirectUrl(): string
    {
        return StudentGuideResource::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Panduan mahasiswa berhasil diperbarui';
    }

    public function getTitle(): string
    {
        /** @var ProgramStudi $record */
        $record = $this->record;

        return 'Edit Panduan Mahasiswa - '.$record->name;
    }

    /**
     * @param  array<string, mixed>|null  $previous
     * @param  array<string, mixed>  $current
     * @return array<int, string>
     */
    private function removedTemplatePaths(?array $previous, array $current): array
    {
        $previousPaths = collect(StudentGuideContent::normalize($previous)['template_docs'])
            ->pluck('file_path')
            ->filter()
            ->values()
            ->all();

        $currentPaths = collect($current['template_docs'])
            ->pluck('file_path')
            ->filter()
            ->values()
            ->all();

        return array_values(array_diff($previousPaths, $currentPaths));
    }
}
