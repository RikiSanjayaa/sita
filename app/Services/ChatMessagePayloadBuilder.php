<?php

namespace App\Services;

use App\Models\MentorshipChatMessage;
use App\Models\MentorshipDocument;
use App\Models\User;
use Illuminate\Support\Collection;

class ChatMessagePayloadBuilder
{
    public function __construct(
        private readonly UserProfilePresenter $userProfilePresenter,
    ) {}

    /**
     * The viewer's own copy of a document event, looked up in $ownReviews (see ownReviewsFor()).
     * Broadcasts are shared by all thread members and pass no map, so they carry null.
     *
     * @param  array<string, array{id: int, status: string}>  $ownReviews
     * @return array<string, mixed>
     */
    public function build(MentorshipChatMessage $message, array $ownReviews = []): array
    {
        $message->loadMissing(['sender', 'relatedDocument']);

        $author = $this->userProfilePresenter->summary($message->sender);
        $hasFile = $message->attachment_path !== null;
        $isImage = $hasFile && str_starts_with((string) $message->attachment_mime, 'image/');

        return [
            'id' => $message->id,
            'senderUserId' => $message->sender_user_id,
            'author' => $message->sender?->name ?? 'Sistem',
            'authorAvatar' => $author['avatar'] ?? null,
            'authorProfileUrl' => $author['profileUrl'] ?? null,
            'message' => $this->displayText($message->message),
            'time' => $message->created_at->format('d M Y H:i'),
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
            'reviewStatus' => $message->relatedDocument?->status,
            'ownReview' => $this->ownReview($message, $ownReviews),
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
     * @return array<string, array{id: int, status: string}>
     */
    public function ownReviewsFor(User $viewer, Collection $documents): array
    {
        if ($documents->isEmpty()) {
            return [];
        }

        return MentorshipDocument::query()
            ->where('lecturer_user_id', $viewer->id)
            ->whereIn('student_user_id', $documents->pluck('student_user_id')->unique()->values()->all())
            ->whereIn('document_group', $documents->pluck('document_group')->unique()->values()->all())
            ->get()
            ->mapWithKeys(fn (MentorshipDocument $copy): array => [
                self::copyKey($copy) => ['id' => $copy->id, 'status' => $copy->status],
            ])
            ->all();
    }

    /**
     * @param  array<string, array{id: int, status: string}>  $ownReviews
     * @return array{id: int, status: string}|null
     */
    private function ownReview(MentorshipChatMessage $message, array $ownReviews): ?array
    {
        $related = $message->relatedDocument;

        if ($message->message_type !== 'document_event' || $related === null) {
            return null;
        }

        return $ownReviews[self::copyKey($related)] ?? null;
    }

    /**
     * Copies of one upload share student, document group and version; the lecturer id tells them apart.
     */
    private static function copyKey(MentorshipDocument $document): string
    {
        return $document->student_user_id.'|'.$document->document_group.'|'.$document->version_number;
    }
}
