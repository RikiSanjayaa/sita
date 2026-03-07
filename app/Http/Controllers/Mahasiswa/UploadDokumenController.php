<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Events\ChatMessageCreated;
use App\Http\Controllers\Controller;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipDocument;
use App\Models\ThesisDefense;
use App\Models\ThesisSupervisorAssignment;
use App\Services\RealtimeNotificationService;
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
    public function __construct(
        private readonly RealtimeNotificationService $realtimeNotificationService,
    ) {}

    public function index(Request $request): Response
    {
        $student = $request->user();
        abort_if($student === null, 401);

        $documents = MentorshipDocument::query()
            ->where('student_user_id', $student->id)
            ->where('uploaded_by_role', 'mahasiswa')
            ->latest('created_at')
            ->get()
            ->groupBy(fn(MentorshipDocument $document): string => sprintf('%s:%d', (string) $document->document_group, $document->version_number))
            ->map(function ($versions): array {
                /** @var MentorshipDocument $document */
                $document = $versions->first();

                return [
                    'id' => $document->id,
                    'title' => $document->title,
                    'category' => $document->category ?? '-',
                    'version' => sprintf('v%d', $document->version_number),
                    'uploadedAt' => $document->created_at->format('d M Y H:i'),
                    'fileName' => $document->file_name,
                    'size' => sprintf('%d KB', (int) $document->file_size_kb),
                    'status' => match ($document->status) {
                        'approved' => 'Disetujui',
                        'needs_revision' => 'Perlu Revisi',
                        default => 'Menunggu Review',
                    },
                    'downloadUrl' => route('files.documents.download', ['document' => $document->id]),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('upload-dokumen', [
            'uploadedDocuments' => $documents,
            'flashMessage' => $request->session()->get('success'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $student = $request->user();
        abort_if($student === null, 401);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'document' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ]);

        $assignments = ThesisSupervisorAssignment::query()
            ->with('lecturer')
            ->whereHas('project', fn($query) => $query
                ->where('student_user_id', $student->id)
                ->where('state', 'active'))
            ->where('status', 'active')
            ->get();

        $activeSemproDefenses = $this->activeSemproDefenses($student->id);
        $pengujiThreads = $this->activeSemproThreads($student->id, $activeSemproDefenses);
        $semproExaminerIds = $activeSemproDefenses
            ->flatMap(fn(ThesisDefense $defense) => $defense->examiners->pluck('lecturer_user_id'))
            ->filter()
            ->map(static fn($id): int => (int) $id)
            ->unique()
            ->values();

        $recipientLecturerIds = $assignments
            ->pluck('lecturer_user_id')
            ->merge($semproExaminerIds)
            ->filter()
            ->map(static fn($id): int => (int) $id)
            ->unique()
            ->values();

        if ($recipientLecturerIds->isEmpty()) {
            return back()->withErrors([
                'document' => 'Belum ada dosen pembimbing atau penguji sempro aktif untuk menerima dokumen.',
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
        $assignmentIdsByLecturer = $assignments
            ->mapWithKeys(fn(ThesisSupervisorAssignment $assignment): array => [
                (int) $assignment->lecturer_user_id => $assignment->getKey(),
            ]);

        DB::transaction(function () use ($assignmentIdsByLecturer, $assignments, $createdDocuments, $data, $disk, $file, $groupKey, $nextVersion, $pengujiThreads, $recipientLecturerIds, $storedPath, $student): void {
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

                $this->broadcastChatMessage($thread->id, $this->mapMessagePayload($message));
            }

            $notifiedUserIds = [];

            foreach ($assignments as $assignment) {
                if ($assignment->lecturer === null) {
                    continue;
                }

                if (in_array($assignment->lecturer->id, $notifiedUserIds, true)) {
                    continue;
                }
                $notifiedUserIds[] = $assignment->lecturer->id;

                $this->realtimeNotificationService->notifyUser($assignment->lecturer, 'statusTugasAkhir', [
                    'title' => 'Dokumen bimbingan baru diunggah',
                    'description' => sprintf('%s mengunggah dokumen %s.', $student->name, trim($data['category'])),
                    'url' => '/dosen/dokumen-revisi',
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
                        'Mahasiswa mengunggah dokumen %s versi v%d (notifikasi ke thread Sempro).',
                        trim($data['category']),
                        $nextVersion,
                    ),
                    'sent_at' => now(),
                ]);

                $this->broadcastChatMessage($pengujiThread->id, $this->mapMessagePayload($pengujiMessage));
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
     * @return array<string, mixed>
     */
    private function mapMessagePayload($message): array
    {
        return [
            'id' => $message->id,
            'senderUserId' => $message->sender_user_id,
            'author' => $message->sender?->name ?? 'Sistem',
            'message' => $message->message,
            'time' => $message->created_at->format('d M Y H:i'),
            'type' => $message->message_type,
            'documentName' => $message->relatedDocument?->file_name ?? $message->attachment_name,
            'documentUrl' => $message->attachment_path === null
                ? ($message->related_document_id === null
                    ? null
                    : route('files.documents.download', ['document' => $message->related_document_id]))
                : route('files.chat-attachments.download', ['message' => $message->id]),
        ];
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

        try {
            if ($document->storage_path !== null && $document->storage_disk !== null) {
                Storage::disk($document->storage_disk)->delete($document->storage_path);
            }
        } catch (Throwable) {
            // noop: keep metadata removal resilient when file already missing.
        }

        MentorshipDocument::query()
            ->where('student_user_id', $student->id)
            ->where('document_group', $document->document_group)
            ->where('version_number', $document->version_number)
            ->delete();

        return redirect()
            ->route('mahasiswa.upload-dokumen')
            ->with('success', 'Dokumen berhasil dihapus.');
    }
}
