<?php

namespace App\Services;

use App\Models\MentorshipChatMessage;

class ChatMessagePayloadBuilder
{
    public function __construct(
        private readonly UserProfilePresenter $userProfilePresenter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(MentorshipChatMessage $message): array
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
}
