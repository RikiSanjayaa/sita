import { Inbox, Search, SlidersHorizontal, UserPlus } from 'lucide-react';
import { type ReactNode } from 'react';

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
                            className="pl-9"
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
                            className="size-9 shrink-0"
                            onClick={privateThread.toggle}
                        >
                            <UserPlus className="size-4" />
                        </Button>
                    ) : groupFilterToggle ? (
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            className="size-9 shrink-0"
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
                        {threads.length > 0 ? (
                            threads.map((thread) => {
                                const latestMessage =
                                    messagesFor(thread).at(-1);
                                const isActive = activeThreadId === thread.id;

                                return (
                                    <button
                                        key={thread.id}
                                        type="button"
                                        className={cn(
                                            'flex w-full shrink-0 flex-col border-b border-l-[3px] border-l-transparent p-4 text-left transition-all last:border-b-0 hover:bg-muted/50',
                                            isActive && 'border-l-primary',
                                        )}
                                        onClick={() => onSelect(thread.id)}
                                    >
                                        <div className="flex w-full items-start justify-between gap-3">
                                            <div className="flex min-w-0 flex-col gap-1.5">
                                                <p
                                                    className={cn(
                                                        'truncate font-semibold',
                                                        isActive
                                                            ? 'text-[15px] text-primary'
                                                            : 'text-sm text-foreground',
                                                    )}
                                                >
                                                    {threadTitle(thread)}
                                                </p>
                                                <p
                                                    className={cn(
                                                        'line-clamp-2 text-[13px]',
                                                        isActive
                                                            ? 'font-medium text-primary/90'
                                                            : 'text-muted-foreground',
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
                                                        'px-1.5 py-0.5 text-[9px] font-bold tracking-wider uppercase',
                                                        badgeClassName(thread),
                                                    )}
                                                >
                                                    {thread.threadLabel}
                                                </Badge>
                                                <div className="flex items-center gap-2">
                                                    <span className="text-[11px] font-medium text-muted-foreground">
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
                            })
                        ) : (
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
