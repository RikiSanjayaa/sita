import { Link } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { useState } from 'react';

import { AttachmentCard } from '@/components/chat/attachment-card';
import { DocumentReviewAction } from '@/components/chat/document-review-action';
import { FilenameLabel, highlightText } from '@/components/chat/highlight-text';
import { ImageLightbox } from '@/components/chat/image-lightbox';
import { type ChatMessage } from '@/components/chat/types';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/lib/initials';
import { cn } from '@/lib/utils';

interface ChatBubbleProps {
    message: ChatMessage;
    isMe: boolean;
    searchTerm?: string;
    isActiveMatch?: boolean;
    /** Latest review status of the bubble's document; falls back to the row's own. */
    reviewStatus?: string | null;
}

function documentCaption(message: ChatMessage): string | undefined {
    if (message.type === 'revision_suggestion') {
        return 'File Revisi dari Dosen';
    }

    if (message.type === 'document_event') {
        return message.documentEventKind === 'review'
            ? 'Hasil Review Dokumen'
            : 'Dokumen Baru Diunggah';
    }

    return undefined;
}

export function ChatBubble({
    message,
    isMe,
    reviewStatus = null,
    searchTerm = '',
    isActiveMatch = false,
}: ChatBubbleProps) {
    const [isLightboxOpen, setIsLightboxOpen] = useState(false);
    const isPending = message.type === 'pending';
    const thumbnailUrl =
        message.attachmentKind === 'image' ? message.previewUrl : null;
    if (
        (message.type === 'document_event' ||
            message.type === 'revision_suggestion') &&
        message.documentUrl === null
    ) {
        return (
            <div className="animate-pop my-4 flex flex-col items-center gap-1.5">
                <div
                    className={cn(
                        'flex w-full max-w-[min(100%,34rem)] flex-col gap-3 rounded-xl border bg-background px-3 py-3 shadow-sm min-[520px]:flex-row min-[520px]:items-center min-[520px]:justify-between sm:px-4',
                        isActiveMatch &&
                            'border-primary ring-2 ring-primary/20',
                    )}
                >
                    <div className="flex min-w-0 items-center gap-3 sm:gap-4">
                        <div className="flex size-10 shrink-0 items-center justify-center rounded-sm bg-primary-muted text-primary-muted-foreground">
                            <FileText className="size-5" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-xs font-medium text-muted-foreground">
                                {documentCaption(message)}
                            </p>
                            <FilenameLabel
                                name={message.documentName}
                                className="block truncate text-sm font-semibold text-foreground"
                                searchTerm={searchTerm}
                            />
                        </div>
                    </div>
                </div>
                <span className="text-xs text-muted-foreground">
                    {message.time}
                </span>
            </div>
        );
    }

    return (
        <div
            className={cn(
                'animate-pop my-2 flex w-full',
                isMe ? 'justify-end' : 'justify-start',
            )}
        >
            <div
                className={cn(
                    'flex max-w-[calc(100%-2.75rem)] gap-3 sm:max-w-[75%]',
                    isMe ? 'flex-row-reverse' : 'flex-row',
                )}
            >
                {!isMe && (
                    <div className="mt-1 shrink-0">
                        {message.authorProfileUrl ? (
                            <Link href={message.authorProfileUrl}>
                                <Avatar className="size-8">
                                    <AvatarImage
                                        src={message.authorAvatar ?? undefined}
                                        alt={message.author}
                                    />
                                    <AvatarFallback>
                                        {getInitials(message.author)}
                                    </AvatarFallback>
                                </Avatar>
                            </Link>
                        ) : (
                            <Avatar className="size-8">
                                <AvatarImage
                                    src={message.authorAvatar ?? undefined}
                                    alt={message.author}
                                />
                                <AvatarFallback>
                                    {getInitials(message.author)}
                                </AvatarFallback>
                            </Avatar>
                        )}
                    </div>
                )}

                <div className="flex min-w-0 flex-col gap-1">
                    {!isMe && (
                        <div className="pl-1 text-xs text-muted-foreground">
                            {message.authorProfileUrl ? (
                                <Link
                                    href={message.authorProfileUrl}
                                    className="font-medium hover:text-primary"
                                >
                                    {message.author}
                                </Link>
                            ) : (
                                <span className="font-medium">
                                    {message.author}
                                </span>
                            )}
                        </div>
                    )}

                    <div
                        className={cn(
                            'rounded-2xl px-4 py-3 text-[15px] leading-relaxed',
                            isMe
                                ? 'rounded-br-sm bg-primary text-primary-foreground'
                                : 'rounded-tl-sm border bg-background text-foreground shadow-sm',
                            isActiveMatch &&
                                (isMe
                                    ? 'ring-2 ring-primary-foreground/35'
                                    : 'border-primary ring-2 ring-primary/20'),
                        )}
                    >
                        {thumbnailUrl !== null && (
                            <button
                                type="button"
                                onClick={() => setIsLightboxOpen(true)}
                                disabled={isPending}
                                aria-label={`Perbesar ${message.documentName ?? 'gambar'}`}
                                className="mb-2 block overflow-hidden rounded-xl"
                            >
                                <img
                                    src={thumbnailUrl}
                                    alt={message.documentName ?? 'Gambar'}
                                    loading="lazy"
                                    className="block max-h-64 max-w-full object-cover"
                                />
                            </button>
                        )}
                        {message.documentName && thumbnailUrl === null && (
                            <AttachmentCard
                                caption={documentCaption(message)}
                                href={message.documentUrl}
                                inverse={isMe}
                                name={message.documentName}
                                reviewStatus={
                                    message.ownReview?.status ??
                                    reviewStatus ??
                                    message.reviewStatus
                                }
                                searchTerm={searchTerm}
                                sizeKb={message.attachmentSizeKb}
                                action={
                                    message.ownReview?.status ===
                                    'submitted' ? (
                                        <DocumentReviewAction
                                            document={message.ownReview}
                                            documentName={message.documentName}
                                        />
                                    ) : undefined
                                }
                            />
                        )}
                        {thumbnailUrl !== null &&
                            message.documentName &&
                            message.ownReview?.status === 'submitted' && (
                                <div className="mb-2 flex justify-end">
                                    <DocumentReviewAction
                                        document={message.ownReview}
                                        documentName={message.documentName}
                                    />
                                </div>
                            )}
                        {thumbnailUrl !== null && !isPending && (
                            <ImageLightbox
                                downloadHref={message.documentUrl}
                                name={message.documentName ?? 'Gambar'}
                                onOpenChange={setIsLightboxOpen}
                                open={isLightboxOpen}
                                src={thumbnailUrl}
                            />
                        )}
                        {message.message && (
                            <div className="break-words whitespace-pre-wrap">
                                {highlightText(
                                    message.message,
                                    searchTerm,
                                    isMe,
                                )}
                            </div>
                        )}
                    </div>

                    <div
                        className={cn(
                            'mt-0.5 text-xs text-muted-foreground',
                            isMe ? 'pr-1 text-right' : 'pl-1 text-left',
                        )}
                    >
                        {message.time}
                    </div>
                </div>
            </div>
        </div>
    );
}
