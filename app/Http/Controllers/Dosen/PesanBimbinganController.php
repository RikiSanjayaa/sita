<?php

namespace App\Http\Controllers\Dosen;

use App\Events\ChatMessageCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\StoreChatMessageRequest;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipChatThreadParticipant;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\ChatMessagePayloadBuilder;
use App\Services\ChatUnreadService;
use App\Services\CompletedChatThreadService;
use App\Services\DosenBimbinganService;
use App\Services\PrivateChatService;
use App\Services\RealtimeNotificationService;
use App\Services\UserProfilePresenter;
use App\Support\AcademicTerminology;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PesanBimbinganController extends Controller
{
    public function __construct(
        private readonly DosenBimbinganService $dosenBimbinganService,
        private readonly PrivateChatService $privateChatService,
        private readonly RealtimeNotificationService $realtimeNotificationService,
        private readonly UserProfilePresenter $userProfilePresenter,
        private readonly ChatMessagePayloadBuilder $payloadBuilder,
        private readonly CompletedChatThreadService $completedChatThreads,
    ) {}

    public function index(Request $request): Response
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        $activeStudentIds = $this->dosenBimbinganService->activeStudentIds($lecturer);
        $archivedStudentIds = $this->dosenBimbinganService->archivedStudentIds($lecturer);
        $studentIds = array_values(array_unique(array_merge($activeStudentIds, $archivedStudentIds)));

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
        $privateThreadIds = $this->privateChatService->threadsFor($lecturer)
            ->pluck('id')
            ->all();

        $allThreadIds = array_unique(array_merge($allThreadIds, $privateThreadIds));

        $threads = MentorshipChatThread::query()
            ->with([
                'student',
                'participants.user.roles',
                'participants.user.mahasiswaProfile.programStudi',
                'participants.user.dosenProfile.programStudi',
                'latestMessage.sender',
                'latestMessage.relatedDocument',
                'messages' => fn($query) => $query->with(['sender', 'relatedDocument'])->latest('created_at')->latest('id')->limit(30),
            ])
            ->whereIn('id', $allThreadIds)
            ->withMax('messages', 'created_at')
            ->orderByDesc('messages_max_created_at')
            ->orderByDesc('id')
            ->get();

        $completedThreadIds = $this->completedChatThreads->completedThreadIds($threads);

        $defensesById = ThesisDefense::query()
            ->with('project')
            ->whereIn('id', $threads
                ->whereIn('type', ['sempro', 'sidang'])
                ->pluck('context_id')
                ->filter()
                ->unique()
                ->values())
            ->get()
            ->keyBy('id');

        $ownReviews = $this->payloadBuilder->ownReviewsFor(
            $lecturer,
            $threads
                ->flatMap(fn (MentorshipChatThread $thread) => $thread->messages)
                ->where('message_type', 'document_event')
                ->map(fn (MentorshipChatMessage $message) => $message->relatedDocument)
                ->filter()
                ->values(),
        );

        $threads = $threads
            ->map(function (MentorshipChatThread $thread) use ($activeStudentIds, $archivedStudentIds, $completedThreadIds, $defensesById, $lecturer, $ownReviews): array {
                $latestMessage = $thread->latestMessage;
                $unreadCount = MentorshipChatMessage::query()
                    ->where('mentorship_chat_messages.mentorship_chat_thread_id', $thread->id)
                    ->unreadFor($lecturer->id)
                    ->count();

                $memberProfiles = $thread->type === 'pembimbing'
                    ? array_values(array_filter([
                        $this->userProfilePresenter->summary($thread->student),
                        ...ThesisSupervisorAssignment::query()
                            ->with(['lecturer.roles', 'lecturer.dosenProfile.programStudi'])
                            ->whereHas('project', fn($query) => $query
                                ->where('student_user_id', $thread->student_user_id)
                                ->where('state', 'active'))
                            ->where('status', 'active')
                            ->get()
                            ->map(fn(ThesisSupervisorAssignment $assignment): ?array => $this->userProfilePresenter->summary($assignment->lecturer))
                            ->all(),
                    ]))
                    : $thread->participants
                        ->map(fn(MentorshipChatThreadParticipant $participant): ?array => $this->userProfilePresenter->summary($participant->user))
                        ->filter()
                        ->values()
                        ->all();

                $privatePartnerProfile = $thread->type === 'private'
                    ? collect($memberProfiles)->firstWhere('id', '!=', $lecturer->id)
                    : null;

                $members = collect($memberProfiles)
                    ->pluck('name')
                    ->filter()
                    ->values()
                    ->all();

                return [
                    'id' => $thread->id,
                    'student' => $privatePartnerProfile['name'] ?? $thread->student?->name ?? '-',
                    'studentProfile' => $privatePartnerProfile ?? $this->userProfilePresenter->summary($thread->student),
                    'members' => $members,
                    'memberProfiles' => $memberProfiles,
                    'unread' => $unreadCount,
                    'preview' => $this->payloadBuilder->displayText($latestMessage?->message) ?? 'Belum ada pesan',
                    'lastTime' => $latestMessage?->created_at?->diffForHumans() ?? '-',
                    'latestActivityAt' => $latestMessage?->created_at?->toIso8601String(),
                    'isEscalated' => $thread->is_escalated,
                    'isArchived' => $this->isArchivedThread($thread, $activeStudentIds, $archivedStudentIds, $defensesById),
                    'isCompleted' => isset($completedThreadIds[$thread->id]),
                    'threadType' => $thread->type,
                    'threadLabel' => $thread->type === 'private'
                        ? ($privatePartnerProfile['roleLabel'] ?? 'Pribadi')
                        : $this->threadLabel($thread, $defensesById->get($thread->context_id)?->project),
                    'messages' => $thread->messages
                        ->sortBy([['created_at', 'asc'], ['id', 'asc']])
                        ->values()
                        ->map(fn(MentorshipChatMessage $message): array => $this->payloadBuilder->build($message, $ownReviews))
                        ->all(),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('dosen/pesan-bimbingan', [
            'threads' => $threads,
            'privateRecipients' => $this->privateChatService->recipientOptionsFor($lecturer),
        ]);
    }

    public function storePrivateThread(Request $request): RedirectResponse
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        $data = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $recipient = User::query()->findOrFail($data['recipient_id']);
        $thread = $this->privateChatService->findOrCreateThread($lecturer, $recipient);

        return redirect()->route('dosen.pesan', ['thread' => $thread->id]);
    }

    public function markAsRead(Request $request, MentorshipChatThread $thread, ChatUnreadService $chatUnread): JsonResponse
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        // Allow marking as read for bimbingan threads (by student assignment) or penguji threads (by participant)
        abort_unless($this->canAccessThread($lecturer, $thread), 403);

        $data = $request->validate(['last_message_id' => ['required', 'integer']]);
        $readNotificationIds = $chatUnread->markRead($lecturer, $thread, (int) $data['last_message_id']);

        $activeRole = $lecturer->resolveActiveRole($request->session()->get('active_role'));

        return response()->json($chatUnread->readSnapshot($lecturer, $activeRole, $thread, $readNotificationIds))
            ->header('Cache-Control', 'no-store, private');
    }

    public function storeMessage(StoreChatMessageRequest $request, MentorshipChatThread $thread): JsonResponse
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        $canAccess = $this->canAccessThread($lecturer, $thread);
        abort_unless($canAccess, 403, 'Anda tidak memiliki akses ke thread ini.');

        if ($this->completedChatThreads->isCompleted($thread)) {
            throw ValidationException::withMessages(['message' => CompletedChatThreadService::READ_ONLY_MESSAGE]);
        }

        $data = $request->validated();

        $attachment = $data['attachment'] ?? null;
        $attachmentPath = null;
        $attachmentDisk = null;
        $attachmentName = null;
        $attachmentMime = null;
        $attachmentSizeKb = null;
        $isImage = $attachment !== null && str_starts_with((string) $attachment->getMimeType(), 'image/');

        if ($attachment !== null) {
            $attachmentDisk = 'public';
            $attachmentPath = $attachment->store(
                sprintf('chat/dosen/%d/thread/%d', $lecturer->id, $thread->id),
                $attachmentDisk,
            );
            $attachmentName = $attachment->getClientOriginalName();
            $attachmentMime = $attachment->getClientMimeType();
            $attachmentSizeKb = (int) ceil($attachment->getSize() / 1024);
        }

        $trimmedMessage = trim((string) ($data['message'] ?? ''));
        $messagesToSend = collect();

        if ($attachment !== null) {
            $messagesToSend->push($thread->messages()->create([
                'sender_user_id' => $lecturer->id,
                'message_type' => $isImage ? 'attachment' : 'revision_suggestion',
                'message' => $isImage ? 'Mengirim gambar' : 'Mengirim lampiran revisi',
                'attachment_disk' => $attachmentDisk,
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'attachment_mime' => $attachmentMime,
                'attachment_size_kb' => $attachmentSizeKb,
                'sent_at' => now(),
            ]));
        }

        if ($trimmedMessage !== '') {
            $messagesToSend->push($thread->messages()->create([
                'sender_user_id' => $lecturer->id,
                'message_type' => 'text',
                'message' => $trimmedMessage,
                'attachment_disk' => $attachmentDisk,
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'attachment_mime' => $attachmentMime,
                'attachment_size_kb' => $attachmentSizeKb,
                'sent_at' => now()->addSecond(),
            ]));
        }

        $payloads = [];
        foreach ($messagesToSend as $message) {
            $payload = [...$this->payloadBuilder->build($message), 'clientMessageId' => $data['client_message_id']];
            $payloads[] = $payload;
            $this->broadcastChatMessage($thread->id, $payload);

            $this->notifyThreadMembers($thread, $lecturer, $message);
        }

        return response()->json([
            'threadId' => $thread->id,
            'clientMessageId' => $data['client_message_id'],
            'messages' => $payloads,
        ], 201);
    }

    private function canAccessThread(User $lecturer, MentorshipChatThread $thread): bool
    {
        if ($thread->type === 'private') {
            return MentorshipChatThreadParticipant::query()
                ->where('thread_id', $thread->id)
                ->where('user_id', $lecturer->id)
                ->exists();
        }

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

    private function threadLabel(MentorshipChatThread $thread, ?ThesisProject $project): string
    {
        $terms = $project === null ? AcademicTerminology::neutral() : AcademicTerminology::forProject($project);

        return match ($thread->type) {
            'pembimbing' => 'Bimbingan',
            'sempro' => 'Penguji '.$terms['proposalExamShort'],
            'sidang' => 'Penguji '.$terms['finalExamShort'],
            'private' => 'Pribadi',
            default => $thread->label ?? 'Penguji',
        };
    }

    /**
     * @param  array<int, int>  $activeStudentIds
     * @param  array<int, int>  $archivedStudentIds
     * @param  \Illuminate\Support\Collection<int, ThesisDefense>  $defensesById
     */
    private function isArchivedThread(
        MentorshipChatThread $thread,
        array $activeStudentIds,
        array $archivedStudentIds,
        mixed $defensesById,
    ): bool {
        if ($thread->type === 'pembimbing') {
            return ! in_array($thread->student_user_id, $activeStudentIds, true)
                && in_array($thread->student_user_id, $archivedStudentIds, true);
        }

        $defense = $thread->context_id === null ? null : $defensesById->get($thread->context_id);

        if (! $defense instanceof ThesisDefense) {
            return false;
        }

        return $defense->status === 'completed'
            || $defense->project?->state !== 'active';
    }

    private function notifyThreadMembers(MentorshipChatThread $thread, User $lecturer, MentorshipChatMessage $message): void
    {
        if ($thread->type === 'private') {
            $participants = MentorshipChatThreadParticipant::query()
                ->where('thread_id', $thread->id)
                ->where('user_id', '!=', $lecturer->id)
                ->with('user')
                ->get();

            foreach ($participants as $participant) {
                if (! $participant->user instanceof User) {
                    continue;
                }

                $this->realtimeNotificationService->notifyUser($participant->user, 'pesanBaru', [
                    'title' => 'Pesan pribadi baru',
                    'description' => sprintf('%s mengirim pesan pribadi.', $lecturer->name),
                    'chatThreadId' => $thread->id,
                    'chatMessageId' => $message->id,
                    'url' => $participant->user->hasRole('mahasiswa')
                        ? sprintf('/mahasiswa/pesan?thread=%d', $thread->id)
                        : sprintf('/dosen/pesan?thread=%d', $thread->id),
                    'icon' => 'message-square',
                    'createdAt' => now()->toIso8601String(),
                ]);
            }

            return;
        }

        $student = $thread->student;

        if (! $student instanceof User) {
            return;
        }

        $preferenceKey = $message->message_type === 'revision_suggestion'
            ? 'feedbackDokumen'
            : 'pesanBaru';

        $title = $message->message_type === 'revision_suggestion'
            ? 'Feedback dokumen baru'
            : 'Pesan bimbingan baru';

        $this->realtimeNotificationService->notifyUser($student, $preferenceKey, [
            'title' => $title,
            'description' => sprintf('%s mengirim pembaruan bimbingan.', $lecturer->name),
            'url' => sprintf('/mahasiswa/pesan?thread=%d', $thread->id),
            'chatThreadId' => $thread->id,
            'chatMessageId' => $message->id,
            'icon' => $message->message_type === 'revision_suggestion' ? 'file-text' : 'message-square',
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
