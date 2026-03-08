<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Events\ChatMessageCreated;
use App\Http\Controllers\Controller;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipChatThreadParticipant;
use App\Models\MentorshipDocument;
use App\Models\ThesisDefense;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\RealtimeNotificationService;
use App\Services\UserProfilePresenter;
use Illuminate\Database\Eloquent\Collection;
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
        private readonly UserProfilePresenter $userProfilePresenter,
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
        $advisors = ThesisSupervisorAssignment::query()
            ->with('lecturer')
            ->whereHas('project', fn($query) => $query
                ->where('student_user_id', $student->id)
                ->where('state', 'active'))
            ->where('status', 'active')
            ->get()
            ->map(fn(ThesisSupervisorAssignment $assignment): string => $assignment->lecturer?->name ?? '-')
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
                'memberProfiles' => $thread->type === 'pembimbing'
                    ? array_values(array_filter([
                        $this->userProfilePresenter->summary($student),
                        ...ThesisSupervisorAssignment::query()
                            ->with(['lecturer.roles', 'lecturer.dosenProfile.programStudi'])
                            ->whereHas('project', fn($query) => $query
                                ->where('student_user_id', $student->id)
                                ->where('state', 'active'))
                            ->where('status', 'active')
                            ->get()
                            ->map(fn(ThesisSupervisorAssignment $assignment): ?array => $this->userProfilePresenter->summary($assignment->lecturer))
                            ->all(),
                    ]))
                    : MentorshipChatThreadParticipant::query()
                        ->where('thread_id', $thread->id)
                        ->with(['user.roles', 'user.mahasiswaProfile.programStudi', 'user.dosenProfile.programStudi'])
                        ->get()
                        ->map(fn(MentorshipChatThreadParticipant $participant): ?array => $this->userProfilePresenter->summary($participant->user))
                        ->filter()
                        ->values()
                        ->all(),
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
        $activeSemproDefenses = $this->activeSemproDefenses($student->id);
        $semproThreads = $this->activeSemproThreads($student->id, $activeSemproDefenses);

        $result = DB::transaction(function () use ($activeSemproDefenses, $attachment, $semproThreads, $student, $thread, $trimmedMessage): array {
            if ($attachment === null) {
                return [
                    'primary' => $thread->messages()->create([
                        'sender_user_id' => $student->id,
                        'message_type' => 'text',
                        'message' => $trimmedMessage,
                        'sent_at' => now(),
                    ]),
                    'mirrored' => collect(),
                ];
            }

            $assignments = ThesisSupervisorAssignment::query()
                ->whereHas('project', fn($query) => $query
                    ->where('student_user_id', $student->id)
                    ->where('state', 'active'))
                ->where('status', 'active')
                ->get();

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
                abort(422, 'Belum ada dosen pembimbing atau penguji sempro aktif untuk menerima lampiran.');
            }

            $disk = 'public';
            $storedPath = $attachment->store(sprintf('documents/mahasiswa/%d', $student->id), $disk);
            $documentGroup = sprintf('%d:%s', $student->id, 'lampiran-chat');
            $nextVersion = ((int) MentorshipDocument::query()
                ->where('student_user_id', $student->id)
                ->where('document_group', $documentGroup)
                ->max('version_number')) + 1;

            $createdDocuments = collect();
            $assignmentIdsByLecturer = $assignments
                ->mapWithKeys(fn(ThesisSupervisorAssignment $assignment): array => [
                    (int) $assignment->lecturer_user_id => $assignment->getKey(),
                ]);

            foreach ($recipientLecturerIds as $lecturerUserId) {
                $createdDocuments->push(MentorshipDocument::query()->create([
                    'student_user_id' => $student->id,
                    'lecturer_user_id' => $lecturerUserId,
                    'mentorship_assignment_id' => $assignmentIdsByLecturer->get($lecturerUserId),
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
                    'stored_file_name' => basename($storedPath),
                    'mime_type' => $attachment->getClientMimeType(),
                    'file_size_kb' => (int) ceil($attachment->getSize() / 1024),
                    'status' => 'submitted',
                    'revision_notes' => null,
                    'reviewed_at' => null,
                    'uploaded_by_user_id' => $student->id,
                    'uploaded_by_role' => 'mahasiswa',
                ]));
            }

            $message = $thread->messages()->create([
                'sender_user_id' => $student->id,
                'related_document_id' => $createdDocuments->first()?->id,
                'attachment_disk' => $disk,
                'attachment_path' => $storedPath,
                'attachment_name' => $attachment->getClientOriginalName(),
                'attachment_mime' => $attachment->getClientMimeType(),
                'attachment_size_kb' => (int) ceil($attachment->getSize() / 1024),
                'message_type' => 'document_event',
                'message' => sprintf('Mahasiswa mengunggah dokumen lampiran chat versi v%d.', $nextVersion),
                'sent_at' => now(),
            ]);

            $mirroredMessages = collect();

            foreach ($semproThreads as $semproThread) {
                if ($semproThread->getKey() === $thread->getKey()) {
                    continue;
                }

                $mirroredMessages->push($semproThread->messages()->create([
                    'sender_user_id' => $student->id,
                    'related_document_id' => $createdDocuments->first()?->id,
                    'attachment_disk' => $disk,
                    'attachment_path' => $storedPath,
                    'attachment_name' => $attachment->getClientOriginalName(),
                    'attachment_mime' => $attachment->getClientMimeType(),
                    'attachment_size_kb' => (int) ceil($attachment->getSize() / 1024),
                    'message_type' => 'document_event',
                    'message' => sprintf('Mahasiswa mengunggah dokumen lampiran chat versi v%d (notifikasi ke thread Sempro).', $nextVersion),
                    'sent_at' => now(),
                ]));
            }

            return [
                'primary' => $message,
                'mirrored' => $mirroredMessages,
            ];
        });

        /** @var MentorshipChatMessage $message */
        $message = $result['primary'];
        /** @var Collection<int, MentorshipChatMessage> $mirroredMessages */
        $mirroredMessages = $result['mirrored'];

        $this->broadcastChatMessage($thread->id, $this->mapMessagePayload($message));

        foreach ($mirroredMessages as $mirroredMessage) {
            $this->broadcastChatMessage($mirroredMessage->mentorship_chat_thread_id, $this->mapMessagePayload($mirroredMessage));
        }

        $this->notifyThreadMembers($student, $thread);

        return back()->with('success', 'Pesan berhasil dikirim.');
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
        $lecturers = ThesisSupervisorAssignment::query()
            ->whereHas('project', fn($query) => $query
                ->where('student_user_id', $student->id)
                ->where('state', 'active'))
            ->where('status', 'active')
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
