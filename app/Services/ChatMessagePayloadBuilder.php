<?php

namespace App\Services;

use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipDocument;
use App\Models\User;
use App\Support\WitaDateTime;
use Illuminate\Support\Collection;

class ChatMessagePayloadBuilder
{
    /** @var array<int, array{type: string, student: ?int}> thread id => its type and mahasiswa, remembered per instance */
    private array $threads = [];

    public function __construct(
        private readonly UserProfilePresenter $userProfilePresenter,
        private readonly SecretSidangExaminers $secretExaminers,
    ) {}

    /**
     * The viewer's own copy of a document event, looked up in $ownReviews (see ownReviewsFor()).
     * Broadcasts are shared by all thread members and pass no map, so they carry null.
     *
     * @param  array<string, array{id: int, status: string, detailUrl: string}>  $ownReviews
     * @return array<string, mixed>
     */
    public function build(MentorshipChatMessage $message, array $ownReviews = [], bool $withReply = true): array
    {
        $message->loadMissing(['sender', 'relatedDocument']);

        $secretLabel = $this->secretSenderLabel($message);
        $author = $secretLabel === null ? $this->userProfilePresenter->summary($message->sender) : null;
        $hasFile = $message->attachment_path !== null;
        $isImage = $hasFile && str_starts_with((string) $message->attachment_mime, 'image/');

        return [
            'id' => $message->id,
            'editedAt' => $message->edited_at?->toIso8601String(),
            'reply' => $withReply && $message->reply_to_id !== null ? $this->replyContext($message) : null,
            'quoteAvailable' => $secretLabel === null,
            'senderUserId' => $secretLabel === null ? $message->sender_user_id : null,
            'author' => $secretLabel ?? $message->sender?->name ?? 'Sistem',
            'authorAvatar' => $author['avatar'] ?? null,
            'authorProfileUrl' => $author['profileUrl'] ?? null,
            'message' => $this->displayText($secretLabel === null
                ? $message->message
                : str_replace((string) $message->sender?->name, $secretLabel, (string) $message->message)),
            'sentAt' => $message->created_at->toIso8601String(),
            'time' => WitaDateTime::format($message->created_at, 'H.i', withLabel: false),
            'type' => $message->message_type,
            'documentName' => $message->attachment_name ?? $message->relatedDocument?->file_name,
            'documentUrl' => $hasFile
                ? route('files.chat-attachments.download', ['message' => $message->id])
                : ($message->related_document_id === null
                    ? null
                    : route('files.documents.download', ['document' => $message->related_document_id])),
            'attachmentKind' => $hasFile ? ($isImage ? 'image' : 'document') : null,
            'attachmentSizeKb' => $hasFile ? $message->attachment_size_kb : null,
            'previewUrl' => $isImage
                ? route('files.chat-attachments.preview', ['message' => $message->id])
                : null,
            'relatedDocumentId' => $message->related_document_id,
            'reviewStatus' => $message->relatedDocument === null ? null : DocumentRevisionService::displayStatus($message->relatedDocument),
            'documentEventKind' => $this->documentEventKind($message),
            'ownReview' => $this->ownReview($message, $ownReviews),
        ];
    }

    /** Live context uses the ordinary presentation path, never raw source attributes. */
    public function replyContext(MentorshipChatMessage $message): array
    {
        $source = $message->replyTo;
        if ($source === null
            || (int) $source->mentorship_chat_thread_id !== (int) $message->mentorship_chat_thread_id
            || $this->secretSenderLabel($source) !== null) {
            return ['available' => false];
        }

        $payload = $this->build($source, withReply: false);

        return [
            'available' => true,
            'id' => $payload['id'],
            'author' => $payload['author'],
            'message' => $payload['message'],
            'documentName' => $payload['documentName'],
        ];
    }

