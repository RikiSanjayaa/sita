<?php

namespace App\Services;

use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisProjectTitle;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ThesisProjectAdminEditService
{
    public function __construct(
        private readonly RealtimeNotificationService $realtimeNotificationService,
    ) {}

    /**
     * @param  array{title_id:string,title_en:string,proposal_summary:string}  $data
     */
    public function syncCoreSubmission(
        User $admin,
        ThesisProject $project,
        array $data,
        ?UploadedFile $proposalFile = null,
    ): ThesisProject {
        $project->loadMissing([
            'student',
            'titles',
            'latestTitle',
            'documents',
        ]);

        $currentTitle = $this->resolveCurrentTitle($project);
        $currentProposal = $this->resolveCurrentProposalDocument($project, $currentTitle);

        $titleId = trim($data['title_id']);
        $titleEn = trim($data['title_en']) !== '' ? trim($data['title_en']) : '-';
        $proposalSummary = trim($data['proposal_summary']);
        $oldTitleId = $currentTitle?->title_id;

        $titleChanged = ($currentTitle?->title_id ?? null) !== $titleId
            || ($currentTitle?->title_en ?? '-') !== $titleEn
            || ($currentTitle?->proposal_summary ?? '') !== $proposalSummary;

        $proposalChanged = $proposalFile instanceof UploadedFile;

        if (! $titleChanged && ! $proposalChanged) {
            return $project;
        }

        $updatedAt = now();

        if (! $currentTitle instanceof ThesisProjectTitle) {
            $currentTitle = ThesisProjectTitle::query()->create([
                'project_id' => $project->id,
                'version_no' => 1,
                'title_id' => $titleId,
                'title_en' => $titleEn,
                'proposal_summary' => $proposalSummary,
                'status' => $project->phase === 'title_review' ? 'submitted' : 'approved',
                'submitted_by_user_id' => $project->student_user_id,
                'submitted_at' => $updatedAt,
                'decided_by_user_id' => $project->phase === 'title_review' ? null : $admin->id,
                'decided_at' => $project->phase === 'title_review' ? null : $updatedAt,
            ]);
        } else {
            $currentTitle->forceFill([
                'title_id' => $titleId,
                'title_en' => $titleEn,
                'proposal_summary' => $proposalSummary,
            ])->save();
        }

        if (! $currentProposal instanceof ThesisDocument && ! $proposalChanged) {
            throw new RuntimeException('Proposal aktif belum tersedia. Upload file proposal baru saat memperbarui data inti proyek.');
        }

        if ($proposalChanged) {
            $path = $proposalFile->store('proposal_files', 'public');

            if ($currentProposal instanceof ThesisDocument
                && filled($currentProposal->storage_path)
                && Storage::disk($currentProposal->storage_disk)->exists($currentProposal->storage_path)) {
                Storage::disk($currentProposal->storage_disk)->delete($currentProposal->storage_path);
            }

            ThesisDocument::query()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'title_version_id' => $currentTitle->id,
                    'kind' => 'proposal',
                ],
                [
                    'uploaded_by_user_id' => $admin->id,
                    'status' => 'active',
                    'version_no' => $currentTitle->version_no,
                    'title' => 'Proposal Skripsi',
                    'notes' => $currentProposal?->notes,
                    'storage_disk' => 'public',
                    'storage_path' => $path,
                    'stored_file_name' => basename($path),
                    'file_name' => $proposalFile->getClientOriginalName(),
                    'mime_type' => $proposalFile->getMimeType() ?? 'application/pdf',
                    'file_size_kb' => $this->toKilobytes($proposalFile->getSize()),
                    'uploaded_at' => $updatedAt,
                ],
            );
        }

        $changeSummary = $this->buildChangeSummary(
            oldTitleId: $oldTitleId,
            newTitleId: $titleId,
            titleChanged: $titleChanged,
            proposalChanged: $proposalChanged,
        );

        ThesisProjectEvent::query()->create([
            'project_id' => $project->id,
            'actor_user_id' => $admin->id,
            'event_type' => 'core_submission_updated_by_admin',
            'label' => 'Judul/proposal diperbarui admin',
            'description' => $changeSummary,
            'occurred_at' => $updatedAt,
        ]);

        $this->notifyStudentAboutCoreUpdate($project, $changeSummary);

        return $project->fresh(['latestTitle', 'documents', 'student']);
    }

    private function resolveCurrentTitle(ThesisProject $project): ?ThesisProjectTitle
    {
        $approved = $project->titles
            ->where('status', 'approved')
            ->sortByDesc('version_no')
            ->first();

        if ($approved instanceof ThesisProjectTitle) {
            return $approved;
        }

        return $project->latestTitle;
    }

    private function resolveCurrentProposalDocument(
        ThesisProject $project,
        ?ThesisProjectTitle $currentTitle,
    ): ?ThesisDocument {
        $query = $project->documents()
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

    private function buildChangeSummary(
        ?string $oldTitleId,
        string $newTitleId,
        bool $titleChanged,
        bool $proposalChanged,
    ): string {
        $parts = [];

        if ($titleChanged) {
            $parts[] = sprintf(
                'Judul diperbarui dari "%s" menjadi "%s".',
                $oldTitleId ?: '-',
                $newTitleId,
            );
        }

        if ($proposalChanged) {
            $parts[] = 'File proposal diperbarui oleh admin.';
        }

        if ($parts === []) {
            $parts[] = 'Admin memperbarui data inti tugas akhir.';
        }

        return implode(' ', $parts);
    }

    private function notifyStudentAboutCoreUpdate(ThesisProject $project, string $changeSummary): void
    {
        if (! $project->student instanceof User) {
            return;
        }

        $this->realtimeNotificationService->notifyUser($project->student, 'statusTugasAkhir', [
            'title' => 'Judul / proposal diperbarui admin',
            'description' => $changeSummary.' Silakan cek data terbaru pada halaman Tugas Akhir.',
            'url' => '/tugas-akhir',
            'icon' => 'file-pen-line',
            'createdAt' => now()->toIso8601String(),
        ]);
    }

    private function toKilobytes(?int $bytes): ?int
    {
        if ($bytes === null) {
            return null;
        }

        return (int) ceil($bytes / 1024);
    }
}
