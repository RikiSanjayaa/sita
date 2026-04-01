import { Link } from '@inertiajs/react';
import { Download, FileText } from 'lucide-react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type ChatMessagePayload = {
    id: number | string;
    author: string;
    authorAvatar?: string | null;
    authorProfileUrl?: string | null;
    message: string;
    time: string;
    type: string;
    documentName: string | null;
    documentUrl: string | null;
};

interface ChatBubbleProps {
    message: ChatMessagePayload;
    isMe: boolean;
}

function initials(name: string) {
    return name
        .split(' ')
        .map((chunk) => chunk[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

export function ChatBubble({ message, isMe }: ChatBubbleProps) {
    if (
        message.type === 'document_event' ||
        message.type === 'revision_suggestion'
    ) {
        const isRevision = message.type === 'revision_suggestion';

        return (
            <div className="animate-pop my-4 flex flex-col items-center gap-1.5">
                <div className="flex w-full max-w-[85%] items-center justify-between gap-4 rounded-xl border bg-background px-4 py-3 shadow-sm sm:w-auto sm:min-w-[400px]">
                    <div className="flex min-w-0 items-center gap-4">
                        <div className="flex size-10 shrink-0 items-center justify-center rounded-sm bg-primary/10 text-primary">
                            <FileText className="size-5" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                {isRevision
                                    ? 'File Revisi dari Dosen'
                                    : 'Dokumen Baru Diunggah'}
                            </p>
                            <p className="truncate text-sm font-semibold text-foreground">
                                {message.documentName}
                            </p>
                        </div>
                    </div>

                    <Button
                        size="sm"
                        variant="secondary"
                        className="h-8 shrink-0 gap-1.5 rounded-full px-4 text-primary hover:text-primary"
                        disabled={!message.documentUrl}
                        asChild={!!message.documentUrl}
                    >
                        {message.documentUrl ? (
                            <a
                                href={message.documentUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <Download className="size-3.5" />
                                <span className="text-xs font-semibold">
                                    Unduh
                                </span>
                            </a>
                        ) : (
                            <div>
                                <Download className="size-3.5" />
                                <span className="text-xs font-semibold">
                                    Unduh
                                </span>
                            </div>
                        )}
                    </Button>
                </div>
                <span className="text-[11px] font-medium text-muted-foreground">
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
                    'flex max-w-[85%] gap-3 sm:max-w-[75%]',
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
                                        {initials(message.author)}
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
                                    {initials(message.author)}
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
                        )}
                    >
                        {message.documentName && (
                            <div
                                className={cn(
                                    'mb-2 overflow-hidden rounded border text-xs break-all',
                                    isMe
                                        ? 'border-primary-foreground/25 bg-primary-foreground/15 text-primary-foreground'
                                        : 'border-border bg-muted/30',
                                )}
                            >
                                {message.documentUrl ? (
                                    <a
                                        href={message.documentUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="flex items-center gap-2 p-2 transition-colors hover:bg-black/5 dark:hover:bg-white/5"
                                    >
                                        <FileText className="size-3.5 shrink-0" />
                                        <span className="font-semibold underline decoration-primary-foreground/30 underline-offset-2 hover:decoration-primary-foreground">
                                            {message.documentName}
                                        </span>
                                    </a>
                                ) : (
                                    <div className="flex items-center gap-2 p-2">
                                        <FileText className="size-3.5 shrink-0" />
                                        <span>{message.documentName}</span>
                                    </div>
                                )}
                            </div>
                        )}
                        {message.message && (
                            <div className="break-words whitespace-pre-wrap">
                                {message.message}
                            </div>
                        )}
                    </div>

                    <div
                        className={cn(
                            'mt-0.5 text-[11px] text-muted-foreground',
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
