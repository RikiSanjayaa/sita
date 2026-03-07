<?php

namespace App\Http\Controllers\Dosen;

use App\Events\ChatMessageCreated;
use App\Http\Controllers\Controller;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatRead;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipChatThreadParticipant;
use App\Models\User;
use App\Services\DosenBimbinganService;
use App\Services\RealtimeNotificationService;
use App\Services\UserProfilePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PesanBimbinganController extends Controller
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

        $tab = $request->query('tab', 'aktif');

        // Bimbingan threads (existing: by student IDs from assignment)
        $studentIds = $tab === 'arsip'
            ? $this->dosenBimbinganService->archivedStudentIds($lecturer)
            : $this->dosenBimbinganService->activeStudentIds($lecturer);

        $bimbinganThreads = MentorshipChatThread::query()
            ->ofType('pembimbing')
            ->whereIn('student_user_id', $studentIds)
            ->pluck('id')
            ->all();

        // Penguji threads (new: by participant table)
        $pengujiThreadIds = MentorshipChatThreadParticipant::query()
            ->where('user_id', $lecturer->id)
            ->where('role', 'examiner')
            ->pluck('thread_id')
            ->all();

        $allThreadIds = array_unique(array_merge($bimbinganThreads, $pengujiThreadIds));

        $threads = MentorshipChatThread::query()
            ->with([
                'student',
                'latestMessage.sender',
                'latestMessage.relatedDocument',
                'messages' => fn($query) => $query->with(['sender', 'relatedDocument'])->latest('created_at')->limit(30),
            ])
            ->whereIn('id', $allThreadIds)
            ->withMax('messages', 'created_at')
            ->orderByDesc('messages_max_created_at')
            ->orderByDesc('id')
            ->get();

        $readsByThread = MentorshipChatRead::query()
            ->where('user_id', $lecturer->id)
            ->whereIn('mentorship_chat_thread_id', $threads->pluck('id'))
            ->get()
            ->keyBy('mentorship_chat_thread_id');

        $threads = $threads
            ->map(function (MentorshipChatThread $thread) use ($lecturer, $readsByThread, $tab): array {
                $latestMessage = $thread->latestMessage;
                $lastReadAt = $readsByThread->get($thread->id)?->last_read_at;
                $unreadCount = MentorshipChatMessage::query()
                    ->where('mentorship_chat_thread_id', $thread->id)
                    ->where('sender_user_id', '!=', $lecturer->id)
                    ->where('message_type', '!=', 'document_event') // Do not count system events as separate unread messages
                    ->when($lastReadAt !== null, function ($query) use ($lastReadAt) {
                        $query->where('created_at', '>', $lastReadAt);
                    })
                    ->count();

                return [
                    'id' => $thread->id,
                    'student' => $thread->student?->name ?? '-',
                    'studentProfile' => $this->userProfilePresenter->summary($thread->student),
                    'unread' => $unreadCount,
                    'preview' => $latestMessage?->message ?? 'Belum ada pesan',
                    'lastTime' => $latestMessage?->created_at?->diffForHumans() ?? '-',
                    'latestActivityAt' => $latestMessage?->created_at?->toIso8601String(),
                    'isEscalated' => $thread->is_escalated,
                    'isArchived' => $tab === 'arsip',
                    'threadType' => $thread->type,
                    'threadLabel' => $thread->label ?? ($thread->type === 'pembimbing' ? 'Bimbingan' : 'Penguji'),
                    'messages' => $thread->messages
                        ->sortBy('created_at')
                        ->values()
                        ->map(function (MentorshipChatMessage $message): array {
                            $author = $this->userProfilePresenter->summary($message->sender);

                            return [
                                'id' => $message->id,
                                'senderUserId' => $message->sender_user_id,
                                'author' => $message->sender?->name ?? 'Sistem',
                                'authorAvatar' => $author['avatar'] ?? null,
                                'authorProfileUrl' => $author['profileUrl'] ?? null,
                                'message' => $message->message,
                                'time' => $message->created_at->format('d M Y H:i'),
                                'type' => $message->message_type,
                                'documentName' => $message->attachment_name ?? $message->relatedDocument?->file_name,
                                'documentUrl' => $message->attachment_path === null
                                    ? ($message->related_document_id === null
                                        ? null
                                        : route('files.documents.download', ['document' => $message->related_document_id]))
                                    : route('files.chat-attachments.download', ['message' => $message->id]),
                            ];
                        })
                        ->all(),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('dosen/pesan-bimbingan', [
            'threads' => $threads,
            'tab' => $tab,
            'flashMessage' => $request->session()->get('success'),
        ]);
    }

    public function markAsRead(Request $request, MentorshipChatThread $thread): JsonResponse
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        // Allow marking as read for bimbingan threads (by student assignment) or penguji threads (by participant)
        $canAccess = $this->canAccessThread($lecturer, $thread);
        abort_unless($canAccess, 403);

        $lastMessageAt = $thread->messages()->max('created_at');

        MentorshipChatRead::query()->updateOrCreate(
            [
                'mentorship_chat_thread_id' => $thread->id,
                'user_id' => $lecturer->id,
            ],
            [
                'last_read_at' => $lastMessageAt ?? now(),
            ],
        );

        return response()->json(['ok' => true]);
    }

    public function storeMessage(Request $request, MentorshipChatThread $thread): RedirectResponse
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        $canAccess = $this->canAccessThread($lecturer, $thread);
        abort_unless($canAccess, 403, 'Anda tidak memiliki akses ke thread ini.');

        $data = $request->validate([
            'message' => ['required_without:attachment', 'nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ]);

        $attachment = $data['attachment'] ?? null;
        $attachmentPath = null;
        $attachmentDisk = null;
        $attachmentName = null;
        $attachmentMime = null;
        $attachmentSizeKb = null;

        if ($attachment !== null) {
            $attachmentDisk = 'public';
            $attachmentPath = $attachment->store(
                sprintf('chat/dosen/%d/student/%d', $lecturer->id, $thread->student_user_id),
                $attachmentDisk,
            );
            $attachmentName = $attachment->getClientOriginalName();
            $attachmentMime = $attachment->getClientMimeType();
            $attachmentSizeKb = (int) ceil($attachment->getSize() / 1024);
        }

        $message = $thread->messages()->create([
            'sender_user_id' => $lecturer->id,
            'message_type' => $attachmentPath === null ? 'text' : 'revision_suggestion',
            'message' => trim((string) ($data['message'] ?? '')),
            'attachment_disk' => $attachmentDisk,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'attachment_mime' => $attachmentMime,
            'attachment_size_kb' => $attachmentSizeKb,
            'sent_at' => now(),
        ]);

        $author = $this->userProfilePresenter->summary($lecturer);

        $this->broadcastChatMessage($thread->id, [
            'id' => $message->id,
            'senderUserId' => $message->sender_user_id,
            'author' => $lecturer->name,
            'authorAvatar' => $author['avatar'] ?? null,
            'authorProfileUrl' => $author['profileUrl'] ?? null,
            'message' => $message->message,
            'time' => $message->created_at->format('d M Y H:i'),
            'type' => $message->message_type,
            'documentName' => $message->attachment_name,
            'documentUrl' => $message->attachment_path === null
                ? null
                : route('files.chat-attachments.download', ['message' => $message->id]),
        ]);

        $this->notifyStudent($thread, $lecturer, $message->message_type);

        return back()->with('success', 'Pesan berhasil dikirim.');
    }

    private function canAccessThread(User $lecturer, MentorshipChatThread $thread): bool
    {
        // Bimbingan thread: check student assignment
        if ($thread->type === 'pembimbing') {
            $activeIds = $this->dosenBimbinganService->activeStudentIds($lecturer);
            $archivedIds = $this->dosenBimbinganService->archivedStudentIds($lecturer);

            return in_array($thread->student_user_id, array_merge($activeIds, $archivedIds), true);
        }

        // Penguji/other threads: check participant table
        return MentorshipChatThreadParticipant::query()
            ->where('thread_id', $thread->id)
            ->where('user_id', $lecturer->id)
            ->exists();
    }

    private function notifyStudent(MentorshipChatThread $thread, User $lecturer, string $messageType): void
    {
        $student = $thread->student;

        if (! $student instanceof User) {
            return;
        }

        $preferenceKey = $messageType === 'revision_suggestion'
            ? 'feedbackDokumen'
            : 'pesanBaru';

        $title = $messageType === 'revision_suggestion'
            ? 'Feedback dokumen baru'
            : 'Pesan bimbingan baru';

        $this->realtimeNotificationService->notifyUser($student, $preferenceKey, [
            'title' => $title,
            'description' => sprintf('%s mengirim pembaruan bimbingan.', $lecturer->name),
            'url' => '/mahasiswa/pesan',
            'icon' => $messageType === 'revision_suggestion' ? 'file-text' : 'message-square',
            'createdAt' => now()->toIso8601String(),
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
