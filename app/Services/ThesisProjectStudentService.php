<?php

namespace App\Services;

use App\Models\ThesisDefense;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisRevision;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ThesisProjectStudentService
{
    public function canEditSubmission(?ThesisProject $project): bool
    {
        return $this->editableSubmissionMode($project) !== null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(User $student, array $data, UploadedFile $proposalFile): ThesisProject
    {
        $programStudiId = $student->mahasiswaProfile?->program_studi_id;

        if ($programStudiId === null) {
            throw new RuntimeException('Program studi Anda belum diatur. Hubungi admin terlebih dahulu.');
        }

        $path = $proposalFile->store('proposal_files', 'public');

        return DB::transaction(function () use ($data, $path, $programStudiId, $proposalFile, $student): ThesisProject {
            $submittedAt = now();

            $project = ThesisProject::query()->create([
                'student_user_id' => $student->id,
                'program_studi_id' => $programStudiId,
                'phase' => 'title_review',
                'state' => 'active',
                'started_at' => $submittedAt,
                'created_by' => $student->id,
            ]);

            $title = ThesisProjectTitle::query()->create([
                'project_id' => $project->id,
                'version_no' => 1,
                'title_id' => (string) $data['title_id'],
                'title_en' => (string) ($data['title_en'] ?? '-'),
                'proposal_summary' => (string) $data['proposal_summary'],
                'status' => 'submitted',
                'submitted_by_user_id' => $student->id,
                'submitted_at' => $submittedAt,
            ]);

            ThesisDocument::query()->create([
                'project_id' => $project->id,
                'title_version_id' => $title->id,
                'uploaded_by_user_id' => $student->id,
                'kind' => 'proposal',
                'status' => 'active',
                'version_no' => 1,
                'title' => 'Proposal Skripsi',
                'storage_disk' => 'public',
                'storage_path' => $path,
                'stored_file_name' => basename($path),
                'file_name' => $proposalFile->getClientOriginalName(),
                'mime_type' => $proposalFile->getMimeType() ?? 'application/pdf',
                'file_size_kb' => $this->toKilobytes($proposalFile->getSize()),
                'uploaded_at' => $submittedAt,
            ]);

            $this->recordEvent(
                $project,
                actorUserId: $student->id,
                eventType: 'project_created',
                label: 'Proyek tugas akhir dimulai',
                description: 'Mahasiswa membuat pengajuan judul dan proposal baru.',
            );

            $this->recordEvent(
                $project,
                actorUserId: $student->id,
                eventType: 'title_submitted',
                label: 'Judul diajukan',
                description: $title->title_id,
            );

            return $project;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePendingSubmission(User $student, ThesisProject $project, array $data, ?UploadedFile $proposalFile): ThesisProject
    {
        $programStudiId = $student->mahasiswaProfile?->program_studi_id;

        if ($programStudiId === null) {
            throw new RuntimeException('Program studi Anda belum diatur. Hubungi admin terlebih dahulu.');
        }

        $mode = $this->editableSubmissionMode($project);

        if ($mode === null) {
            throw new RuntimeException('Pengajuan tidak dapat diedit pada tahap ini.');
        }

        return DB::transaction(function () use ($data, $mode, $programStudiId, $proposalFile, $student, $project): ThesisProject {
            $updatedFileMeta = null;
            $proposalDocument = ThesisDocument::query()
                ->where('project_id', $project->id)
                ->where('kind', 'proposal')
                ->latest('id')
                ->first();

            if ($proposalFile instanceof UploadedFile) {
                $oldPath = $proposalDocument?->storage_path;
                $newPath = $proposalFile->store('proposal_files', 'public');

                if ($oldPath !== null && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }

                $updatedFileMeta = [
                    'path' => $newPath,
                    'stored_file_name' => basename($newPath),
                    'name' => $proposalFile->getClientOriginalName(),
                    'mime_type' => $proposalFile->getMimeType() ?? 'application/pdf',
                    'file_size_kb' => $this->toKilobytes($proposalFile->getSize()),
                ];
            }

            $phase = $mode === 'title_review'
                ? 'title_review'
                : ($project->phase === 'sempro' ? 'sempro' : 'sempro');

            $titleStatus = $mode === 'title_review'
                ? 'submitted'
                : ($project->latestTitle?->status ?? 'approved');

            $project->forceFill([
                'program_studi_id' => $programStudiId,
                'phase' => $phase,
                'state' => 'active',
            ])->save();

            $submittedAt = now();

            $title = $project->latestTitle ?? ThesisProjectTitle::query()->create([
                'project_id' => $project->id,
                'version_no' => 1,
                'title_id' => (string) $data['title_id'],
                'title_en' => (string) ($data['title_en'] ?? '-'),
                'proposal_summary' => (string) $data['proposal_summary'],
                'status' => $titleStatus,
                'submitted_by_user_id' => $student->id,
                'submitted_at' => $submittedAt,
            ]);

            $titlePayload = [
                'title_id' => (string) $data['title_id'],
                'title_en' => (string) ($data['title_en'] ?? '-'),
                'proposal_summary' => (string) $data['proposal_summary'],
                'status' => $titleStatus,
            ];

            if ($mode === 'title_review') {
                $titlePayload['submitted_by_user_id'] = $student->id;
                $titlePayload['submitted_at'] = $submittedAt;
                $titlePayload['decided_by_user_id'] = null;
                $titlePayload['decided_at'] = null;
                $titlePayload['decision_notes'] = null;
            }

            $title->forceFill($titlePayload)->save();

            $document = ThesisDocument::query()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'title_version_id' => $title->id,
                    'kind' => 'proposal',
                ],
                [
                    'uploaded_by_user_id' => $student->id,
                    'status' => 'active',
                    'version_no' => $title->version_no,
                    'title' => 'Proposal Skripsi',
                    'storage_disk' => 'public',
                    'storage_path' => $updatedFileMeta['path'] ?? $proposalDocument?->storage_path,
                    'stored_file_name' => $updatedFileMeta['stored_file_name'] ?? $proposalDocument?->stored_file_name,
                    'file_name' => $updatedFileMeta['name'] ?? $proposalDocument?->file_name,
                    'mime_type' => $updatedFileMeta['mime_type'] ?? $proposalDocument?->mime_type ?? 'application/pdf',
                    'file_size_kb' => $updatedFileMeta['file_size_kb'] ?? $proposalDocument?->file_size_kb,
                    'uploaded_at' => $submittedAt,
                ],
            );

            if ($updatedFileMeta === null && $proposalDocument instanceof ThesisDocument) {
                $document->forceFill([
                    'storage_path' => $proposalDocument->storage_path,
                    'stored_file_name' => $proposalDocument->stored_file_name,
                    'file_name' => $proposalDocument->file_name,
                ])->save();
            }

            if ($mode === 'sempro_revision') {
                ThesisRevision::query()
                    ->where('project_id', $project->id)
                    ->whereHas('defense', fn($query) => $query
                        ->where('type', 'sempro')
                        ->where('status', 'completed')
                        ->where('result', 'pass_with_revision'))
                    ->whereIn('status', ['open', 'submitted'])
                    ->update([
                        'status' => 'submitted',
                        'submitted_at' => $submittedAt,
                    ]);
            }

            $eventDescription = match ($mode) {
                'sempro_scheduled' => 'Mahasiswa memperbarui judul atau proposal untuk kebutuhan sempro yang sudah dijadwalkan.',
                'sempro_revision' => 'Mahasiswa memperbarui judul atau proposal untuk menindaklanjuti revisi sempro.',
                default => 'Mahasiswa memperbarui judul atau proposal sebelum direview admin.',
            };

            $this->recordEvent(
                $project,
                actorUserId: $student->id,
                eventType: 'title_updated',
                label: 'Pengajuan diperbarui',
                description: $eventDescription,
            );

            return $project;
        });
    }

    private function editableSubmissionMode(?ThesisProject $project): ?string
    {
        if (! $project instanceof ThesisProject) {
            return null;
        }

        $project->loadMissing(['latestTitle', 'defenses', 'revisions', 'activeSupervisorAssignments']);

        $latestSidang = $project->defenses
            ->where('type', 'sidang')
            ->sortByDesc('attempt_no')
            ->first();

        if ($latestSidang instanceof ThesisDefense) {
            return null;
        }

        $latestSempro = $project->defenses
            ->where('type', 'sempro')
            ->sortByDesc('attempt_no')
            ->first();

        if ($latestSempro instanceof ThesisDefense) {
            if ($latestSempro->status === 'scheduled') {
                return 'sempro_scheduled';
            }

            $hasOpenSemproRevision = $project->revisions
                ->where('defense_id', $latestSempro->getKey())
                ->whereIn('status', ['open', 'submitted'])
                ->isNotEmpty();

            if ($latestSempro->status === 'completed'
                && $latestSempro->result === 'pass_with_revision'
                && $hasOpenSemproRevision) {
                return 'sempro_revision';
            }

            return null;
        }

        if ($project->phase !== 'title_review') {
            return null;
        }

        if ($project->defenses->isNotEmpty() || $project->activeSupervisorAssignments->isNotEmpty()) {
            return null;
        }

        return $project->latestTitle?->status === 'submitted'
            ? 'title_review'
            : null;
    }

    private function recordEvent(
        ThesisProject $project,
        int $actorUserId,
        string $eventType,
        string $label,
        string $description,
    ): void {
        ThesisProjectEvent::query()->create([
            'project_id' => $project->id,
            'actor_user_id' => $actorUserId,
            'event_type' => $eventType,
            'label' => $label,
            'description' => $description,
            'payload' => null,
            'occurred_at' => now(),
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
