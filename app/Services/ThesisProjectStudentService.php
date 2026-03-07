<?php

namespace App\Services;

use App\Enums\ThesisSubmissionStatus;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisSubmission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ThesisProjectStudentService
{
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
            $submission = ThesisSubmission::query()->create([
                'student_user_id' => $student->id,
                'program_studi_id' => $programStudiId,
                'title_id' => (string) $data['title_id'],
                'title_en' => (string) ($data['title_en'] ?? '-'),
                'proposal_summary' => (string) $data['proposal_summary'],
                'proposal_file_path' => $path,
                'status' => ThesisSubmissionStatus::MenungguPersetujuan->value,
                'is_active' => true,
                'submitted_at' => now(),
            ]);

            $project = ThesisProject::query()->create([
                'student_user_id' => $student->id,
                'program_studi_id' => $programStudiId,
                'legacy_thesis_submission_id' => $submission->id,
                'phase' => 'title_review',
                'state' => 'active',
                'started_at' => $submission->submitted_at,
                'created_by' => $student->id,
            ]);

            $title = ThesisProjectTitle::query()->create([
                'project_id' => $project->id,
                'version_no' => 1,
                'title_id' => $submission->title_id,
                'title_en' => $submission->title_en,
                'proposal_summary' => $submission->proposal_summary,
                'status' => 'submitted',
                'submitted_by_user_id' => $student->id,
                'submitted_at' => $submission->submitted_at,
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
                'file_name' => basename($path),
                'mime_type' => $proposalFile->getMimeType() ?? 'application/pdf',
                'file_size_kb' => $this->toKilobytes($proposalFile->getSize()),
                'uploaded_at' => $submission->submitted_at,
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
    public function updatePendingSubmission(User $student, ThesisSubmission $submission, array $data, ?UploadedFile $proposalFile): ThesisProject
    {
        $programStudiId = $student->mahasiswaProfile?->program_studi_id;

        if ($programStudiId === null) {
            throw new RuntimeException('Program studi Anda belum diatur. Hubungi admin terlebih dahulu.');
        }

        return DB::transaction(function () use ($data, $programStudiId, $proposalFile, $student, $submission): ThesisProject {
            $project = ThesisProject::query()
                ->where('legacy_thesis_submission_id', $submission->id)
                ->first();

            if (! $project instanceof ThesisProject) {
                app(LegacyThesisProjectBackfillService::class)->backfill($student->id);

                $project = ThesisProject::query()
                    ->where('legacy_thesis_submission_id', $submission->id)
                    ->first();
            }

            if (! $project instanceof ThesisProject) {
                throw new RuntimeException('Project snapshot untuk pengajuan ini tidak ditemukan.');
            }

            $payload = [
                'program_studi_id' => $programStudiId,
                'title_id' => (string) $data['title_id'],
                'title_en' => (string) ($data['title_en'] ?? '-'),
                'proposal_summary' => (string) $data['proposal_summary'],
            ];

            $updatedFileMeta = null;

            if ($proposalFile instanceof UploadedFile) {
                $oldPath = $submission->proposal_file_path;
                $newPath = $proposalFile->store('proposal_files', 'public');
                $payload['proposal_file_path'] = $newPath;

                if ($oldPath !== null && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }

                $updatedFileMeta = [
                    'path' => $newPath,
                    'name' => basename($newPath),
                    'mime_type' => $proposalFile->getMimeType() ?? 'application/pdf',
                    'file_size_kb' => $this->toKilobytes($proposalFile->getSize()),
                ];
            }

            $submission->update($payload);

            $project->forceFill([
                'program_studi_id' => $programStudiId,
                'phase' => 'title_review',
                'state' => 'active',
            ])->save();

            $title = $project->latestTitle ?? ThesisProjectTitle::query()->create([
                'project_id' => $project->id,
                'version_no' => 1,
                'title_id' => $submission->title_id,
                'title_en' => $submission->title_en,
                'proposal_summary' => $submission->proposal_summary,
                'status' => 'submitted',
                'submitted_by_user_id' => $student->id,
                'submitted_at' => $submission->submitted_at ?? now(),
            ]);

            $title->forceFill([
                'title_id' => $submission->title_id,
                'title_en' => $submission->title_en,
                'proposal_summary' => $submission->proposal_summary,
                'status' => 'submitted',
                'submitted_by_user_id' => $student->id,
            ])->save();

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
                    'storage_path' => $updatedFileMeta['path'] ?? $submission->proposal_file_path,
                    'file_name' => $updatedFileMeta['name'] ?? basename((string) $submission->proposal_file_path),
                    'mime_type' => $updatedFileMeta['mime_type'] ?? 'application/pdf',
                    'file_size_kb' => $updatedFileMeta['file_size_kb'] ?? null,
                    'uploaded_at' => now(),
                ],
            );

            if ($updatedFileMeta === null) {
                $document->forceFill([
                    'storage_path' => $submission->proposal_file_path,
                    'file_name' => basename((string) $submission->proposal_file_path),
                ])->save();
            }

            $this->recordEvent(
                $project,
                actorUserId: $student->id,
                eventType: 'title_updated',
                label: 'Pengajuan diperbarui',
                description: 'Mahasiswa memperbarui judul atau proposal sebelum direview admin.',
            );

            return $project;
        });
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
