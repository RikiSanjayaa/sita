import {
    ChevronDown,
    ChevronRight,
    Inbox,
    Search,
    SlidersHorizontal,
    UserPlus,
    UserRound,
    Users,
} from 'lucide-react';
import { type ReactNode, useState } from 'react';

import {
    PrivateRecipientCombobox,
    type PrivateRecipientOption,
} from '@/components/private-recipient-combobox';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { neutralAcademicTerminology } from '@/lib/academic-terminology';
import { cn } from '@/lib/utils';

import { ChatEmptyState } from './empty-state';
import { chatStyles } from './styles';
import {
    type ChatMessage,
    type ChatThread,
    type ChatTone,
    type MobileView,
} from './types';
import { type PrivateThreadState } from './use-private-thread';

type ThreadListProps<T extends ChatThread> = {
    activeThreadId: number | null;
    badgeClassName: (thread: T) => string;
    emptyState: { title: string; description: string };
    groupExtra?: ReactNode;
    groupFilterToggle?: () => void;
    hasUnread?: (thread: T) => boolean;
    isMobile: boolean;
    messagesFor: (thread: T) => ChatMessage[];
    mobileView: MobileView;
    onSearchChange: (value: string) => void;
    onSelect: (threadId: number) => void;
    privateRecipients: PrivateRecipientOption[];
    privateThread: PrivateThreadState;
    search: string;
    threadTitle: (thread: T) => string;
    threads: T[];
    title: string;
    tone: ChatTone;
};

