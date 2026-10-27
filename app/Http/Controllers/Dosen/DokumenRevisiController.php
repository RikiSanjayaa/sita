<?php

namespace App\Http\Controllers\Dosen;

use App\Events\ChatMessageCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dosen\ReviewMentorshipDocumentRequest;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipDocument;
use App\Models\User;
use App\Services\DocumentDetailService;
use App\Services\DocumentRevisionService;
use App\Services\DosenBimbinganService;
use App\Services\RealtimeNotificationService;
use App\Services\SecretSidangExaminers;
use App\Services\UserProfilePresenter;
use App\Support\WitaDateTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DokumenRevisiController extends Controller
{
    public function __construct(
        private readonly DosenBimbinganService $dosenBimbinganService,
        private readonly RealtimeNotificationService $realtimeNotificationService,
        private readonly UserProfilePresenter $userProfilePresenter,
    ) {}

    public function index(Request $request): Response
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        $studentIds = $this->dosenBimbinganService->activeStudentIds($lecturer);

        $documentQueue = MentorshipDocument::query()
            ->with('student.mahasiswaProfile')
            ->where('lecturer_user_id', $lecturer->id)
            ->whereIn('student_user_id', $studentIds)
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->map(function (MentorshipDocument $document): array {
                return [
                    'id' => $document->id,
                    'detailUrl' => DocumentDetailService::workspaceUrl($document, 'dosen'),
                    'mahasiswa' => $document->student?->name ?? '-',
                    'nim' => $document->student?->mahasiswaProfile?->nim ?? '-',
                    'title' => $document->title,
                    'file' => $document->file_name,
                    'uploadedAt' => WitaDateTime::format($document->created_at, withLabel: false),
                    'status' => match (DocumentRevisionService::displayStatus($document)) {
                        'approved' => 'Disetujui',
                        'needs_revision' => 'Perlu Revisi',
                        'revision_completed' => 'Revisi selesai',
                        default => 'Perlu Review',
                    },
                    'revisionNotes' => $document->revision_notes,
                    'fileUrl' => $document->storage_path === null
                        ? $document->file_url
                        : route('files.documents.download', ['document' => $document->id]),
                ];
            })
            ->all();

        return Inertia::render('dosen/dokumen-revisi', [
            'documentQueue' => $documentQueue,
        ]);
    }

    public function review(ReviewMentorshipDocumentRequest $request, MentorshipDocument $document): RedirectResponse
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        $data = $request->validated();

        app(DocumentRevisionService::class)->review($document, $data);

        $this->announceReview($document, $lecturer, $data['status']);

        return back()->with('success', 'Status dokumen berhasil diperbarui.');
    }

    private function announceReview(MentorshipDocument $document, User $lecturer, string $status): void
    {
        $document->loadMissing('student');

        $verdict = $status === 'approved' ? 'disetujui' : 'perlu revisi';
        // Chat attachments of one student share a document group, so their version
        // number is only a counter; name the file instead.
        $subject = $document->category === 'lampiran-chat'
            ? sprintf('lampiran %s', $document->file_name)
            : sprintf('dokumen %s versi v%d', $document->title, $document->version_number);
        // A sidang examiner the mahasiswa may not know yet is announced by role only.
        $secretLabel = $document->student_user_id === null
            ? null
            : app(SecretSidangExaminers::class)->labelFor((int) $document->student_user_id, (int) $lecturer->getKey());
        $description = sprintf('%s menandai %s %s.', $secretLabel ?? $lecturer->name, $subject, $verdict);

        if ($document->student !== null) {
            $this->realtimeNotificationService->notifyUser($document->student, 'statusTugasAkhir', [
                'title' => 'Status dokumen bimbingan diperbarui',
                'description' => $description,
                'url' => DocumentDetailService::workspaceUrl($document, 'mahasiswa'),
                'icon' => 'file-text',
                'createdAt' => now()->toIso8601String(),
            ]);
        }

        $originMessage = MentorshipChatMessage::query()
            ->with('thread')
            ->where('message_type', 'document_event')
            ->where('sender_user_id', $document->student_user_id)
            ->whereIn('related_document_id', app(DocumentDetailService::class)->copies($document)
                ->where('version_number', $document->version_number)->select('id'))
            ->orderBy('id')->first();

        $thread = $originMessage?->thread;

        if ($thread === null) {
            return;
        }

        $message = $thread->messages()->create([
            'sender_user_id' => $lecturer->id,
            'related_document_id' => $document->id,
            'message_type' => 'document_event',
            'message' => $description,
            'sent_at' => now(),
        ]);

        $author = $secretLabel === null ? $this->userProfilePresenter->summary($lecturer) : null;

        $this->broadcastChatMessage($thread->id, [
            'id' => $message->id,
            'senderUserId' => $secretLabel === null ? $lecturer->id : null,
            'author' => $secretLabel ?? $lecturer->name,
            'authorAvatar' => $author['avatar'] ?? null,
            'authorProfileUrl' => $author['profileUrl'] ?? null,
            'message' => $message->message,
            'time' => WitaDateTime::format($message->created_at, withLabel: false),
            'type' => 'document_event',
            'documentEventKind' => 'review',
            'documentName' => $document->file_name,
            'documentUrl' => null,
            'relatedDocumentId' => $document->id,
            'reviewStatus' => $document->status,
            'attachmentKind' => null,
            'attachmentSizeKb' => null,
            'previewUrl' => null,
            'ownReview' => null,
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
}
