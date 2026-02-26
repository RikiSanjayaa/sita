<?php

namespace App\Http\Controllers\Dosen;

use App\Events\ChatMessageCreated;
use App\Http\Controllers\Controller;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatRead;
use App\Models\MentorshipChatThread;
use App\Services\DosenBimbinganService;
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
    ) {}

    public function index(Request $request): Response
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        $studentIds = $this->dosenBimbinganService->activeStudentIds($lecturer);

        $threads = MentorshipChatThread::query()
            ->with([
                'student',
                'latestMessage.sender',
                'latestMessage.relatedDocument',
                'messages' => fn ($query) => $query->with(['sender', 'relatedDocument'])->latest('created_at')->limit(30),
            ])
            ->whereIn('student_user_id', $studentIds)
            ->get();

        $readsByThread = MentorshipChatRead::query()
            ->where('user_id', $lecturer->id)
            ->whereIn('mentorship_chat_thread_id', $threads->pluck('id'))
            ->get()
            ->keyBy('mentorship_chat_thread_id');

        $threads = $threads
            ->map(function (MentorshipChatThread $thread) use ($lecturer, $readsByThread): array {
                $latestMessage = $thread->latestMessage;
                $lastReadAt = $readsByThread->get($thread->id)?->last_read_at;
                $unreadCount = MentorshipChatMessage::query()
                    ->where('mentorship_chat_thread_id', $thread->id)
                    ->where('sender_user_id', '!=', $lecturer->id)
                    ->when($lastReadAt !== null, function ($query) use ($lastReadAt) {
                        $query->where('created_at', '>', $lastReadAt);
                    })
                    ->count();

                return [
                    'id' => $thread->id,
                    'student' => $thread->student?->name ?? '-',
                    'unread' => $unreadCount,
                    'preview' => $latestMessage?->message ?? 'Belum ada pesan',
                    'lastTime' => $latestMessage?->created_at?->diffForHumans() ?? '-',
                    'isEscalated' => $thread->is_escalated,
                    'messages' => $thread->messages
                        ->sortBy('created_at')
                        ->values()
                        ->map(function (MentorshipChatMessage $message): array {
                            return [
                                'id' => $message->id,
                                'senderUserId' => $message->sender_user_id,
                                'author' => $message->sender?->name ?? 'Sistem',
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
            'flashMessage' => $request->session()->get('success'),
        ]);
    }

    public function markAsRead(Request $request, MentorshipChatThread $thread): JsonResponse
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        $studentIds = $this->dosenBimbinganService->activeStudentIds($lecturer);
        abort_unless(in_array($thread->student_user_id, $studentIds, true), 403);

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

        $studentIds = $this->dosenBimbinganService->activeStudentIds($lecturer);
        abort_unless(in_array($thread->student_user_id, $studentIds, true), 403);

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

        $this->broadcastChatMessage($thread->id, [
            'id' => $message->id,
            'senderUserId' => $message->sender_user_id,
            'author' => $lecturer->name,
            'message' => $message->message,
            'time' => $message->created_at->format('d M Y H:i'),
            'type' => $message->message_type,
            'documentName' => $message->attachment_name,
            'documentUrl' => $message->attachment_path === null
                ? null
                : route('files.chat-attachments.download', ['message' => $message->id]),
        ]);

        return back()->with('success', 'Pesan berhasil dikirim.');
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
