import {
    ChevronDown,
    ChevronRight,
    Inbox,
    Search,
    SlidersHorizontal,
    UserPlus,
} from 'lucide-react';
import { type ReactNode, useState } from 'react';

import {
    PrivateRecipientCombobox,
    type PrivateRecipientOption,
} from '@/components/private-recipient-combobox';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { neutralAcademicTerminology } from '@/lib/academic-terminology';
import { cn } from '@/lib/utils';

import { ChatEmptyState } from './empty-state';
import { chatStyles } from './styles';
import {
    type ChatMessage,
    type ChatThread,
    type ChatTone,
    type MobileView,
    type ThreadMode,
} from './types';
import { type PrivateThreadState } from './use-private-thread';

type ThreadListProps<T extends ChatThread> = {
    activeThreadId: number | null;
    /** Class names for the role-specific `threadType` badge colour. */
    badgeClassName: (thread: T) => string;
    emptyState: { title: string; description: string };
    /** Extra controls shown under the search row in group mode (filters). */
    groupExtra?: ReactNode;
    /** Shows a filter toggle next to the search box in group mode. */
    groupFilterToggle?: () => void;
    hasUnread?: (thread: T) => boolean;
    isMobile: boolean;
    messagesFor: (thread: T) => ChatMessage[];
    mobileView: MobileView;
    mode: ThreadMode;
    onModeChange: (mode: ThreadMode) => void;
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

const modeOptions: { label: string; value: ThreadMode }[] = [
    { label: 'Grup', value: 'group' },
    { label: 'Pribadi', value: 'private' },
];

/** Side panel: mode switch, search, optional filters and the thread rows. */
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
    mode,
    onModeChange,
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
    const isRaised = tone === 'raised';
    const gapClass = isRaised ? 'mt-3' : undefined;
    const picker = (
        <PrivateRecipientCombobox
            recipients={privateRecipients}
            value={privateThread.recipientId}
            onValueChange={privateThread.setRecipientId}
            onSubmit={privateThread.start}
            disabled={privateThread.processing}
        />
    );

    const activeThreads = threads.filter((thread) => !thread.isCompleted);
    const completedThreads = threads.filter((thread) => thread.isCompleted);
    const [completedOpen, setCompletedOpen] = useState(false);
    // The open thread must stay reachable in the list, so its section never hides it.
    const showCompleted =
        completedOpen ||
        completedThreads.some((thread) => thread.id === activeThreadId);

    function renderRow(thread: T) {
        const latestMessage = messagesFor(thread).at(-1);
        const isActive = activeThreadId === thread.id;

        return (
            <button
                key={thread.id}
                type="button"
                className={cn(
                    'flex w-full shrink-0 flex-col border-b p-4 text-left transition-colors last:border-b-0',
                    isActive ? 'bg-primary-muted' : 'hover:bg-muted/50',
                )}
                onClick={() => onSelect(thread.id)}
            >
                <div className="flex w-full items-start justify-between gap-3">
                    <div className="flex min-w-0 flex-col gap-1.5">
                        <p
                            className={cn(
                                'truncate text-sm font-semibold text-foreground',
                            )}
                        >
                            {threadTitle(thread)}
                        </p>
                        <p
                            className={cn(
                                'line-clamp-2 text-sm text-pretty text-muted-foreground',
                            )}
                        >
                            {latestMessage?.message ||
                                latestMessage?.documentName ||
                                thread.preview}
                        </p>
                    </div>
                    <div className="flex shrink-0 flex-col items-end gap-2">
                        <Badge
                            variant="soft"
                            className={cn(
                                'px-2 py-0.5 text-xs font-medium',
                                badgeClassName(thread),
                            )}
                        >
                            {thread.threadLabel}
                        </Badge>
                        <div className="flex items-center gap-2">
                            <span className="text-xs font-medium text-muted-foreground">
                                {thread.lastTime}
                            </span>
                            {hasUnread?.(thread) && (
                                <div className="size-2 rounded-full bg-primary" />
                            )}
                        </div>
                    </div>
                </div>
            </button>
        );
    }

    return (
        <Card
            className={cn(
                chatStyles.listCard(tone, isMobile),
                mobileView === 'chat' && 'hidden lg:flex',
                mobileView === 'threads' && 'flex-1 lg:flex-initial',
            )}
        >
            <CardHeader className={chatStyles.listHeader(tone, isMobile)}>
                {isRaised ? (
                    <CardTitle>{title}</CardTitle>
                ) : (
                    <div>
                        <CardTitle>{title}</CardTitle>
                    </div>
                )}
                <div className={cn('grid grid-cols-2 gap-2', gapClass)}>
                    {modeOptions.map(({ label, value }) => (
                        <Button
                            key={value}
                            type="button"
                            size="sm"
                            className="max-md:h-11"
                            variant={mode === value ? 'default' : 'outline'}
                            onClick={() => onModeChange(value)}
                        >
                            {label}
                        </Button>
                    ))}
                </div>

                <div className={cn('flex items-center gap-2', gapClass)}>
                    <div className="relative min-w-0 flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) =>
                                onSearchChange(event.target.value)
                            }
                            className="pl-9 max-md:h-11"
                            placeholder={
                                mode === 'private'
                                    ? 'Cari chat pribadi...'
                                    : 'Cari grup...'
                            }
                        />
                    </div>
                    {mode === 'private' ? (
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            className="size-9 shrink-0 max-md:size-11"
                            onClick={privateThread.toggle}
                        >
                            <UserPlus className="size-4" />
                        </Button>
                    ) : groupFilterToggle ? (
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            className="size-9 shrink-0 max-md:size-11"
                            onClick={groupFilterToggle}
                        >
                            <SlidersHorizontal className="size-4" />
                        </Button>
                    ) : null}
                </div>

                {mode === 'group' ? groupExtra : null}

                {mode === 'private' ? (
                    isRaised ? (
                        <div
                            className={cn(
                                gapClass,
                                !privateThread.isOpen && 'hidden',
                            )}
                        >
                            {picker}
                        </div>
                    ) : privateThread.isOpen ? (
                        picker
                    ) : null
                ) : null}
            </CardHeader>
            {isRaised ? null : <Separator />}
            <CardContent className="relative flex-1 overflow-hidden p-0">
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
                                    className="flex w-full shrink-0 items-center gap-2 border-b bg-muted/40 px-4 py-3 text-left text-sm font-semibold text-muted-foreground transition-colors hover:bg-muted/60"
                                    onClick={() =>
                                        setCompletedOpen((open) => !open)
                                    }
                                >
                                    {showCompleted ? (
                                        <ChevronDown className="size-4" />
                                    ) : (
                                        <ChevronRight className="size-4" />
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
                                title={emptyState.title}
                                description={emptyState.description}
                            />
                        )}
                    </div>
                </ScrollArea>
            </CardContent>
        </Card>
    );
}