export function ThreadList<T extends ChatThread>({
    activeThreadId,
    badgeClassName,
    emptyState,
    groupExtra,
    groupFilterToggle,
    hasUnread,
    isMobile,
    messagesFor,
    mobileView,
    onSearchChange,
    onSelect,
    privateRecipients,
    privateThread,
    search,
    threadTitle,
    threads,
    title,
    tone,
}: ThreadListProps<T>) {
    const completedThreads = threads.filter((thread) => thread.isCompleted);
    const activeThreads = threads.filter((thread) => !thread.isCompleted);
    const [completedOpen, setCompletedOpen] = useState(false);
    const showCompleted =
        completedOpen ||
        search.trim() !== '' ||
        completedThreads.some((thread) => thread.id === activeThreadId);

    function renderRow(thread: T) {
        const latestMessage = messagesFor(thread).at(-1);
        const isPrivate = thread.threadType === 'private';
        const IdentityIcon = isPrivate ? UserRound : Users;
        return (
            <button
                key={thread.id}
                type="button"
                data-thread-id={thread.id}
                aria-current={activeThreadId === thread.id ? 'true' : undefined}
                className={cn(
                    'flex w-full shrink-0 items-start gap-3 border-b px-4 py-4 text-left transition-colors last:border-b-0 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset',
                    activeThreadId === thread.id
                        ? 'bg-primary-muted'
                        : 'hover:bg-muted/50',
                )}
                onClick={() => onSelect(thread.id)}
            >
                <span
                    className={cn(
                        'flex size-10 shrink-0 items-center justify-center rounded-full',
                        isPrivate
                            ? 'bg-muted text-muted-foreground'
                            : 'bg-primary-muted text-primary-muted-foreground',
                    )}
                >
                    <IdentityIcon aria-hidden className="size-5" />
                </span>
                <span className="flex min-w-0 flex-1 flex-col gap-1.5">
                    <span className="flex items-start justify-between gap-2">
                        <span className="min-w-0 truncate text-sm font-semibold text-foreground">
                            {threadTitle(thread)}
                        </span>
                        <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                            {thread.lastTime}
                        </span>
                    </span>
                    <span className="flex items-center justify-between gap-2">
                        <Badge
                            variant="soft"
                            className={cn(
                                'px-2 py-0.5 text-xs font-medium',
                                badgeClassName(thread),
                            )}
                        >
                            {isPrivate
                                ? 'Pribadi'
                                : `Grup ${thread.threadLabel}`}
                        </Badge>
                        {hasUnread?.(thread) && (
                            <span className="size-2 shrink-0 rounded-full bg-primary">
                                <span className="sr-only">
                                    Pesan belum dibaca
                                </span>
                            </span>
                        )}
                    </span>
                    <span className="line-clamp-2 text-sm text-pretty text-muted-foreground">
                        {latestMessage?.message ||
                            latestMessage?.documentName ||
                            thread.preview}
                    </span>
                </span>
            </button>
        );
    }

    return (
        <aside
            aria-label="Daftar percakapan"
            className={cn(
                chatStyles.listCard(tone, isMobile),
                mobileView === 'chat' && 'hidden lg:flex',
                mobileView === 'threads' && 'flex-1 lg:flex-initial',
            )}
        >
            <header className={chatStyles.listHeader(tone, isMobile)}>
                <div className="flex items-center justify-between gap-3">
                    <h2 className="text-lg font-semibold">{title}</h2>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Chat baru"
                        aria-expanded={privateThread.isOpen}
                        className="size-9 shrink-0 max-md:size-11"
                        onClick={privateThread.toggle}
                    >
                        <UserPlus aria-hidden className="size-4" />
                    </Button>
                </div>
                <div className="flex items-center gap-2">
                    <div className="relative min-w-0 flex-1">
                        <Search
                            aria-hidden
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            value={search}
                            onChange={(event) =>
                                onSearchChange(event.target.value)
                            }
                            className="pl-9 max-md:h-11"
                            placeholder="Cari percakapan..."
                            aria-label="Cari percakapan"
                        />
                    </div>
                    {groupFilterToggle && (
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            aria-label="Filter percakapan"
                            className="size-9 shrink-0 max-md:size-11"
                            onClick={groupFilterToggle}
                        >
                            <SlidersHorizontal aria-hidden className="size-4" />
                        </Button>
                    )}
                </div>
                {groupExtra}
                {privateThread.isOpen && (
                    <PrivateRecipientCombobox
                        recipients={privateRecipients}
                        value={privateThread.recipientId}
                        onValueChange={privateThread.setRecipientId}
                        onSubmit={privateThread.start}
                        disabled={privateThread.processing}
                    />
                )}
            </header>
            <div className="relative min-h-0 flex-1 overflow-hidden">
                <ScrollArea className="h-full w-full">
                    <div className="flex flex-col">
                        {activeThreads.map(renderRow)}
                        {completedThreads.length > 0 && (
                            <div
                                className="flex flex-col"
                                data-testid="completed-threads"
                            >
                                <button
                                    type="button"
                                    aria-expanded={showCompleted}
                                    className="flex min-h-11 w-full shrink-0 items-center gap-2 border-b bg-muted/40 px-4 py-3 text-left text-sm font-semibold text-muted-foreground transition-colors hover:bg-muted/60"
                                    onClick={() =>
                                        setCompletedOpen((open) => !open)
                                    }
                                >
                                    {showCompleted ? (
                                        <ChevronDown
                                            aria-hidden
                                            className="size-4"
                                        />
                                    ) : (
                                        <ChevronRight
                                            aria-hidden
                                            className="size-4"
                                        />
                                    )}
                                    {neutralAcademicTerminology.completedPhase}{' '}
                                    ({completedThreads.length})
                                </button>
                                {showCompleted &&
                                    completedThreads.map(renderRow)}
                            </div>
                        )}
                        {threads.length === 0 && (
                            <ChatEmptyState
                                className="p-6"
                                icon={Inbox}
                                tone={tone}
                                title={
                                    search.trim()
                                        ? 'Tidak ada percakapan yang sesuai'
                                        : emptyState.title
                                }
                                description={
                                    search.trim()
                                        ? 'Coba nama peserta atau jenis percakapan lain.'
                                        : emptyState.description
                                }
                            />
                        )}
                    </div>
                </ScrollArea>
            </div>
        </aside>
    );
}
