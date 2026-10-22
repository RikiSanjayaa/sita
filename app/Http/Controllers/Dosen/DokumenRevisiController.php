<?php

namespace App\Http\Controllers\Dosen;

use App\Events\ChatMessageCreated;
use App\Http\Controllers\Controller;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipDocument;
use App\Models\User;
use App\Services\DosenBimbinganService;
use App\Services\RealtimeNotificationService;
use App\Services\UserProfilePresenter;
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
                    'mahasiswa' => $document->student?->name ?? '-',
                    'nim' => $document->student?->mahasiswaProfile?->nim ?? '-',
                    'title' => $document->title,
                    'file' => $document->file_name,
                    'uploadedAt' => $document->created_at->format('d M Y H:i'),
                    'status' => match ($document->status) {
                        'approved' => 'Disetujui',
                        'needs_revision' => 'Perlu Revisi',
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
            'flashMessage' => $request->session()->get('success'),
        ]);
    }

    public function review(Request $request, MentorshipDocument $document): RedirectResponse
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);
        abort_unless($document->lecturer_user_id === $lecturer->id, 403);

        $data = $request->validate([
            'status' => ['required', 'in:needs_revision,approved'],
            'revision_notes' => ['nullable', 'string'],
        ]);

        $document->forceFill([
            'status' => $data['status'],
            'revision_notes' => $data['revision_notes'] ?? null,
            'reviewed_at' => now(),
        ])->save();

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
        $description = sprintf('%s menandai %s %s.', $lecturer->name, $subject, $verdict);

        if ($document->student !== null) {
            $this->realtimeNotificationService->notifyUser($document->student, 'statusTugasAkhir', [
                'title' => 'Status dokumen bimbingan diperbarui',
                'description' => $description,
                'url' => '/mahasiswa/upload-dokumen',
                'icon' => 'file-text',
                'createdAt' => now()->toIso8601String(),
            ]);
        }

        $originMessage = MentorshipChatMessage::query()
            ->where('message_type', 'document_event')
            ->whereIn('related_document_id', MentorshipDocument::query()
                ->where('student_user_id', $document->student_user_id)
                ->where('document_group', $document->document_group)
                ->select('id'))
            ->orderBy('id')
            ->first();

        $thread = $originMessage === null
            ? null
            : MentorshipChatThread::query()->find($originMessage->mentorship_chat_thread_id);

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

        $author = $this->userProfilePresenter->summary($lecturer);

        $this->broadcastChatMessage($thread->id, [
            'id' => $message->id,
            'senderUserId' => $lecturer->id,
            'author' => $lecturer->name,
            'authorAvatar' => $author['avatar'] ?? null,
            'authorProfileUrl' => $author['profileUrl'] ?? null,
            'message' => $message->message,
            'time' => $message->created_at->format('d M Y H:i'),
            'type' => 'document_event',
            'documentName' => $document->file_name,
            'documentUrl' => null,
            'relatedDocumentId' => $document->id,
            'reviewStatus' => $document->status,
            'attachmentKind' => null,
            'attachmentSizeKb' => null,
            'previewUrl' => null,
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
