<?php

namespace App\Services;

use App\Enums\AppRole;
use App\Models\MentorshipDocument;
use App\Models\ThesisDefense;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisRevision;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        $project = DB::transaction(function () use ($data, $path, $programStudiId, $proposalFile, $student): ThesisProject {
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

            $this->createWorkspaceMirror(
                student: $student,
                sourceDisk: 'public',
                sourcePath: $path,
                fileName: $proposalFile->getClientOriginalName(),
                mimeType: $proposalFile->getMimeType() ?? 'application/pdf',
                fileSizeKb: $this->toKilobytes($proposalFile->getSize()),
                title: 'Proposal Skripsi',
                category: 'proposal',
            );

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

        $this->notifyAdminsAboutNewSubmission($project);

        return $project;
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

                $this->createWorkspaceMirror(
                    student: $student,
                    sourceDisk: 'public',
                    sourcePath: $newPath,
                    fileName: $proposalFile->getClientOriginalName(),
                    mimeType: $proposalFile->getMimeType() ?? 'application/pdf',
                    fileSizeKb: $this->toKilobytes($proposalFile->getSize()),
                    title: 'Proposal Skripsi',
                    category: 'proposal',
                );
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
                'sempro_failed' => 'Mahasiswa memperbarui judul atau proposal setelah sempro dinyatakan gagal untuk persiapan attempt berikutnya.',
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

    /**
     * @param  array<int, int>  $supportingDocumentIds
     */
    public function syncDefenseDocumentsFromWorkspace(
        User $student,
        ThesisProject $project,
        string $type,
        int $mainDocumentId,
        array $supportingDocumentIds = [],
    ): ThesisProject {
        abort_unless($project->student_user_id === $student->id, 403);

        $project->loadMissing(['defenses.documents', 'latestTitle', 'titles']);

        /** @var ThesisDefense|null $defense */
        $defense = $project->defenses
            ->where('type', $type)
            ->sortByDesc('attempt_no')
            ->first();

        if (! $defense instanceof ThesisDefense) {
            throw new RuntimeException(sprintf('%s belum tersedia untuk proyek ini.', $type === 'sidang' ? 'Sidang' : 'Sempro'));
        }

        if ($defense->status === 'completed') {
            throw new RuntimeException(sprintf('Dokumen %s sudah dikunci karena tahap ini telah selesai.', $type));
        }

        $documentIds = collect([$mainDocumentId])
            ->merge($supportingDocumentIds)
            ->filter(static fn($id): bool => is_int($id) || ctype_digit((string) $id))
            ->map(static fn($id): int => (int) $id)
            ->unique()
            ->values();

        $workspaceDocuments = MentorshipDocument::query()
            ->where('student_user_id', $student->id)
            ->where('uploaded_by_role', 'mahasiswa')
            ->whereIn('id', $documentIds)
            ->get()
            ->keyBy('id');

        if (! $workspaceDocuments->has($mainDocumentId)) {
            throw new RuntimeException('Dokumen utama yang dipilih tidak ditemukan di workspace mahasiswa.');
        }

        if ($type === 'sempro' && $supportingDocumentIds !== []) {
            throw new RuntimeException('Sempro hanya menerima satu dokumen utama.');
        }

        $titleVersionId = $defense->title_version_id ?? $project->latestTitle?->getKey();
        $submittedAt = now();
        $mainDocument = $workspaceDocuments->get($mainDocumentId);

        if (! $mainDocument instanceof MentorshipDocument) {
            throw new RuntimeException('Dokumen utama tidak valid.');
        }

        return DB::transaction(function () use ($defense, $mainDocument, $project, $student, $submittedAt, $supportingDocumentIds, $titleVersionId, $type, $workspaceDocuments): ThesisProject {
            $primaryKind = $type === 'sidang' ? 'final_manuscript' : 'proposal';
            $primaryTitle = $type === 'sidang' ? 'Naskah Akhir Sidang' : 'Proposal Sempro';

            $this->replaceDefenseDocument(
                defense: $defense,
                project: $project,
                student: $student,
                source: $mainDocument,
                kind: $primaryKind,
                title: $primaryTitle,
                titleVersionId: $titleVersionId,
                uploadedAt: $submittedAt,
            );

            if ($type === 'sidang') {
                $this->replaceSidangSupportingDocuments(
                    defense: $defense,
                    project: $project,
                    student: $student,
                    sources: collect($supportingDocumentIds)
                        ->map(static fn($id): int => (int) $id)
                        ->reject(static fn(int $id): bool => $id === $mainDocument->getKey())
                        ->map(fn(int $id): ?MentorshipDocument => $workspaceDocuments->get($id))
                        ->filter(fn($document): bool => $document instanceof MentorshipDocument)
                        ->values(),
                    titleVersionId: $titleVersionId,
                    uploadedAt: $submittedAt,
                );
            }

            $this->recordEvent(
                $project,
                actorUserId: $student->id,
                eventType: $type === 'sidang' ? 'sidang_documents_selected' : 'sempro_document_selected',
                label: $type === 'sidang' ? 'Dokumen sidang dipilih' : 'Dokumen sempro dipilih',
                description: $type === 'sidang'
                    ? 'Mahasiswa memperbarui naskah akhir dan lampiran sidang dari workspace dokumen.'
                    : 'Mahasiswa memilih dokumen proposal dari workspace untuk sempro aktif.',
            );

            return $project->fresh(['documents', 'defenses.documents']);
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

            if ($latestSempro->status === 'completed' && $latestSempro->result === 'fail') {
                return 'sempro_failed';
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

    private function notifyAdminsAboutNewSubmission(ThesisProject $project): void
    {
        $project->loadMissing([
            'student',
            'programStudi',
            'latestTitle',
        ]);

        $recipients = User::query()
            ->where(function ($query) use ($project): void {
                $query->whereHas('roles', function ($roleQuery): void {
                    $roleQuery->where('name', AppRole::SuperAdmin->value);
                })->orWhere(function ($adminQuery) use ($project): void {
                    $adminQuery->whereHas('roles', function ($roleQuery): void {
                        $roleQuery->where('name', AppRole::Admin->value);
                    })->whereHas('adminProfile', function ($profileQuery) use ($project): void {
                        $profileQuery->where('program_studi_id', $project->program_studi_id);
                    });
                });
            })
            ->get();

        $body = trim(implode(' - ', array_filter([
            $project->student?->name,
            $project->programStudi?->name,
            $project->latestTitle?->title_id,
        ])));

        foreach ($recipients as $recipient) {
            Notification::make()
                ->title('Pengajuan judul baru')
                ->body($body)
                ->icon('heroicon-o-bell-alert')
                ->sendToDatabase($recipient, isEventDispatched: true);
        }
    }

    private function toKilobytes(?int $bytes): ?int
    {
        if ($bytes === null) {
            return null;
        }

        return (int) ceil($bytes / 1024);
    }

    private function createWorkspaceMirror(
        User $student,
        string $sourceDisk,
        string $sourcePath,
        string $fileName,
        ?string $mimeType,
        ?int $fileSizeKb,
        string $title,
        string $category,
    ): MentorshipDocument {
        $workspacePath = sprintf(
            'documents/mahasiswa/%d/%s/%s-%s',
            $student->id,
            trim($category),
            Str::uuid()->toString(),
            basename($sourcePath),
        );

        Storage::disk($sourceDisk)->copy($sourcePath, $workspacePath);

        $groupKey = sprintf('%d:%s', $student->id, strtolower(trim($category)));
        $nextVersion = ((int) MentorshipDocument::query()
            ->where('student_user_id', $student->id)
            ->where('document_group', $groupKey)
            ->max('version_number')) + 1;

        return MentorshipDocument::query()->create([
            'student_user_id' => $student->id,
            'lecturer_user_id' => null,
            'mentorship_assignment_id' => null,
            'title' => $title,
            'category' => $category,
            'document_group' => $groupKey,
            'version_number' => $nextVersion,
            'file_name' => $fileName,
            'file_url' => null,
            'storage_disk' => $sourceDisk,
            'storage_path' => $workspacePath,
            'stored_file_name' => basename($workspacePath),
            'mime_type' => $mimeType,
            'file_size_kb' => $fileSizeKb,
            'status' => 'submitted',
            'revision_notes' => null,
            'reviewed_at' => null,
            'uploaded_by_user_id' => $student->id,
            'uploaded_by_role' => 'mahasiswa',
        ]);
    }

    private function replaceDefenseDocument(
        ThesisDefense $defense,
        ThesisProject $project,
        User $student,
        MentorshipDocument $source,
        string $kind,
        string $title,
        ?int $titleVersionId,
        $uploadedAt,
    ): ThesisDocument {
        $existing = ThesisDocument::query()
            ->where('defense_id', $defense->getKey())
            ->where('kind', $kind)
            ->latest('id')
            ->first();

        $snapshotPath = $this->copyWorkspaceDocumentToThesisStorage($project, $defense, $source, $kind);

        if ($existing instanceof ThesisDocument
            && $existing->storage_path !== null
            && Storage::disk($existing->storage_disk)->exists($existing->storage_path)) {
            Storage::disk($existing->storage_disk)->delete($existing->storage_path);
        }

        return ThesisDocument::query()->updateOrCreate(
            [
                'defense_id' => $defense->getKey(),
                'kind' => $kind,
            ],
            [
                'project_id' => $project->getKey(),
                'title_version_id' => $titleVersionId,
                'revision_id' => null,
                'source_workspace_document_id' => $source->getKey(),
                'uploaded_by_user_id' => $student->id,
                'status' => 'active',
                'version_no' => $defense->attempt_no,
                'title' => $title,
                'notes' => null,
                'storage_disk' => $source->storage_disk,
                'storage_path' => $snapshotPath,
                'stored_file_name' => basename($snapshotPath),
                'file_name' => $source->file_name,
                'mime_type' => $source->mime_type,
                'file_size_kb' => $source->file_size_kb,
                'uploaded_at' => $uploadedAt,
            ],
        );
    }

    /**
     * @param  Collection<int, MentorshipDocument>  $sources
     */
    private function replaceSidangSupportingDocuments(
        ThesisDefense $defense,
        ThesisProject $project,
        User $student,
        Collection $sources,
        ?int $titleVersionId,
        $uploadedAt,
    ): void {
        $existingDocuments = ThesisDocument::query()
            ->where('defense_id', $defense->getKey())
            ->where('kind', 'supporting_document')
            ->get();

        foreach ($existingDocuments as $existingDocument) {
            if ($existingDocument->storage_path !== null
                && Storage::disk($existingDocument->storage_disk)->exists($existingDocument->storage_path)) {
                Storage::disk($existingDocument->storage_disk)->delete($existingDocument->storage_path);
            }

            $existingDocument->delete();
        }

        foreach ($sources as $index => $source) {
            if (! $source instanceof MentorshipDocument) {
                continue;
            }

            $snapshotPath = $this->copyWorkspaceDocumentToThesisStorage($project, $defense, $source, 'supporting_document');

            ThesisDocument::query()->create([
                'project_id' => $project->getKey(),
                'title_version_id' => $titleVersionId,
                'defense_id' => $defense->getKey(),
                'revision_id' => null,
                'source_workspace_document_id' => $source->getKey(),
                'uploaded_by_user_id' => $student->id,
                'kind' => 'supporting_document',
                'status' => 'active',
                'version_no' => $defense->attempt_no + $index,
                'title' => $source->title,
                'notes' => null,
                'storage_disk' => $source->storage_disk,
                'storage_path' => $snapshotPath,
                'stored_file_name' => basename($snapshotPath),
                'file_name' => $source->file_name,
                'mime_type' => $source->mime_type,
                'file_size_kb' => $source->file_size_kb,
                'uploaded_at' => $uploadedAt,
            ]);
        }
    }

    private function copyWorkspaceDocumentToThesisStorage(
        ThesisProject $project,
        ThesisDefense $defense,
        MentorshipDocument $source,
        string $kind,
    ): string {
        if ($source->storage_disk === null || $source->storage_path === null) {
            throw new RuntimeException('Dokumen workspace tidak memiliki lokasi file yang valid.');
        }

        $snapshotPath = sprintf(
            'thesis/defenses/%d/%s/attempt-%d/%s-%s',
            $project->getKey(),
            $defense->type,
            $defense->attempt_no,
            $kind,
            Str::uuid()->toString().'-'.($source->stored_file_name ?? basename($source->storage_path)),
        );

        Storage::disk($source->storage_disk)->copy($source->storage_path, $snapshotPath);

        return $snapshotPath;
    }
}