    /**
     * Maps system thread notices to the wording shown in chat.
     */
    public function displayText(?string $message): ?string
    {
        return match ($message) {
            'Thread Seminar Proposal telah dibuat. Silahkan berdiskusi mengenai sempro di sini.',
            'Thread Seminar Proposal telah dibuat. Gunakan thread ini untuk koordinasi sempro.' => 'Ruang Seminar Proposal siap digunakan.',
            'Thread Sidang telah dibuat. Silahkan berdiskusi mengenai sidang di sini.',
            'Thread Sidang telah dibuat. Gunakan thread ini untuk koordinasi sidang.' => 'Ruang Sidang siap digunakan.',
            default => $message,
        };
    }

    /**
     * Loads the viewer's copies of every document referenced by the given events in one query.
     * Keyed by {@see copyKey()} so each message resolves its own copy without another query.
     *
     * @param  Collection<int, MentorshipDocument>  $documents
     * @return array<string, array{id: int, status: string, detailUrl: string}>
     */
    public function ownReviewsFor(User $viewer, Collection $documents): array
    {
        if ($documents->isEmpty()) {
            return [];
        }

        return MentorshipDocument::query()
            ->where('lecturer_user_id', $viewer->id)
            ->whereIn('student_user_id', $documents->pluck('student_user_id')->unique()->values()->all())
            ->where(fn ($query) => $query
                ->whereIn('document_group', $documents->pluck('document_group')->filter()->unique()->values()->all())
                ->orWhereIn('id', $documents->whereNull('document_group')->pluck('id')->all()))
            ->get()
            ->mapWithKeys(fn (MentorshipDocument $copy): array => [
                self::copyKey($copy) => ['id' => $copy->id, 'status' => DocumentRevisionService::displayStatus($copy), 'detailUrl' => DocumentDetailService::workspaceUrl($copy, 'dosen')],
            ])
            ->all();
    }

    /**
     * 'upload' for the event announcing a student's file, 'review' for lecturer review/status events.
     */
    private function documentEventKind(MentorshipChatMessage $message): ?string
    {
        if ($message->message_type !== 'document_event') {
            return null;
        }

        $student = $message->relatedDocument?->student_user_id;

        return $student === null || (int) $message->sender_user_id === (int) $student ? 'upload' : 'review';
    }

    /**
     * @param  array<string, array{id: int, status: string, detailUrl: string}>  $ownReviews
     * @return array{id: int, status: string, detailUrl: string}|null
     */
    private function ownReview(MentorshipChatMessage $message, array $ownReviews): ?array
    {
        $related = $message->relatedDocument;

        // Only the event announcing the student's own upload is reviewable. Review/status
        // events and files sent by lecturers are authored by someone other than the student.
        if (
            $message->message_type !== 'document_event'
            || $related === null
            || (int) $message->sender_user_id !== (int) $related->student_user_id
        ) {
            return null;
        }

        return $ownReviews[self::copyKey($related)] ?? null;
    }

    /**
     * Copies of one upload share student, document group and version; the lecturer id tells them apart.
     */
    private static function copyKey(MentorshipDocument $document): string
    {
        return $document->student_user_id.'|'.($document->document_group ?? 'legacy-'.$document->id).'|'.$document->version_number;
    }

    /**
     * The label that replaces the sender when the message comes from a sidang examiner the
     * mahasiswa of the thread may not know yet (also covers messages sent before that rule).
     * Private chats are left alone: the lecturer started that conversation themselves.
     */
    private function secretSenderLabel(MentorshipChatMessage $message): ?string
    {
        if ($message->sender_user_id === null) {
            return null;
        }

        $threadId = (int) $message->mentorship_chat_thread_id;
        $this->threads[$threadId] ??= MentorshipChatThread::query()
            ->whereKey($threadId)
            ->get(['type', 'student_user_id'])
            ->map(fn (MentorshipChatThread $thread): array => ['type' => (string) $thread->type, 'student' => $thread->student_user_id])
            ->first() ?? ['type' => 'private', 'student' => null];

        ['type' => $type, 'student' => $student] = $this->threads[$threadId];

        if ($type === 'private' || $student === null || (int) $student === (int) $message->sender_user_id) {
            return null;
        }

        return $this->secretExaminers->labelFor((int) $student, (int) $message->sender_user_id);
    }
}
