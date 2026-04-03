import { Link } from '@inertiajs/react';
import { Download, FileText } from 'lucide-react';
import { Fragment } from 'react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
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
    searchTerm?: string;
    isActiveMatch?: boolean;
}

function initials(name: string) {
    return name
        .split(' ')
        .map((chunk) => chunk[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function truncateFilename(name: string | null, maxLength = 28) {
    if (!name) {
        return '';
    }

    if (name.length <= maxLength) {
        return name;
    }

    return `${name.slice(0, maxLength - 3)}...`;
}

function escapeRegExp(value: string) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function highlightText(text: string, query?: string, inverse = false) {
    const normalizedQuery = query?.trim() ?? '';

    if (!normalizedQuery) {
        return text;
    }

    const parts = text.split(
        new RegExp(`(${escapeRegExp(normalizedQuery)})`, 'gi'),
    );

    return parts.map((part, index) => {
        if (part.toLowerCase() !== normalizedQuery.toLowerCase()) {
            return <Fragment key={`${part}-${index}`}>{part}</Fragment>;
        }

        return (
            <mark
                key={`${part}-${index}`}
                className={cn(
                    'rounded px-0.5',
                    inverse
                        ? 'bg-primary-foreground/25 text-primary-foreground'
                        : 'bg-yellow-200/80 text-foreground dark:bg-yellow-500/30 dark:text-foreground',
                )}
            >
                {part}
            </mark>
        );
    });
}

function FilenameLabel({
    name,
    className,
    searchTerm,
    inverse,
}: {
    name: string | null;
    className?: string;
    searchTerm?: string;
    inverse?: boolean;
}) {
    if (!name) {
        return null;
    }

    const shortName = truncateFilename(name);

    if (shortName === name) {
        return (
            <span className={className}>
                {highlightText(name, searchTerm, inverse)}
            </span>
        );
    }

    return (
        <TooltipProvider delayDuration={0}>
            <Tooltip>
                <TooltipTrigger asChild>
                    <span className={className}>
                        {highlightText(shortName, searchTerm, inverse)}
                    </span>
                </TooltipTrigger>
                <TooltipContent>
                    <p className="max-w-xs break-all">{name}</p>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}

export function ChatBubble({
    message,
    isMe,
    searchTerm = '',
    isActiveMatch = false,
}: ChatBubbleProps) {
    if (
        message.type === 'document_event' ||
        message.type === 'revision_suggestion'
    ) {
        const isRevision = message.type === 'revision_suggestion';

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
                        <div className="flex size-10 shrink-0 items-center justify-center rounded-sm bg-primary/10 text-primary">
                            <FileText className="size-5" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                {isRevision
                                    ? 'File Revisi dari Dosen'
                                    : 'Dokumen Baru Diunggah'}
                            </p>
                            <FilenameLabel
                                name={message.documentName}
                                className="block truncate text-sm font-semibold text-foreground"
                                searchTerm={searchTerm}
                            />
                        </div>
                    </div>

                    <Button
                        size="sm"
                        variant="secondary"
                        className="h-8 w-full shrink-0 gap-1.5 rounded-full px-3 text-primary hover:text-primary min-[520px]:w-auto sm:px-4"
                        disabled={!message.documentUrl}
                        asChild={!!message.documentUrl}
                    >
                        {message.documentUrl ? (
                            <a
                                href={message.documentUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="flex w-full items-center justify-center"
                            >
                                <Download className="size-3.5" />
                                <span className="text-xs font-semibold">
                                    Unduh
                                </span>
                            </a>
                        ) : (
                            <div className="flex w-full items-center justify-center">
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
                            isActiveMatch &&
                                (isMe
                                    ? 'ring-2 ring-primary-foreground/35'
                                    : 'border-primary ring-2 ring-primary/20'),
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
                                        className="flex min-w-0 items-center gap-2 p-2 transition-colors hover:bg-black/5 dark:hover:bg-white/5"
                                    >
                                        <FileText className="size-3.5 shrink-0" />
                                        <FilenameLabel
                                            name={message.documentName}
                                            className="block min-w-0 truncate font-semibold underline decoration-primary-foreground/30 underline-offset-2 hover:decoration-primary-foreground"
                                            searchTerm={searchTerm}
                                            inverse={isMe}
                                        />
                                    </a>
                                ) : (
                                    <div className="flex min-w-0 items-center gap-2 p-2">
                                        <FileText className="size-3.5 shrink-0" />
                                        <FilenameLabel
                                            name={message.documentName}
                                            className="block min-w-0 truncate"
                                            searchTerm={searchTerm}
                                            inverse={isMe}
                                        />
                                    </div>
                                )}
                            </div>
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
