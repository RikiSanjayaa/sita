import {
    ChevronDown,
    ChevronRight,
    Inbox,
    Search,
    SlidersHorizontal,
    UserPlus,
    X,
} from 'lucide-react';
import { type ReactNode, useId, useRef, useState } from 'react';

import {
    PrivateRecipientCombobox,
    type PrivateRecipientOption,
} from '@/components/private-recipient-combobox';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { neutralAcademicTerminology } from '@/lib/academic-terminology';
import { cn } from '@/lib/utils';

import { ChatEmptyState } from './empty-state';
import { chatStyles } from './styles';
import { ThreadAvatar } from './thread-avatar';
import {
    type ChatMessage,
    type ChatThread,
    type ChatTone,
    type MobileView,
} from './types';
import { type PrivateThreadState } from './use-private-thread';

type ThreadListProps<T extends ChatThread> = {
    activeThreadId: number | null;
    emptyState: { title: string; description: string };
    groupExtra?: ReactNode;
    groupFilterToggle?: () => void;
    unreadCount?: (thread: T) => number;
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
    emptyState,
    groupExtra,
    groupFilterToggle,
    unreadCount,
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
    const [searchOpen, setSearchOpen] = useState(false);
    const searchId = useId();
    const searchToggleRef = useRef<HTMLButtonElement>(null);
    const showSearch = searchOpen || search.trim() !== '';

    function closeSearch() {
        onSearchChange('');
        setSearchOpen(false);
        searchToggleRef.current?.focus();
    }
    const showCompleted =
        completedOpen ||
        search.trim() !== '' ||
        completedThreads.some((thread) => thread.id === activeThreadId);

    function renderRow(thread: T) {
        const latestMessage = messagesFor(thread).at(-1);
        const isPrivate = thread.threadType === 'private';
        const unread = unreadCount?.(thread) ?? 0;
        return (
            <button
                key={thread.id}
                type="button"
                data-thread-id={thread.id}
                title={threadTitle(thread)}
                aria-current={activeThreadId === thread.id ? 'true' : undefined}
                className={cn(
                    'relative mx-2 flex min-h-17 shrink-0 items-center gap-3 rounded-2xl px-2 py-2.5 text-left transition-colors before:absolute before:inset-x-2 before:top-0 before:left-19 before:h-px before:bg-border first:before:hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset [&[aria-current]+button]:before:hidden',
                    activeThreadId === thread.id
                        ? 'bg-muted before:hidden dark:bg-background'
                        : 'hover:bg-muted/50',
                )}
                onClick={() => onSelect(thread.id)}
            >
                <ThreadAvatar thread={thread} />
                <span className="flex min-w-0 flex-1 flex-col gap-1">
                    <span className="flex items-center justify-between gap-2">
                        <span className="min-w-0 truncate text-sm font-semibold text-foreground">
                            {threadTitle(thread)}
                        </span>
                        <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                            {thread.lastTime.replace(/ yang lalu$/, '')}
                        </span>
                    </span>
                    <span className="flex items-center justify-between gap-2">
                        <span className="min-w-0 truncate text-sm text-muted-foreground">
                            <span className="text-xs font-medium">
                                {isPrivate
                                    ? 'Pribadi'
                                    : `Grup ${thread.threadLabel}`}
                                {' · '}
                            </span>
                            {latestMessage?.message ||
                                latestMessage?.documentName ||
                                thread.preview}
                        </span>
                        {unread > 0 && (
                            <span
                                aria-label={`${unread} pesan belum dibaca`}
                                className="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-primary px-1 text-xs font-medium text-primary-foreground tabular-nums"
                            >
                                <span aria-hidden="true">
                                    {unread > 99 ? '99+' : unread}
                                </span>
                            </span>
                        )}
                    </span>
                </span>
            </button>
        );
    }

    return (
        <aside
            aria-label="Daftar percakapan"
            className={cn(
                chatStyles.listCard(isMobile),
                mobileView === 'chat' && 'hidden lg:flex',
                mobileView === 'threads' && 'flex-1 lg:flex-initial',
            )}
        >
            <header className={chatStyles.listHeader(isMobile)}>
                <div className="flex items-center justify-between gap-3">
                    <h2 className="text-lg font-semibold">{title}</h2>
                    <div className="flex items-center gap-1">
                        <Button
                            ref={searchToggleRef}
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label={
                                showSearch
                                    ? 'Tutup pencarian percakapan'
                                    : 'Cari percakapan'
                            }
                            aria-expanded={showSearch}
                            aria-controls={searchId}
                            className="size-9 shrink-0 max-md:size-11"
                            onClick={() =>
                                showSearch ? closeSearch() : setSearchOpen(true)
                            }
                        >
                            {showSearch ? (
                                <X aria-hidden className="size-4" />
                            ) : (
                                <Search aria-hidden className="size-4" />
                            )}
                        </Button>
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
                        {groupFilterToggle && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Filter percakapan"
                                className="size-9 shrink-0 max-md:size-11"
                                onClick={groupFilterToggle}
                            >
                                <SlidersHorizontal
                                    aria-hidden
                                    className="size-4"
                                />
                            </Button>
                        )}
                    </div>
                </div>
                {showSearch && (
                    <div id={searchId} className="relative">
                        <Search
                            aria-hidden
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            autoFocus
                            value={search}
                            onChange={(event) =>
                                onSearchChange(event.target.value)
                            }
                            className="pl-9 max-md:h-11"
                            placeholder="Cari percakapan..."
                            aria-label="Cari percakapan"
                            onKeyDown={(event) => {
                                if (event.key === 'Escape') {
                                    event.preventDefault();
                                    closeSearch();
                                }
                            }}
                        />
                    </div>
                )}
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
