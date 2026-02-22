<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Enums\AssignmentStatus;
use App\Events\ChatMessageCreated;
use App\Http\Controllers\Controller;
use App\Models\MentorshipAssignment;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PesanController extends Controller
{
    public function index(Request $request): Response
    {
        $student = $request->user();
        abort_if($student === null, 401);

        $thread = MentorshipChatThread::query()
            ->with([
                'messages' => fn ($query) => $query->with(['sender', 'relatedDocument'])->orderBy('created_at')->limit(50),
            ])
            ->firstOrCreate([
                'student_user_id' => $student->id,
            ]);

        $advisors = MentorshipAssignment::query()
            ->with('lecturer')
            ->where('student_user_id', $student->id)
            ->where('status', AssignmentStatus::Active->value)
            ->get()
            ->map(fn (MentorshipAssignment $assignment): string => $assignment->lecturer?->name ?? '-')
            ->unique()
            ->values()
            ->all();

        $messages = $thread->messages
            ->map(fn (MentorshipChatMessage $message): array => $this->mapMessagePayload($message))
            ->values()
            ->all();

        return Inertia::render('pesan', [
            'thread' => [
                'id' => $thread->id,
                'name' => 'Group Chat Bimbingan',
                'members' => array_values(array_filter([
                    $student->name,
                    ...$advisors,
                ])),
                'messages' => $messages,
            ],
            'flashMessage' => $request->session()->get('success'),
        ]);
    }

    public function storeMessage(Request $request): RedirectResponse
    {
        $student = $request->user();
        abort_if($student === null, 401);

        $data = $request->validate([
            'message' => ['required_without:attachment', 'nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ]);

        $thread = MentorshipChatThread::query()->firstOrCreate([
            'student_user_id' => $student->id,
        ]);

        $attachment = $data['attachment'] ?? null;
        $attachmentPath = null;
        $attachmentDisk = null;
        $attachmentName = null;
        $attachmentMime = null;
        $attachmentSizeKb = null;

        if ($attachment !== null) {
            $attachmentDisk = 'public';
            $attachmentPath = $attachment->store(sprintf('chat/mahasiswa/%d', $student->id), $attachmentDisk);
            $attachmentName = $attachment->getClientOriginalName();
            $attachmentMime = $attachment->getClientMimeType();
            $attachmentSizeKb = (int) ceil($attachment->getSize() / 1024);
        }

        $message = $thread->messages()->create([
            'sender_user_id' => $student->id,
            'message_type' => $attachmentPath === null ? 'text' : 'attachment',
            'message' => trim((string) ($data['message'] ?? '')),
            'attachment_disk' => $attachmentDisk,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'attachment_mime' => $attachmentMime,
            'attachment_size_kb' => $attachmentSizeKb,
            'sent_at' => now(),
        ]);

        $this->broadcastChatMessage($thread->id, $this->mapMessagePayload($message));

        return back()->with('success', 'Pesan berhasil dikirim.');
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMessagePayload(MentorshipChatMessage $message): array
    {
        return [
            'id' => $message->id,
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
