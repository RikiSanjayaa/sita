<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Enums\AssignmentStatus;
use App\Events\ChatMessageCreated;
use App\Http\Controllers\Controller;
use App\Models\MentorshipAssignment;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipChatThreadParticipant;
use App\Models\MentorshipDocument;
use App\Models\User;
use App\Services\RealtimeNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PesanController extends Controller
{
    public function __construct(
        private readonly RealtimeNotificationService $realtimeNotificationService,
    ) {}

    public function index(Request $request): Response
    {
        $student = $request->user();
        abort_if($student === null, 401);

        // Pembimbing thread (existing — single thread)
        $pembimbingThread = MentorshipChatThread::query()
            ->ofType('pembimbing')
            ->firstOrCreate([
                'student_user_id' => $student->id,
                'type' => 'pembimbing',
            ]);

        // Penguji threads (from participant table)
        $pengujiThreadIds = MentorshipChatThreadParticipant::query()
            ->where('user_id', $student->id)
            ->where('role', 'student')
            ->pluck('thread_id')
            ->all();

        $allThreadIds = array_unique(array_merge([$pembimbingThread->id], $pengujiThreadIds));

        $threads = MentorshipChatThread::query()
            ->with([
                'latestMessage.sender',
                'latestMessage.relatedDocument',
                'messages' => fn($query) => $query->with(['sender', 'relatedDocument'])->orderBy('created_at')->limit(50),
            ])
            ->whereIn('id', $allThreadIds)
            ->withMax('messages', 'created_at')
            ->orderByDesc('messages_max_created_at')
            ->orderByDesc('id')
            ->get();

        // Get members for pembimbing thread
        $advisors = MentorshipAssignment::query()
            ->with('lecturer')
            ->where('student_user_id', $student->id)
            ->where('status', AssignmentStatus::Active->value)
            ->get()
            ->map(fn(MentorshipAssignment $a): string => $a->lecturer?->name ?? '-')
            ->unique()
            ->values()
            ->all();

        $threadsData = $threads->map(function (MentorshipChatThread $thread) use ($student, $advisors) {
            $messages = $thread->messages
                ->map(fn(MentorshipChatMessage $m): array => $this->mapMessagePayload($m))
                ->values()
                ->all();

            // Determine members list
            if ($thread->type === 'pembimbing') {
                $members = array_values(array_filter([
                    $student->name,
                    ...$advisors,
                ]));
            } else {
                // Load participants for penguji threads
                $participantNames = MentorshipChatThreadParticipant::query()
                    ->where('thread_id', $thread->id)
                    ->with('user')
                    ->get()
                    ->map(fn(MentorshipChatThreadParticipant $p): string => $p->user?->name ?? '-')
                    ->all();
                $members = $participantNames;
            }

            return [
                'id' => $thread->id,
                'name' => $thread->label ?? ($thread->type === 'pembimbing' ? 'Group Chat Bimbingan' : 'Group Chat Penguji'),
                'threadType' => $thread->type,
                'threadLabel' => $thread->label ?? ($thread->type === 'pembimbing' ? 'Bimbingan' : 'Penguji'),
                'members' => $members,
                'messages' => $messages,
                'preview' => $thread->latestMessage?->message ?? 'Belum ada pesan',
                'lastTime' => $thread->latestMessage?->created_at?->diffForHumans() ?? '-',
            ];
        })
            ->values()
            ->all();

        return Inertia::render('pesan', [
            'hasDosbing' => ! empty($advisors),
            'threads' => $threadsData,
            'flashMessage' => $request->session()->get('success'),
        ]);
    }

    public function storeMessage(Request $request, MentorshipChatThread $thread): RedirectResponse
    {
        $student = $request->user();
        abort_if($student === null, 401);

        // Validate student has access to thread
        $canAccess = $thread->student_user_id === $student->id;
        abort_unless($canAccess, 403, 'Anda tidak memiliki akses ke thread ini.');

        $data = $request->validate([
            'message' => ['required_without:attachment', 'nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ]);

        $attachment = $data['attachment'] ?? null;
        $trimmedMessage = trim((string) ($data['message'] ?? ''));

        $message = DB::transaction(function () use ($attachment, $student, $thread, $trimmedMessage): MentorshipChatMessage {
            if ($attachment === null) {
                return $thread->messages()->create([
                    'sender_user_id' => $student->id,
                    'message_type' => 'text',
                    'message' => $trimmedMessage,
                    'sent_at' => now(),
                ]);
            }

            $assignments = MentorshipAssignment::query()
                ->where('student_user_id', $student->id)
                ->where('status', AssignmentStatus::Active->value)
                ->get();

            if ($assignments->isEmpty() && $thread->type === 'pembimbing') {
                abort(422, 'Belum ada dosen pembimbing aktif untuk menerima lampiran.');
            }

            $disk = 'public';
            $storedPath = $attachment->store(sprintf('documents/mahasiswa/%d', $student->id), $disk);
            $documentGroup = sprintf('%d:%s', $student->id, 'lampiran-chat');
            $nextVersion = ((int) MentorshipDocument::query()
                ->where('student_user_id', $student->id)
                ->where('document_group', $documentGroup)
                ->max('version_number')) + 1;

            $createdDocuments = collect();

            foreach ($assignments as $assignment) {
                $createdDocuments->push(MentorshipDocument::query()->create([
                    'student_user_id' => $student->id,
                    'lecturer_user_id' => $assignment->lecturer_user_id,
                    'mentorship_assignment_id' => $assignment->id,
                    'title' => $trimmedMessage !== ''
                        ? $trimmedMessage
                        : sprintf('Lampiran chat v%d', $nextVersion),
                    'category' => 'lampiran-chat',
                    'document_group' => $documentGroup,
                    'version_number' => $nextVersion,
                    'file_name' => $attachment->getClientOriginalName(),
                    'file_url' => null,
                    'storage_disk' => $disk,
                    'storage_path' => $storedPath,
                    'mime_type' => $attachment->getClientMimeType(),
                    'file_size_kb' => (int) ceil($attachment->getSize() / 1024),
                    'status' => 'submitted',
                    'revision_notes' => null,
                    'reviewed_at' => null,
                    'uploaded_by_user_id' => $student->id,
                    'uploaded_by_role' => 'mahasiswa',
                ]));
            }

            return $thread->messages()->create([
                'sender_user_id' => $student->id,
                'related_document_id' => $createdDocuments->first()?->id,
                'message_type' => 'document_event',
                'message' => sprintf('Mahasiswa mengunggah dokumen lampiran chat versi v%d.', $nextVersion),
                'sent_at' => now(),
            ]);
        });

        $this->broadcastChatMessage($thread->id, $this->mapMessagePayload($message));
        $this->notifyThreadMembers($student, $thread);

        return back()->with('success', 'Pesan berhasil dikirim.');
    }

    private function notifyThreadMembers(User $student, MentorshipChatThread $thread): void
    {
        if ($thread->type === 'pembimbing') {
            $this->notifyLecturers($student, $thread->id);
        } else {
            // Notify all participants except self
            $participants = MentorshipChatThreadParticipant::query()
                ->where('thread_id', $thread->id)
                ->where('user_id', '!=', $student->id)
                ->with('user')
                ->get();

            $notifiedUserIds = [];

            foreach ($participants as $participant) {
                if ($participant->user === null) {
                    continue;
                }

                if (in_array($participant->user->id, $notifiedUserIds, true)) {
                    continue;
                }
                $notifiedUserIds[] = $participant->user->id;

                $this->realtimeNotificationService->notifyUser($participant->user, 'pesanBaru', [
                    'title' => 'Pesan sempro baru',
                    'description' => sprintf('%s mengirim pesan di thread Sempro.', $student->name),
                    'url' => '/dosen/pesan-bimbingan',
                    'icon' => 'message-square',
                    'createdAt' => now()->toIso8601String(),
                ]);
            }
        }
    }

    private function notifyLecturers(User $student, int $threadId): void
    {
        $lecturers = MentorshipAssignment::query()
            ->where('student_user_id', $student->id)
            ->where('status', AssignmentStatus::Active->value)
            ->with('lecturer')
            ->get()
            ->pluck('lecturer')
            ->filter()
            ->unique('id');

        foreach ($lecturers as $lecturer) {
            $this->realtimeNotificationService->notifyUser($lecturer, 'pesanBaru', [
                'title' => 'Pesan bimbingan baru',
                'description' => sprintf('%s mengirim pesan baru.', $student->name),
                'url' => sprintf('/dosen/pesan-bimbingan?thread=%d', $threadId),
                'icon' => 'message-square',
                'createdAt' => now()->toIso8601String(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMessagePayload(MentorshipChatMessage $message): array
    {
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
