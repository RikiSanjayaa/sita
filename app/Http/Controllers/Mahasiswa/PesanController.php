<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Enums\AssignmentStatus;
use App\Events\ChatMessageCreated;
use App\Http\Controllers\Controller;
use App\Models\MentorshipAssignment;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $trimmedMessage = trim((string) ($data['message'] ?? ''));

        $message = DB::transaction(function () use (
            $attachment,
            $student,
            $thread,
            $trimmedMessage,
        ): MentorshipChatMessage {
            if ($attachment === null) {
                $textMessage = $thread->messages()->create([
                    'sender_user_id' => $student->id,
                    'message_type' => 'text',
                    'message' => $trimmedMessage,
                    'sent_at' => now(),
                ]);

                return $textMessage;
            }

            $assignments = MentorshipAssignment::query()
                ->where('student_user_id', $student->id)
                ->where('status', AssignmentStatus::Active->value)
                ->get();

            if ($assignments->isEmpty()) {
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

            $systemMessage = sprintf(
                'Mahasiswa mengunggah dokumen lampiran chat versi v%d.',
                $nextVersion,
            );

            $documentMessage = $thread->messages()->create([
                'sender_user_id' => $student->id,
                'related_document_id' => $createdDocuments->first()?->id,
                'message_type' => 'document_event',
                'message' => $systemMessage,
                'sent_at' => now(),
            ]);

            return $documentMessage;
        });

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
