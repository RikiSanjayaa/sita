<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Events\ChatMessageCreated;
use App\Http\Controllers\Controller;
use App\Models\MentorshipAssignment;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipDocument;
use App\Models\ThesisDefense;
use App\Models\ThesisDocument;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\ChatMessagePayloadBuilder;
use App\Services\DocumentDetailService;
use App\Services\MentorshipTicketService;
use App\Services\RealtimeNotificationService;
use App\Services\SecretSidangExaminers;
use App\Services\ThesisArchiveService;
use App\Support\AcademicTerminology;
use App\Support\WitaDateTime;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class UploadDokumenController extends Controller
{
    private function linkedDocumentDeleteMessage(User $student): string
    {
        $terms = AcademicTerminology::forStudent($student);

        return 'Dokumen ini sedang dipakai untuk '.$terms['proposalExamShort'].' atau '.$terms['finalExamShort'].' dan tidak dapat dihapus.';
    }

    public function __construct(
        private readonly RealtimeNotificationService $realtimeNotificationService,
        private readonly ChatMessagePayloadBuilder $payloadBuilder,
    ) {}

    public function index(Request $request, ThesisArchiveService $archive): Response
    {
        $student = $request->user();
        abort_if($student === null, 401);

        $documents = MentorshipDocument::query()
            ->with('lecturer')
            ->where('student_user_id', $student->id)
            ->where('uploaded_by_role', 'mahasiswa')
            ->latest('created_at')
            ->get();

        $linkedWorkspaceDocumentIds = ThesisDocument::query()
            ->whereHas('project', fn($query) => $query->where('student_user_id', $student->id))
            ->whereHas('defense', fn($query) => $query->whereIn('type', ['sempro', 'sidang']))
            ->whereNotNull('source_workspace_document_id')
            ->pluck('source_workspace_document_id')
            ->map(static fn($id): int => (int) $id)
            ->flip();

        $reviewerRoles = app(DocumentDetailService::class)->reviewerRoles($student);
        $secretExaminers = app(SecretSidangExaminers::class);

        $documents = $documents
            ->groupBy(fn(MentorshipDocument $document): string => sprintf('%s:%d', $document->document_group ?? 'legacy-'.$document->id, $document->version_number))
            ->map(function ($versions) use ($linkedWorkspaceDocumentIds, $reviewerRoles, $secretExaminers, $student): array {
                /** @var MentorshipDocument $document */
                $document = $versions->firstWhere('lecturer_user_id', null) ?? $versions->first();
                $isLinkedToDefense = $versions->contains(
                    fn(MentorshipDocument $version): bool => $linkedWorkspaceDocumentIds->has($version->getKey()),
                );

                return [
                    'id' => $document->id,
                    'detailUrl' => DocumentDetailService::workspaceUrl($document, 'mahasiswa'),
                    'title' => $document->title,
                    'category' => $document->category ?? '-',
                    'version' => $document->category === 'lampiran-chat' ? null : sprintf('v%d', $document->version_number),
                    'uploadedAt' => WitaDateTime::format($document->created_at, withLabel: false),
                    'fileName' => $document->file_name,
                    'size' => sprintf('%d KB', (int) $document->file_size_kb),
                    'status' => DocumentDetailService::statusLabel(DocumentDetailService::aggregateStatus($versions)),
                    'reviews' => $versions
                        ->whereNotNull('lecturer_user_id')
                        ->map(fn(MentorshipDocument $version): array => [
                            'lecturerName' => $secretExaminers->labelFor($student->id, (int) $version->lecturer_user_id)
                                ?? $version->lecturer?->name ?? '-',
                            'role' => $reviewerRoles[(int) $version->lecturer_user_id] ?? 'Dosen',
                            'status' => \App\Services\DocumentRevisionService::displayStatus($version),
                            'notes' => $version->revision_notes,
                            'reviewedAt' => WitaDateTime::formatOrNull($version->reviewed_at),
                        ])
                        ->values()
                        ->all(),
                    'downloadUrl' => route('files.documents.download', ['document' => $document->id]),
                    'canDelete' => ! $isLinkedToDefense,
                    'deleteBlockedReason' => $isLinkedToDefense
                        ? $this->linkedDocumentDeleteMessage($student)
                        : null,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('upload-dokumen', [
            'uploadedDocuments' => $documents,
            'archivedProject' => $archive->studentHasArchivedProject($student),
        ]);
    }

    public function store(Request $request, MentorshipTicketService $tickets): RedirectResponse
    {
        $student = $request->user();
        abort_if($student === null, 401);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'document' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ]);

        $suspension = $tickets->suspensionMessageForStudent($student->id);

        if ($suspension !== null) {
            return back()->withErrors(['ticket' => $suspension]);
        }

        $assignments = ThesisSupervisorAssignment::query()
            ->with('lecturer')
            ->whereHas('project', fn($query) => $query
                ->where('student_user_id', $student->id)
                ->where('state', 'active'))
            ->where('status', 'active')
            ->get();

        $shouldNotifySidang = in_array(trim((string) $data['category']), ['final-manuscript', 'lampiran-sidang'], true);
        $activeSemproDefenses = $this->activeSemproDefenses($student->id);
        $pengujiThreads = $this->activeSemproThreads($student->id, $activeSemproDefenses);
        $semproExaminerIds = $activeSemproDefenses
            ->flatMap(fn(ThesisDefense $defense) => $defense->examiners->pluck('lecturer_user_id'))
            ->filter()
            ->map(static fn($id): int => (int) $id)
            ->unique()
            ->values();
        $activeSidangDefenses = $shouldNotifySidang ? $this->activeSidangDefenses($student->id) : collect();
        $sidangThreads = $shouldNotifySidang ? $this->activeSidangThreads($student->id, $activeSidangDefenses) : collect();
        $sidangExaminerIds = $activeSidangDefenses
            ->flatMap(fn(ThesisDefense $defense) => $defense->examiners->pluck('lecturer_user_id'))
            ->filter()
            ->map(static fn($id): int => (int) $id)
            ->unique()
            ->values();

        $recipientLecturerIds = $assignments
            ->pluck('lecturer_user_id')
            ->merge($semproExaminerIds)
            ->merge($sidangExaminerIds)
            ->filter()
            ->map(static fn($id): int => (int) $id)
            ->unique()
            ->values();

        if ($recipientLecturerIds->isEmpty()) {
            return back()->withErrors([
                'document' => 'Belum ada dosen pembimbing atau penguji '.AcademicTerminology::forStudent($student)['proposalExamShort'].' aktif untuk menerima dokumen.',
            ]);
        }

        $file = $data['document'];
        $disk = 'public';
        $storedPath = $file->store(sprintf('documents/mahasiswa/%d', $student->id), $disk);

        $groupKey = sprintf('%d:%s', $student->id, strtolower(trim($data['category'])));
        $nextVersion = ((int) MentorshipDocument::query()
            ->where('student_user_id', $student->id)
            ->where('document_group', $groupKey)
            ->max('version_number')) + 1;

        $createdDocuments = collect();
        $assignmentIdsByLecturer = $this->mentorshipAssignmentIdsByLecturer($student->id, $assignments);

        DB::transaction(function () use ($assignmentIdsByLecturer, $assignments, $createdDocuments, $data, $disk, $file, $groupKey, $nextVersion, $pengujiThreads, $recipientLecturerIds, $sidangThreads, $storedPath, $student): void {
            foreach ($recipientLecturerIds as $lecturerUserId) {
                $createdDocuments->push(MentorshipDocument::query()->create([
                    'student_user_id' => $student->id,
                    'lecturer_user_id' => $lecturerUserId,
                    'mentorship_assignment_id' => $assignmentIdsByLecturer->get($lecturerUserId),
                    'title' => trim($data['title']),
                    'category' => trim($data['category']),
                    'document_group' => $groupKey,
                    'version_number' => $nextVersion,
                    'file_name' => $file->getClientOriginalName(),
                    'file_url' => null,
                    'storage_disk' => $disk,
                    'storage_path' => $storedPath,
                    'stored_file_name' => basename($storedPath),
                    'mime_type' => $file->getClientMimeType(),
                    'file_size_kb' => (int) ceil($file->getSize() / 1024),
                    'status' => 'submitted',
                    'revision_notes' => null,
                    'reviewed_at' => null,
                    'uploaded_by_user_id' => $student->id,
                    'uploaded_by_role' => 'mahasiswa',
                ]));
            }

            if ($assignments->isNotEmpty()) {
                $thread = MentorshipChatThread::query()->firstOrCreate([
                    'student_user_id' => $student->id,
                    'type' => 'pembimbing',
                ]);

                $message = $thread->messages()->create([
                    'sender_user_id' => $student->id,
                    'related_document_id' => $createdDocuments->first()?->id,
                    'attachment_disk' => $disk,
                    'attachment_path' => $storedPath,
                    'attachment_name' => $file->getClientOriginalName(),
                    'attachment_mime' => $file->getClientMimeType(),
                    'attachment_size_kb' => (int) ceil($file->getSize() / 1024),
                    'message_type' => 'document_event',
                    'message' => sprintf(
                        'Mahasiswa mengunggah dokumen %s versi v%d.',
                        trim($data['category']),
                        $nextVersion,
                    ),
                    'sent_at' => now(),
                ]);

                $this->broadcastChatMessage($thread->id, $this->payloadBuilder->build($message));
            }

            foreach (User::query()->whereIn('id', $recipientLecturerIds)->get() as $recipient) {

                $this->realtimeNotificationService->notifyUser($recipient, 'statusTugasAkhir', [
                    'title' => 'Dokumen bimbingan baru diunggah',
                    'description' => sprintf('%s mengunggah dokumen %s.', $student->name, trim($data['category'])),
                    'url' => DocumentDetailService::workspaceUrl($createdDocuments->firstWhere('lecturer_user_id', $recipient->id), 'dosen'),
                    'icon' => 'file-text',
                    'createdAt' => now()->toIso8601String(),
                ]);
            }

            foreach ($pengujiThreads as $pengujiThread) {
                $pengujiMessage = $pengujiThread->messages()->create([
                    'sender_user_id' => $student->id,
                    'related_document_id' => $createdDocuments->first()?->id,
                    'attachment_disk' => $disk,
                    'attachment_path' => $storedPath,
                    'attachment_name' => $file->getClientOriginalName(),
                    'attachment_mime' => $file->getClientMimeType(),
                    'attachment_size_kb' => (int) ceil($file->getSize() / 1024),
                    'message_type' => 'document_event',
                    'message' => sprintf(
                        'Mahasiswa mengunggah dokumen %s versi v%d (notifikasi ke thread %s).',
                        trim($data['category']),
                        $nextVersion,
                        AcademicTerminology::forStudent($student)['proposalExamShort'],
                    ),
                    'sent_at' => now(),
                ]);

                $this->broadcastChatMessage($pengujiThread->id, $this->payloadBuilder->build($pengujiMessage));
            }

            foreach ($sidangThreads as $sidangThread) {
                $sidangMessage = $sidangThread->messages()->create([
                    'sender_user_id' => $student->id,
                    'related_document_id' => $createdDocuments->first()?->id,
                    'attachment_disk' => $disk,
                    'attachment_path' => $storedPath,
                    'attachment_name' => $file->getClientOriginalName(),
                    'attachment_mime' => $file->getClientMimeType(),
                    'attachment_size_kb' => (int) ceil($file->getSize() / 1024),
                    'message_type' => 'document_event',
                    'message' => sprintf(
                        'Mahasiswa mengunggah dokumen %s versi v%d (notifikasi ke thread %s).',
                        trim($data['category']),
                        $nextVersion,
                        AcademicTerminology::forStudent($student)['finalExamShort'],
                    ),
                    'sent_at' => now(),
                ]);

                $this->broadcastChatMessage($sidangThread->id, $this->payloadBuilder->build($sidangMessage));
            }
        });

        return redirect()
            ->route('mahasiswa.upload-dokumen')
            ->with('success', 'Dokumen berhasil diunggah dan notifikasi terkirim ke thread terkait.');
    }

    /**
     * @return Collection<int, ThesisDefense>
     */
    private function activeSemproDefenses(int $studentUserId): Collection
    {
        return ThesisDefense::query()
            ->with(['examiners' => fn($query) => $query->select(['id', 'defense_id', 'lecturer_user_id'])])
            ->whereHas('project', fn($query) => $query->where('student_user_id', $studentUserId))
            ->where('type', 'sempro')
            ->where(function ($query): void {
                $query->where('status', 'scheduled')
                    ->orWhere(function ($nestedQuery): void {
                        $nestedQuery->where('status', 'completed')
                            ->where('result', 'pass_with_revision');
                    });
            })
            ->get();
    }

    /**
     * @param  Collection<int, ThesisDefense>  $defenses
     * @return Collection<int, MentorshipChatThread>
     */
    private function activeSemproThreads(int $studentUserId, Collection $defenses): Collection
    {
        $contextIds = $defenses
            ->flatMap(static fn(ThesisDefense $defense): array => array_values(array_filter([
                $defense->getKey(),
                $defense->legacy_sempro_id,
            ])))
            ->unique()
            ->values()
            ->all();

        return MentorshipChatThread::query()
            ->where('student_user_id', $studentUserId)
            ->where('type', 'sempro')
            ->when(
                $contextIds !== [],
                fn($query) => $query->whereIn('context_id', $contextIds),
                fn($query) => $query->whereRaw('1 = 0'),
            )
            ->get();
    }

    /**
     * @return Collection<int, ThesisDefense>
     */
    private function activeSidangDefenses(int $studentUserId): Collection
    {
        return ThesisDefense::query()
            ->with(['examiners' => fn($query) => $query->select(['id', 'defense_id', 'lecturer_user_id'])])
            ->whereHas('project', fn($query) => $query->where('student_user_id', $studentUserId))
            ->where('type', 'sidang')
            ->where(function ($query): void {
                $query->where('status', 'scheduled')
                    ->orWhere(function ($nestedQuery): void {
                        $nestedQuery->where('status', 'completed')
                            ->where('result', 'pass_with_revision');
                    });
            })
            ->get();
    }

    /**
     * @param  Collection<int, ThesisDefense>  $defenses
     * @return Collection<int, MentorshipChatThread>
     */
    private function activeSidangThreads(int $studentUserId, Collection $defenses): Collection
    {
        $contextIds = $defenses
            ->map(fn(ThesisDefense $defense): int => $defense->getKey())
            ->values()
            ->all();

        return MentorshipChatThread::query()
            ->where('student_user_id', $studentUserId)
            ->where('type', 'sidang')
            ->when(
                $contextIds !== [],
                fn($query) => $query->whereIn('context_id', $contextIds),
                fn($query) => $query->whereRaw('1 = 0'),
            )
            ->get();
    }

    /**
     * @param  Collection<int, ThesisSupervisorAssignment>  $assignments
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function mentorshipAssignmentIdsByLecturer(int $studentUserId, Collection $assignments): \Illuminate\Support\Collection
    {
        $lecturerIds = $assignments
            ->pluck('lecturer_user_id')
            ->filter()
            ->map(static fn($id): int => (int) $id)
            ->unique()
            ->values();

        if ($lecturerIds->isEmpty()) {
            return collect();
        }

        return MentorshipAssignment::query()
            ->where('student_user_id', $studentUserId)
            ->whereIn('lecturer_user_id', $lecturerIds)
            ->where('status', 'active')
            ->get()
            ->mapWithKeys(fn(MentorshipAssignment $assignment): array => [
                (int) $assignment->lecturer_user_id => $assignment->getKey(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function broadcastChatMessage(int $threadId, array $payload): void
    {
        try {
            broadcast(new ChatMessageCreated(
                threadId: $threadId,
                messagePayload: $payload,
            ))->toOthers();
        } catch (Throwable $exception) {
            Log::warning('Chat broadcast skipped because realtime server is unavailable.', [
                'thread_id' => $threadId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function destroy(Request $request, MentorshipDocument $document): RedirectResponse
    {
        $student = $request->user();
        abort_if($student === null, 401);
        abort_unless($document->student_user_id === $student->id, 403);

        if ($this->workspaceDocumentIsLinkedToDefense($student->id, $document)) {
            return redirect()
                ->route('mahasiswa.upload-dokumen')
                ->with('error', $this->linkedDocumentDeleteMessage($student));
        }

        $documentsToDelete = MentorshipDocument::query()
            ->where('student_user_id', $student->id)
            ->where('document_group', $document->document_group)
            ->where('version_number', $document->version_number)
            ->get();

        try {
            $documentsToDelete
                ->filter(fn(MentorshipDocument $version): bool => $version->storage_path !== null && $version->storage_disk !== null)
                ->unique(fn(MentorshipDocument $version): string => sprintf('%s:%s', (string) $version->storage_disk, (string) $version->storage_path))
                ->each(function (MentorshipDocument $version): void {
                    Storage::disk($version->storage_disk)->delete($version->storage_path);
                });
        } catch (Throwable) {
            // noop: keep metadata removal resilient when file already missing.
        }

        $documentsToDelete->each->delete();

        return redirect()
            ->route('mahasiswa.upload-dokumen')
            ->with('success', 'Dokumen berhasil dihapus.');
    }

    private function workspaceDocumentIsLinkedToDefense(int $studentUserId, MentorshipDocument $document): bool
    {
        $relatedDocumentIds = MentorshipDocument::query()
            ->where('student_user_id', $studentUserId)
            ->where('document_group', $document->document_group)
            ->where('version_number', $document->version_number)
            ->pluck('id');

        if ($relatedDocumentIds->isEmpty()) {
            return false;
        }

        return ThesisDocument::query()
            ->whereHas('project', fn($query) => $query->where('student_user_id', $studentUserId))
            ->whereHas('defense', fn($query) => $query->whereIn('type', ['sempro', 'sidang']))
            ->whereIn('source_workspace_document_id', $relatedDocumentIds)
            ->exists();
    }
}
