import { Link } from '@inertiajs/react';
import { FileText, MoreHorizontal, Pencil, Reply } from 'lucide-react';
import { useState } from 'react';

import {
    AttachmentCard,
    attachmentIconActionClassName,
} from '@/components/chat/attachment-card';
import { DocumentReviewAction } from '@/components/chat/document-review-action';
import { FilenameLabel, highlightText } from '@/components/chat/highlight-text';
import { ImageLightbox } from '@/components/chat/image-lightbox';
import { type ChatMessage } from '@/components/chat/types';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { getInitials } from '@/lib/initials';
import { cn } from '@/lib/utils';

interface ChatBubbleProps {
    message: ChatMessage;
    isMe: boolean;
    searchTerm?: string;
    isActiveMatch?: boolean;
    /** Same sender and displayed minute as the preceding ordinary message. */
    grouped?: boolean;
    showTime?: boolean;
    /** Latest review status of the bubble's document; falls back to the row's own. */
    reviewStatus?: string | null;
    onReply?: () => void;
    onEdit?: () => void;
    onJump?: (id: number) => void;
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
    grouped = false,
    showTime = true,
    onReply,
    onEdit,
    onJump,
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
            <div className="my-4 flex animate-in flex-col items-center gap-1.5 fade-in">
                <div
                    className={cn(
                        'flex w-full max-w-[min(100%,34rem)] flex-col gap-3 rounded-xl bg-card px-3 py-3 min-[520px]:flex-row min-[520px]:items-center min-[520px]:justify-between sm:px-4',
                        isActiveMatch && 'ring-2 ring-primary/40',
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
                'flex w-full animate-in fade-in motion-reduce:animate-none',
                isMe ? 'justify-end' : 'justify-start',
                grouped ? 'mt-0.5' : 'mt-3',
            )}
        >
            <div
                className={cn(
                    'flex gap-2 sm:max-w-[85%]',
                    // Outgoing bubbles reserve the same 44px avatar + 8px gap.
                    isMe
                        ? 'max-w-[calc(100%-3.25rem)] flex-row-reverse'
                        : 'max-w-full flex-row',
                )}
            >
                {!isMe && (
                    <div className="mt-1 w-11 shrink-0">
                        {!grouped &&
                            (message.authorProfileUrl ? (
                                <Link href={message.authorProfileUrl}>
                                    <Avatar className="size-11">
                                        <AvatarImage
                                            src={
                                                message.authorAvatar ??
                                                undefined
                                            }
                                            alt={message.author}
                                        />
                                        <AvatarFallback className="bg-primary-muted text-sm font-medium text-primary-muted-foreground">
                                            {getInitials(message.author)}
                                        </AvatarFallback>
                                    </Avatar>
                                </Link>
                            ) : (
                                <Avatar className="size-11">
                                    <AvatarImage
                                        src={message.authorAvatar ?? undefined}
                                        alt={message.author}
                                    />
                                    <AvatarFallback className="bg-primary-muted text-sm font-medium text-primary-muted-foreground">
                                        {getInitials(message.author)}
                                    </AvatarFallback>
                                </Avatar>
                            ))}
                    </div>
                )}

                <div className="flex min-w-0 flex-col gap-1">
                    {!isMe && !grouped && (
                        <div className="pl-1 text-xs text-muted-foreground">
                            {message.authorProfileUrl ? (
                                <Link
                                    href={message.authorProfileUrl}
                                    className="font-medium hover:underline"
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
                            'relative rounded-2xl px-3 py-2 text-sm leading-snug',
                            isMe
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-card text-card-foreground',
                            !grouped &&
                                (isMe ? 'rounded-tr-none' : 'rounded-tl-none'),
                            isActiveMatch &&
                                (isMe
                                    ? 'ring-2 ring-primary-foreground/35'
                                    : 'ring-2 ring-primary/40'),
                        )}
                    >
                        {!grouped && (
                            <svg
                                aria-hidden="true"
                                viewBox="0 0 12 12"
                                className={cn(
                                    'pointer-events-none absolute top-0 size-3',
                                    isMe
                                        ? '-right-2 fill-primary'
                                        : '-left-2 fill-card',
                                )}
                            >
                                <path
                                    d={
                                        isMe
                                            ? 'M0 0H12L0 12Z'
                                            : 'M12 0H0L12 12Z'
                                    }
                                />
                            </svg>
                        )}
                        {message.reply &&
                            (message.reply.available ? (
                                <button
                                    type="button"
                                    aria-label="Lihat pesan asal"
                                    onClick={() => {
                                        if (message.reply?.available)
                                            onJump?.(message.reply.id);
                                    }}
                                    className="mb-2 block min-h-11 w-full rounded-lg border border-current/30 px-2 py-1 text-left text-xs focus-visible:outline-2 focus-visible:outline-offset-2"
                                >
                                    <span className="block font-semibold">
                                        {message.reply.author}
                                    </span>
                                    {message.reply.documentName && (
                                        <span className="block font-medium break-words">
                                            {message.reply.documentName}
                                        </span>
                                    )}
                                    <span className="line-clamp-2 break-words">
                                        {message.reply.message}
                                    </span>
                                </button>
                            ) : (
                                <p className="mb-2 text-xs">
                                    Pesan asal tidak tersedia
                                </p>
                            ))}
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
                                            className={attachmentIconActionClassName(
                                                isMe,
                                            )}
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
                                        className={attachmentIconActionClassName(
                                            isMe,
                                        )}
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
                        {(showTime || onReply || onEdit) && (
                            <div
                                className={cn(
                                    'mt-1 flex items-center justify-end gap-2 text-xs tabular-nums',
                                    isMe
                                        ? 'text-primary-foreground'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {message.editedAt && <span>Diedit</span>}
                                {showTime && <span>{message.time}</span>}
                                {(onReply || onEdit) && !isPending && (
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                aria-label="Aksi pesan"
                                                className={cn(
                                                    'size-8 shrink-0 max-md:size-11',
                                                    isMe &&
                                                        'hover:bg-primary-foreground/10 hover:text-primary-foreground',
                                                )}
                                            >
                                                <MoreHorizontal className="size-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent
                                            align={isMe ? 'end' : 'start'}
                                        >
                                            {onReply && (
                                                <DropdownMenuItem
                                                    onSelect={onReply}
                                                >
                                                    <Reply className="size-4" />
                                                    Balas
                                                </DropdownMenuItem>
                                            )}
                                            {onEdit && (
                                                <DropdownMenuItem
                                                    onSelect={onEdit}
                                                >
                                                    <Pencil className="size-4" />
                                                    Edit teks / caption
                                                </DropdownMenuItem>
                                            )}
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
