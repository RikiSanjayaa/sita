<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Events\ChatMessageCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\StoreChatMessageRequest;
use App\Models\MentorshipAssignment;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipChatThreadParticipant;
use App\Models\MentorshipDocument;
use App\Models\ThesisDefense;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\ChatMessagePayloadBuilder;
use App\Services\ChatUnreadService;
use App\Services\CompletedChatThreadService;
use App\Services\MentorshipTicketService;
use App\Services\PrivateChatService;
use App\Services\RealtimeNotificationService;
use App\Services\SecretSidangExaminers;
use App\Services\ThesisArchiveService;
use App\Services\UserProfilePresenter;
use App\Support\AcademicTerminology;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PesanController extends Controller
{
    public function __construct(
        private readonly PrivateChatService $privateChatService,
        private readonly RealtimeNotificationService $realtimeNotificationService,
        private readonly UserProfilePresenter $userProfilePresenter,
        private readonly ChatMessagePayloadBuilder $payloadBuilder,
        private readonly CompletedChatThreadService $completedChatThreads,
    ) {}

    public function index(Request $request, ThesisArchiveService $archive): Response
    {
        $student = $request->user();
        abort_if($student === null, 401);

        // Pembimbing thread (existing — single thread). An archived thesis had its thread deleted; do not recreate an empty one.
        $archivedProject = $archive->studentHasArchivedProject($student);
        $pembimbingThread = $archivedProject
            ? MentorshipChatThread::query()->ofType('pembimbing')->where('student_user_id', $student->id)->first()
            : MentorshipChatThread::query()
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

        $allThreadIds = array_unique(array_merge($pembimbingThread === null ? [] : [$pembimbingThread->id], $pengujiThreadIds));
        $privateThreadIds = $this->privateChatService->threadsFor($student)
            ->pluck('id')
            ->all();

        $allThreadIds = array_unique(array_merge($allThreadIds, $privateThreadIds));

        $threads = MentorshipChatThread::query()
            ->with([
                'participants.user.roles',
                'participants.user.mahasiswaProfile.programStudi',
                'participants.user.dosenProfile.programStudi',
                'latestMessage.sender',
                'latestMessage.relatedDocument',
                'messages' => fn($query) => $query->with(['sender', 'relatedDocument'])->latest('created_at')->latest('id')->limit(50),
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

        $completedThreadIds = $this->completedChatThreads->completedThreadIds($threads);
        $secretExaminers = app(SecretSidangExaminers::class);

        $threadsData = $threads->map(function (MentorshipChatThread $thread) use ($advisors, $completedThreadIds, $secretExaminers, $student) {
            $messages = $thread->messages
                ->sortBy([['created_at', 'asc'], ['id', 'asc']])
                ->map(fn(MentorshipChatMessage $m): array => $this->payloadBuilder->build($m))
                ->values()
                ->all();

            // Sidang examiners the mahasiswa may not know yet never reach the payload, even if a
            // participant row was left behind (see ThesisDefense::examinersVisibleToStudent()).
            $participants = $thread->type === 'sidang'
                ? $thread->participants->reject(fn(MentorshipChatThreadParticipant $participant): bool => $secretExaminers->isSecret($student->id, (int) $participant->user_id))
                : $thread->participants;

            // Determine members list
            if ($thread->type === 'pembimbing') {
                $members = array_values(array_filter([
                    $student->name,
                    ...$advisors,
                ]));
            } else {
                $members = $participants
                    ->map(fn(MentorshipChatThreadParticipant $participant): string => $participant->user?->name ?? '-')
                    ->values()
                    ->all();
            }

            $memberProfiles = $thread->type === 'pembimbing'
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
                : $participants
                    ->map(fn(MentorshipChatThreadParticipant $participant): ?array => $this->userProfilePresenter->summary($participant->user))
                    ->filter()
                    ->values()
                    ->all();
            $privatePartnerProfile = $thread->type === 'private'
                ? collect($memberProfiles)->firstWhere('id', '!=', $student->id)
                : null;

            return [
                'id' => $thread->id,
                'name' => $privatePartnerProfile['name'] ?? $thread->label ?? ($thread->type === 'pembimbing' ? 'Group Chat Bimbingan' : 'Group Chat Penguji'),
                'threadType' => $thread->type,
                'threadLabel' => $thread->type === 'private'
                    ? ($privatePartnerProfile['roleLabel'] ?? 'Pribadi')
                    : ($thread->label ?? ($thread->type === 'pembimbing' ? 'Bimbingan' : 'Penguji')),
                'members' => $members,
                'memberProfiles' => $memberProfiles,
                'messages' => $messages,
                'preview' => $this->payloadBuilder->displayText($thread->latestMessage?->message) ?? 'Belum ada pesan',
                'lastTime' => $thread->latestMessage?->created_at?->diffForHumans() ?? '-',
                'latestActivityAt' => $thread->latestMessage?->created_at?->toIso8601String(),
                'isCompleted' => isset($completedThreadIds[$thread->id]),
            ];
        })
            ->values()
            ->all();

        return Inertia::render('pesan', [
            'hasDosbing' => ! empty($advisors),
            'threads' => $threadsData,
            'privateRecipients' => $this->privateChatService->recipientOptionsFor($student),
        ]);
    }

    public function storePrivateThread(Request $request, MentorshipTicketService $tickets): RedirectResponse
    {
        $student = $request->user();
        abort_if($student === null, 401);

        $data = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $suspension = $tickets->suspensionMessageForStudent($student->id);

        if ($suspension !== null) {
            return back()->withErrors(['ticket' => $suspension]);
        }

        $recipient = User::query()->findOrFail($data['recipient_id']);
        $thread = $this->privateChatService->findOrCreateThread($student, $recipient);

        return redirect()->route('mahasiswa.pesan', ['thread' => $thread->id]);
    }

    public function markAsRead(Request $request, MentorshipChatThread $thread, ChatUnreadService $unread): JsonResponse
    {
        $student = $request->user();
        abort_if($student === null, 401);
        abort_unless($unread->canSeeThread($student, 'mahasiswa', $thread), 403);

        $data = $request->validate(['last_message_id' => ['required', 'integer']]);
        $unread->markRead($student, $thread, (int) $data['last_message_id']);

        return response()->json(['ok' => true]);
    }

    public function storeMessage(StoreChatMessageRequest $request, MentorshipChatThread $thread, MentorshipTicketService $tickets): RedirectResponse
    {
        $student = $request->user();
        abort_if($student === null, 401);

        // Validate student has access to thread
        $canAccess = $thread->type === 'private'
            ? MentorshipChatThreadParticipant::query()
                ->where('thread_id', $thread->id)
                ->where('user_id', $student->id)
                ->exists()
            : $thread->student_user_id === $student->id;
        abort_unless($canAccess, 403, 'Anda tidak memiliki akses ke thread ini.');

        if ($this->completedChatThreads->isCompleted($thread)) {
            return back()->withErrors(['message' => CompletedChatThreadService::READ_ONLY_MESSAGE]);
        }

        $suspension = $tickets->suspensionMessageForStudent($student->id);

        if ($suspension !== null) {
            return back()->withErrors(['ticket' => $suspension]);
        }

        $data = $request->validated();

        $attachment = $data['attachment'] ?? null;
        $trimmedMessage = trim((string) ($data['message'] ?? ''));
        $documentName = trim((string) ($data['document_name'] ?? ''));

        if ($attachment !== null && str_starts_with((string) $attachment->getMimeType(), 'image/')) {
            return $this->storeImageMessage($thread, $student, $attachment, $trimmedMessage);
        }

        if ($thread->type === 'private') {
            $messages = DB::transaction(function () use ($attachment, $student, $thread, $trimmedMessage) {
                $attachmentPath = null;
                $attachmentDisk = null;
                $attachmentName = null;
                $attachmentMime = null;
                $attachmentSizeKb = null;

                if ($attachment !== null) {
                    $attachmentDisk = 'public';
                    $attachmentPath = $attachment->store(sprintf('chat/private/%d/thread/%d', $student->id, $thread->id), $attachmentDisk);
                    $attachmentName = $attachment->getClientOriginalName();
                    $attachmentMime = $attachment->getClientMimeType();
                    $attachmentSizeKb = (int) ceil($attachment->getSize() / 1024);
                }

                $createdMessages = collect();

                if ($attachment !== null) {
                    $createdMessages->push($thread->messages()->create([
                        'sender_user_id' => $student->id,
                        'attachment_disk' => $attachmentDisk,
                        'attachment_path' => $attachmentPath,
                        'attachment_name' => $attachmentName,
                        'attachment_mime' => $attachmentMime,
                        'attachment_size_kb' => $attachmentSizeKb,
                        'message_type' => 'attachment',
                        'message' => 'Mengirim lampiran',
                        'sent_at' => now(),
                    ]));
                }

                if ($trimmedMessage !== '') {
                    $createdMessages->push($thread->messages()->create([
                        'sender_user_id' => $student->id,
                        'attachment_disk' => $attachmentDisk,
                        'attachment_path' => $attachmentPath,
                        'attachment_name' => $attachmentName,
                        'attachment_mime' => $attachmentMime,
                        'attachment_size_kb' => $attachmentSizeKb,
                        'message_type' => 'text',
                        'message' => $trimmedMessage,
                        'sent_at' => now()->addSecond(),
                    ]));
                }

                return $createdMessages;
            });

            foreach ($messages as $message) {
                $this->broadcastChatMessage($thread->id, $this->payloadBuilder->build($message));
            }

            $this->notifyThreadMembers($student, $thread);

            return back();
        }

        $terms = AcademicTerminology::forStudent($student);
        $activeSemproDefenses = $this->activeSemproDefenses($student->id);
        $semproThreads = $this->activeSemproThreads($student->id, $activeSemproDefenses);

        $result = DB::transaction(function () use ($activeSemproDefenses, $attachment, $documentName, $semproThreads, $student, $terms, $thread, $trimmedMessage): array {
            if ($attachment === null) {
                return [
                    'primary' => collect([
                        $thread->messages()->create([
                            'sender_user_id' => $student->id,
                            'message_type' => 'text',
                            'message' => $trimmedMessage,
                            'sent_at' => now(),
                        ]),
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
                $terms = AcademicTerminology::forStudent($student);
                abort(422, 'Belum ada dosen pembimbing atau penguji '.$terms['proposalExamShort'].' aktif untuk menerima lampiran.');
            }

            $disk = 'public';
            $storedPath = $attachment->store(sprintf('documents/mahasiswa/%d', $student->id), $disk);
            $documentGroup = sprintf('%d:%s', $student->id, 'lampiran-chat');
            $nextVersion = ((int) MentorshipDocument::query()
                ->where('student_user_id', $student->id)
                ->where('document_group', $documentGroup)
                ->max('version_number')) + 1;

            $createdDocuments = collect();
            $assignmentIdsByLecturer = $this->mentorshipAssignmentIdsByLecturer($student->id, $assignments);

            foreach ($recipientLecturerIds as $lecturerUserId) {
                $createdDocuments->push(MentorshipDocument::query()->create([
                    'student_user_id' => $student->id,
                    'lecturer_user_id' => $lecturerUserId,
                    'mentorship_assignment_id' => $assignmentIdsByLecturer->get($lecturerUserId),
                    'title' => $documentName !== '' ? $documentName : $attachment->getClientOriginalName(),
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

            $createdMessages = collect();

            $createdMessages->push($thread->messages()->create([
                'sender_user_id' => $student->id,
                'related_document_id' => $createdDocuments->first()?->id,
                'attachment_disk' => $disk,
                'attachment_path' => $storedPath,
                'attachment_name' => $attachment->getClientOriginalName(),
                'attachment_mime' => $attachment->getClientMimeType(),
                'attachment_size_kb' => (int) ceil($attachment->getSize() / 1024),
                'message_type' => 'document_event',
                'message' => sprintf('Mahasiswa mengunggah lampiran %s.', $attachment->getClientOriginalName()),
                'sent_at' => now(),
            ]));

            if ($trimmedMessage !== '') {
                $createdMessages->push($thread->messages()->create([
                    'sender_user_id' => $student->id,
                    'related_document_id' => $createdDocuments->first()?->id,
                    'message_type' => 'text',
                    'message' => $trimmedMessage,
                    'sent_at' => now()->addSecond(),
                ]));
            }

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
                    'message' => sprintf('Mahasiswa mengunggah lampiran %s (notifikasi ke thread %s).', $attachment->getClientOriginalName(), $terms['proposalExamShort']),
                    'sent_at' => now(),
                ]));

                if ($trimmedMessage !== '') {
                    $mirroredMessages->push($semproThread->messages()->create([
                        'sender_user_id' => $student->id,
                        'related_document_id' => $createdDocuments->first()?->id,
                        'message_type' => 'text',
                        'message' => $trimmedMessage,
                        'sent_at' => now()->addSecond(),
                    ]));
                }

            }

            return [
                'primary' => $createdMessages,
                'mirrored' => $mirroredMessages,
            ];
        });

        /** @var Collection<int, MentorshipChatMessage> $messages */
        $messages = $result['primary'];
        /** @var Collection<int, MentorshipChatMessage> $mirroredMessages */
        $mirroredMessages = $result['mirrored'];

        foreach ($messages as $message) {
            $this->broadcastChatMessage($thread->id, $this->payloadBuilder->build($message));
        }

        foreach ($mirroredMessages as $mirroredMessage) {
            $this->broadcastChatMessage($mirroredMessage->mentorship_chat_thread_id, $this->payloadBuilder->build($mirroredMessage));
        }

        $this->notifyThreadMembers($student, $thread);

        return back();
    }

    private function storeImageMessage(MentorshipChatThread $thread, User $student, UploadedFile $attachment, string $caption): RedirectResponse
    {
        $messages = DB::transaction(function () use ($attachment, $caption, $student, $thread) {
            $storedPath = $attachment->store(sprintf('chat/mahasiswa/%d/thread/%d', $student->id, $thread->id), 'public');

            $createdMessages = collect([
                $thread->messages()->create([
                    'sender_user_id' => $student->id,
                    'attachment_disk' => 'public',
                    'attachment_path' => $storedPath,
                    'attachment_name' => $attachment->getClientOriginalName(),
                    'attachment_mime' => $attachment->getMimeType(),
                    'attachment_size_kb' => (int) ceil($attachment->getSize() / 1024),
                    'message_type' => 'attachment',
                    'message' => 'Mengirim gambar',
                    'sent_at' => now(),
                ]),
            ]);

            if ($caption !== '') {
                $createdMessages->push($thread->messages()->create([
                    'sender_user_id' => $student->id,
                    'message_type' => 'text',
                    'message' => $caption,
                    'sent_at' => now()->addSecond(),
                ]));
            }

            return $createdMessages;
        });

        foreach ($messages as $message) {
            $this->broadcastChatMessage($thread->id, $this->payloadBuilder->build($message));
        }

        $this->notifyThreadMembers($student, $thread);

        return back();
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

    private function notifyThreadMembers(User $student, MentorshipChatThread $thread): void
    {
        if ($thread->type === 'pembimbing') {
            $this->notifyLecturers($student, $thread->id);
        } elseif ($thread->type === 'private') {
            $participants = MentorshipChatThreadParticipant::query()
                ->where('thread_id', $thread->id)
                ->where('user_id', '!=', $student->id)
                ->with('user')
                ->get();

            foreach ($participants as $participant) {
                if (! $participant->user instanceof User) {
                    continue;
                }

                $this->realtimeNotificationService->notifyUser($participant->user, 'pesanBaru', [
                    'title' => 'Pesan pribadi baru',
                    'description' => sprintf('%s mengirim pesan pribadi.', $student->name),
                    'url' => $participant->user->hasRole('mahasiswa')
                        ? sprintf('/mahasiswa/pesan?thread=%d', $thread->id)
                        : sprintf('/dosen/pesan?thread=%d&mode=private', $thread->id),
                    'icon' => 'message-square',
                    'createdAt' => now()->toIso8601String(),
                ]);
            }

            return;
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

                $terms = AcademicTerminology::forStudent($student);
                $examTerm = $thread->type === 'sidang' ? $terms['finalExamShort'] : $terms['proposalExamShort'];
                $this->realtimeNotificationService->notifyUser($participant->user, 'pesanBaru', [
                    'title' => $examTerm.' baru',
                    'description' => sprintf('%s mengirim pesan di thread %s.', $student->name, $examTerm),
                    'url' => '/dosen/pesan',
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
                'url' => sprintf('/dosen/pesan?thread=%d', $threadId),
                'icon' => 'message-square',
                'createdAt' => now()->toIso8601String(),
            ]);
        }
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
