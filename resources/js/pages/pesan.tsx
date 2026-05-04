import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ChevronDown,
    ChevronUp,
    Inbox,
    Paperclip,
    Search,
    Send,
    Users,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type ChangeEvent } from 'react';

import { ChatBubble } from '@/components/chat-bubble';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useInitials } from '@/hooks/use-initials';
import { useIsMobile } from '@/hooks/use-mobile';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { dashboard, pesan } from '@/routes';
import {
    type BreadcrumbItem,
    type SharedData,
    type UserProfileSummary,
} from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Pesan', href: pesan().url },
];

const panelCardClass = 'overflow-hidden border-border/70 !gap-0 !p-0 shadow-sm';
const panelHeaderClass = 'shrink-0 border-b bg-muted/20 px-6 py-4';

type ChatMessage = {
    id: number;
    senderUserId: number | null;
    author: string;
    authorAvatar: string | null;
    authorProfileUrl: string | null;
    message: string;
    time: string;
    type: string;
    documentName: string | null;
    documentUrl: string | null;
};

type ThreadItem = {
    id: number;
    name: string;
    threadType: string;
    threadLabel: string;
    members: string[];
    memberProfiles: UserProfileSummary[];
    messages: ChatMessage[];
    preview: string;
    lastTime: string;
    latestActivityAt: string | null;
};

function messageMatches(message: ChatMessage, query: string) {
    if (!query) {
        return false;
    }

    return [message.author, message.message, message.documentName, message.type]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()
        .includes(query);
}

type PesanPageProps = {
    hasDosbing: boolean;
    threads: ThreadItem[];
};

type PesanPageContentProps = Pick<SharedData, 'auth'> & {
    hasDosbing: boolean;
    initialThreads: ThreadItem[];
};

function buildThreadStateKey(threads: ThreadItem[]): string {
    return threads
        .map(
            (thread) =>
                `${thread.id}:${thread.messages.length}:${thread.latestActivityAt ?? ''}`,
        )
        .join('|');
}

function sortThreads(threads: ThreadItem[]): ThreadItem[] {
    return [...threads].sort((a, b) => {
        if (a.latestActivityAt === b.latestActivityAt) {
            return b.id - a.id;
        }

        if (a.latestActivityAt === null) {
            return 1;
        }

        if (b.latestActivityAt === null) {
            return -1;
        }

        return b.latestActivityAt.localeCompare(a.latestActivityAt);
    });
}

function syncThreadSearchParam(threadId: number | null): void {
    if (typeof window === 'undefined') {
        return;
    }

    const nextUrl = new URL(window.location.href);

    if (threadId === null) {
        nextUrl.searchParams.delete('thread');
    } else {
        nextUrl.searchParams.set('thread', String(threadId));
    }

    window.history.replaceState(
        window.history.state,
        '',
        `${nextUrl.pathname}${nextUrl.search}${nextUrl.hash}`,
    );
}

function resolveInitialThreadId(threads: ThreadItem[]): number | null {
    if (typeof window !== 'undefined') {
        const queryThread = Number(
            new URLSearchParams(window.location.search).get('thread'),
        );
        if (
            Number.isInteger(queryThread) &&
            threads.some((t) => t.id === queryThread)
        ) {
            return queryThread;
        }
    }
    return threads[0]?.id ?? null;
}

export default function PesanPage() {
    const {
        threads: initialThreads,
        hasDosbing,
        auth,
    } = usePage<SharedData & PesanPageProps>().props;

    return (
        <PesanPageContent
            key={buildThreadStateKey(initialThreads)}
            initialThreads={initialThreads}
            hasDosbing={hasDosbing}
            auth={auth}
        />
    );
}

function PesanPageContent({
    initialThreads,
    hasDosbing,
    auth,
}: PesanPageContentProps) {
    const getInitials = useInitials();
    const isMobile = useIsMobile();

    const [mobileView, setMobileView] = useState<'threads' | 'chat'>('threads');
    const [activeThreadId, setActiveThreadId] = useState<number | null>(
        resolveInitialThreadId(initialThreads),
    );
    const [threadSearch, setThreadSearch] = useState('');
    const [isThreadSearchOpen, setIsThreadSearchOpen] = useState(false);
    const [activeMatchIndex, setActiveMatchIndex] = useState(0);
    const [threadItems, setThreadItems] = useState<ThreadItem[]>(() =>
        sortThreads(initialThreads),
    );
    const [messagesByThread, setMessagesByThread] = useState<
        Record<number, ChatMessage[]>
    >(() => Object.fromEntries(initialThreads.map((t) => [t.id, t.messages])));
    const [attachmentName, setAttachmentName] = useState<string | null>(null);
    const fileRef = useRef<HTMLInputElement | null>(null);
    const messagesEndRef = useRef<HTMLDivElement | null>(null);
    const messageRefs = useRef<Record<string, HTMLDivElement | null>>({});

    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    const form = useForm<{ message: string; attachment: File | null }>({
        message: '',
        attachment: null,
    });

    const resolvedActiveThreadId = useMemo(() => {
        if (activeThreadId !== null) {
            const matchingThread = threadItems.find(
                (thread) => thread.id === activeThreadId,
            );

            if (matchingThread) {
                return matchingThread.id;
            }
        }

        return threadItems[0]?.id ?? null;
    }, [activeThreadId, threadItems]);

    useEffect(() => {
        if (typeof window === 'undefined' || !window.Echo) {
            return;
        }

        const channels = initialThreads.map((thread) => {
            const channelName = `mentorship.thread.${thread.id}`;

            return window.Echo.private(channelName).listen(
                '.chat.message.created',
                (event: { threadId: number; message: ChatMessage }) => {
                    setMessagesByThread((current) => {
                        const currentMessages = current[event.threadId] ?? [];
                        if (
                            currentMessages.some(
                                (m) => m.id === event.message.id,
                            )
                        ) {
                            return current;
                        }

                        if (
                            resolvedActiveThreadId === event.threadId &&
                            event.message.senderUserId !== auth.user?.id
                        ) {
                            setTimeout(() => {
                                messagesEndRef.current?.scrollIntoView({
                                    behavior: 'smooth',
                                });
                            }, 50);
                        }

                        return {
                            ...current,
                            [event.threadId]: [
                                ...currentMessages,
                                event.message,
                            ],
                        };
                    });

                    setThreadItems((current) => {
                        const targetThread = current.find(
                            (thread) => thread.id === event.threadId,
                        );

                        if (!targetThread) {
                            return current;
                        }

                        const updatedThread: ThreadItem = {
                            ...targetThread,
                            preview:
                                event.message.message ||
                                event.message.documentName ||
                                targetThread.preview,
                            lastTime: 'baru saja',
                            latestActivityAt: new Date().toISOString(),
                        };

                        return sortThreads([
                            updatedThread,
                            ...current.filter(
                                (thread) => thread.id !== event.threadId,
                            ),
                        ]);
                    });
                },
            );
        });

        return () => {
            for (const [index, thread] of initialThreads.entries()) {
                channels[index]?.stopListening('.chat.message.created');
                window.Echo.leave(`mentorship.thread.${thread.id}`);
            }
        };
    }, [auth.user?.id, initialThreads, resolvedActiveThreadId]);

    const activeThread = useMemo(
        () =>
            threadItems.find(
                (thread) => thread.id === resolvedActiveThreadId,
            ) ?? null,
        [resolvedActiveThreadId, threadItems],
    );

    const activeMessages = useMemo(
        () => (activeThread ? (messagesByThread[activeThread.id] ?? []) : []),
        [activeThread, messagesByThread],
    );
    const activeMessageCount = activeMessages.length;

    const normalizedThreadSearch = threadSearch.trim().toLowerCase();

    const matchingMessageIds = useMemo(
        () =>
            normalizedThreadSearch
                ? activeMessages
                      .filter((message) =>
                          messageMatches(message, normalizedThreadSearch),
                      )
                      .map((message) => String(message.id))
                : [],
        [activeMessages, normalizedThreadSearch],
    );

    const matchingMessageIdSet = useMemo(
        () => new Set(matchingMessageIds),
        [matchingMessageIds],
    );

    const resolvedActiveMatchIndex =
        matchingMessageIds.length > 0
            ? Math.min(activeMatchIndex, matchingMessageIds.length - 1)
            : 0;

    const activeMatchMessageId =
        matchingMessageIds.length > 0
            ? matchingMessageIds[resolvedActiveMatchIndex]
            : null;

    useEffect(() => {
        if (activeMessageCount < 1 || resolvedActiveThreadId === null) {
            return;
        }

        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [activeMessageCount, resolvedActiveThreadId]);

    useEffect(() => {
        if (!activeMatchMessageId) {
            return;
        }

        messageRefs.current[activeMatchMessageId]?.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
        });
    }, [activeMatchMessageId]);

    const canSend = useMemo(
        () =>
            !form.processing &&
            activeThread !== null &&
            (form.data.message.trim() !== '' || form.data.attachment !== null),
        [
            activeThread,
            form.data.attachment,
            form.data.message,
            form.processing,
        ],
    );

    function selectThread(threadId: number) {
        setActiveThreadId(threadId);
        setThreadSearch('');
        setIsThreadSearchOpen(false);
        setActiveMatchIndex(0);
        syncThreadSearchParam(threadId);
        setMobileView('chat');
    }

    function handleThreadSearchChange(value: string) {
        setThreadSearch(value);
        setActiveMatchIndex(0);
    }

    function pickAttachment(event: ChangeEvent<HTMLInputElement>) {
        const nextFile = event.target.files?.[0] ?? null;
        form.setData('attachment', nextFile);
        setAttachmentName(nextFile?.name ?? null);
    }

    function sendMessage() {
        if (!canSend || activeThread === null) return;

        form.transform((data) => ({
            ...data,
            message: data.message.trim(),
        }));

        form.post(`/mahasiswa/pesan/${activeThread.id}/messages`, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                form.reset('message', 'attachment');
                setAttachmentName(null);
                if (fileRef.current) fileRef.current.value = '';
                setTimeout(() => scrollToBottom(), 50);
            },
        });
    }

    function stepMatch(direction: 1 | -1) {
        if (matchingMessageIds.length === 0) {
            return;
        }

        setActiveMatchIndex((current) => {
            const next = current + direction;

            if (next < 0) {
                return matchingMessageIds.length - 1;
            }

            if (next >= matchingMessageIds.length) {
                return 0;
            }

            return next;
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pesan" />

            <div
                className={cn(
                    'mx-auto flex h-[calc(100dvh-4rem)] w-full max-w-7xl flex-1 lg:grid lg:h-[calc(100dvh-4rem-3rem)] lg:grid-cols-[340px_1fr]',
                    isMobile ? 'gap-0 px-0 py-0' : 'gap-6 px-4 py-6 md:px-6',
                )}
            >
                {/* Thread List */}
                <Card
                    className={cn(
                        'flex min-h-0 flex-col lg:h-full lg:w-[340px] lg:shrink-0',
                        isMobile
                            ? 'rounded-none border-0 shadow-none'
                            : panelCardClass,
                        mobileView === 'chat' && 'hidden lg:flex',
                        mobileView === 'threads' && 'flex-1 lg:flex-initial',
                    )}
                >
                    <CardHeader
                        className={cn(
                            panelHeaderClass,
                            isMobile && 'px-4 py-4',
                        )}
                    >
                        <CardTitle>Pesan</CardTitle>
                        <CardDescription>
                            Thread bimbingan dan sempro Anda
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="relative flex-1 overflow-hidden p-0">
                        <ScrollArea className="h-full w-full">
                            <div className="flex flex-col">
                                {threadItems.length > 0 ? (
                                    threadItems.map((thread) => {
                                        const threadMessages =
                                            messagesByThread[thread.id] ??
                                            thread.messages;
                                        const latestMessage =
                                            threadMessages.at(-1);

                                        return (
                                            <button
                                                key={thread.id}
                                                type="button"
                                                className={cn(
                                                    'flex w-full shrink-0 flex-col border-b border-l-[3px] border-l-transparent p-4 text-left transition-all last:border-b-0 hover:bg-muted/50',
                                                    activeThread?.id ===
                                                        thread.id &&
                                                        'border-l-primary',
                                                )}
                                                onClick={() =>
                                                    selectThread(thread.id)
                                                }
                                            >
                                                <div className="flex w-full items-start justify-between gap-3">
                                                    <div className="flex min-w-0 flex-col gap-1.5">
                                                        <p
                                                            className={cn(
                                                                'truncate font-semibold',
                                                                activeThread?.id ===
                                                                    thread.id
                                                                    ? 'text-[15px] text-primary'
                                                                    : 'text-sm text-foreground',
                                                            )}
                                                        >
                                                            {thread.name}
                                                        </p>
                                                        <p
                                                            className={cn(
                                                                'line-clamp-2 text-[13px]',
                                                                activeThread?.id ===
                                                                    thread.id
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
                                                                thread.threadType ===
                                                                    'pembimbing'
                                                                    ? 'bg-primary/10 text-primary hover:bg-primary/20'
                                                                    : 'bg-amber-600/10 text-amber-600 hover:bg-amber-600/20',
                                                            )}
                                                        >
                                                            {thread.threadLabel}
                                                        </Badge>
                                                        <div className="flex items-center gap-2">
                                                            <span className="text-[11px] font-medium text-muted-foreground">
                                                                {
                                                                    thread.lastTime
                                                                }
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </button>
                                        );
                                    })
                                ) : (
                                    <div className="rounded-xl border border-dashed bg-muted/50 p-6 text-center shadow-sm">
                                        <span className="mx-auto mb-3 inline-flex size-10 items-center justify-center rounded-full bg-background text-muted-foreground shadow-sm">
                                            <Inbox className="size-5" />
                                        </span>
                                        <p className="text-sm font-semibold text-foreground">
                                            Belum ada thread
                                        </p>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Thread akan muncul setelah dosen
                                            pembimbing atau penguji ditetapkan.
                                        </p>
                                    </div>
                                )}
                            </div>
                        </ScrollArea>
                    </CardContent>
                </Card>

                {/* Chat Panel */}
                <Card
                    className={cn(
                        'flex min-h-0 flex-1 flex-col lg:h-full',
                        isMobile
                            ? 'rounded-none border-0 shadow-none'
                            : panelCardClass,
                        mobileView === 'threads' && 'hidden lg:flex',
                    )}
                >
                    <CardHeader
                        className={cn(
                            panelHeaderClass,
                            isMobile && 'px-4 py-4',
                        )}
                    >
                        {activeThread ? (
                            <>
                                <div className="flex items-center gap-4">
                                    <div className="flex min-w-0 flex-1 items-center gap-4">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="-ml-2 shrink-0 lg:hidden"
                                            onClick={() =>
                                                setMobileView('threads')
                                            }
                                        >
                                            <ArrowLeft className="size-5" />
                                        </Button>

                                        {activeThread.memberProfiles.length >
                                        0 ? (
                                            <TooltipProvider delayDuration={0}>
                                                <div className="hidden shrink-0 items-center -space-x-3 sm:flex">
                                                    {activeThread.memberProfiles.map(
                                                        (member) => (
                                                            <Tooltip
                                                                key={member.id}
                                                            >
                                                                <TooltipTrigger
                                                                    asChild
                                                                >
                                                                    <Link
                                                                        href={
                                                                            member.profileUrl
                                                                        }
                                                                        className="relative z-0 transition-transform hover:z-10 hover:scale-110"
                                                                    >
                                                                        <Avatar className="size-10 border-2 border-background bg-background shadow-xs">
                                                                            <AvatarImage
                                                                                src={
                                                                                    member.avatar ??
                                                                                    undefined
                                                                                }
                                                                                alt={
                                                                                    member.name
                                                                                }
                                                                            />
                                                                            <AvatarFallback className="bg-primary/10 text-xs text-primary">
                                                                                {getInitials(
                                                                                    member.name,
                                                                                )}
                                                                            </AvatarFallback>
                                                                        </Avatar>
                                                                    </Link>
                                                                </TooltipTrigger>
                                                                <TooltipContent className="px-3 py-1.5 text-xs">
                                                                    <p className="font-semibold">
                                                                        {
                                                                            member.name
                                                                        }
                                                                    </p>
                                                                    {member.subtitle && (
                                                                        <p className="opacity-80">
                                                                            {
                                                                                member.subtitle
                                                                            }
                                                                        </p>
                                                                    )}
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        ),
                                                    )}
                                                </div>
                                            </TooltipProvider>
                                        ) : null}

                                        <div className="flex min-w-0 flex-col gap-0.5">
                                            <div className="flex items-center gap-2">
                                                <CardTitle className="truncate">
                                                    {activeThread.name}
                                                </CardTitle>
                                                <Badge
                                                    variant="soft"
                                                    className={cn(
                                                        'font-medium',
                                                        activeThread.threadType ===
                                                            'pembimbing'
                                                            ? 'bg-primary/10 text-primary hover:bg-primary/20'
                                                            : 'bg-amber-600/10 text-amber-600 hover:bg-amber-600/20',
                                                    )}
                                                >
                                                    {activeThread.threadLabel}
                                                </Badge>
                                            </div>
                                            <CardDescription className="truncate">
                                                {activeThread.members.join(
                                                    ', ',
                                                )}
                                            </CardDescription>
                                        </div>
                                    </div>

                                    <div className="flex shrink-0 items-center gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="size-8 text-muted-foreground hover:text-foreground"
                                            onClick={() => {
                                                setIsThreadSearchOpen(
                                                    (current) => !current,
                                                );
                                                if (isThreadSearchOpen) {
                                                    setThreadSearch('');
                                                    setActiveMatchIndex(0);
                                                }
                                            }}
                                        >
                                            <Search className="size-4" />
                                        </Button>
                                    </div>
                                </div>
                                {isThreadSearchOpen ? (
                                    <div className="mt-3 space-y-2">
                                        <div className="flex items-center gap-2">
                                            <Input
                                                value={threadSearch}
                                                onChange={(event) =>
                                                    handleThreadSearchChange(
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="Cari di percakapan ini..."
                                                className="h-9 flex-1"
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                className="size-9"
                                                disabled={
                                                    matchingMessageIds.length ===
                                                    0
                                                }
                                                onClick={() => stepMatch(-1)}
                                            >
                                                <ChevronUp className="size-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                className="size-9"
                                                disabled={
                                                    matchingMessageIds.length ===
                                                    0
                                                }
                                                onClick={() => stepMatch(1)}
                                            >
                                                <ChevronDown className="size-4" />
                                            </Button>
                                        </div>
                                        {threadSearch.trim() ? (
                                            <p className="text-xs text-muted-foreground">
                                                {matchingMessageIds.length > 0
                                                    ? `${resolvedActiveMatchIndex + 1} dari ${matchingMessageIds.length} hasil`
                                                    : 'Tidak ada hasil yang cocok'}
                                            </p>
                                        ) : null}
                                    </div>
                                ) : null}
                            </>
                        ) : (
                            <>
                                <div className="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="-ml-2 lg:hidden"
                                        onClick={() => setMobileView('threads')}
                                    >
                                        <ArrowLeft className="size-5" />
                                    </Button>
                                    <CardTitle>Pilih Thread</CardTitle>
                                </div>
                                <CardDescription>
                                    Buka salah satu percakapan untuk melihat
                                    chat.
                                </CardDescription>
                            </>
                        )}
                    </CardHeader>
                    <CardContent className="relative flex-1 overflow-hidden p-0">
                        <ScrollArea className="h-full w-full">
                            <div
                                className={cn(
                                    'flex min-h-full flex-col',
                                    isMobile ? 'px-4 py-3' : 'p-4',
                                )}
                            >
                                {activeThread &&
                                    !hasDosbing &&
                                    activeThread.threadType ===
                                        'pembimbing' && (
                                        <Alert className="mb-3 border-red-200 bg-red-50 text-red-900 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-200">
                                            <AlertTitle className="text-red-900 dark:text-red-200">
                                                Belum ada Dosen Pembimbing
                                            </AlertTitle>
                                            <AlertDescription className="text-red-800 dark:text-red-300">
                                                Anda belum memiliki dosen
                                                pembimbing aktif. Pesan akan
                                                tersedia setelah dosen
                                                pembimbing ditetapkan.
                                            </AlertDescription>
                                        </Alert>
                                    )}

                                {activeThread ? (
                                    <div className="grid">
                                        {activeMessages.map((message) => {
                                            const isMe =
                                                message.senderUserId ===
                                                auth.user?.id;
                                            const messageId = String(
                                                message.id,
                                            );

                                            return (
                                                <div
                                                    key={message.id}
                                                    ref={(element) => {
                                                        messageRefs.current[
                                                            messageId
                                                        ] = element;
                                                    }}
                                                >
                                                    <ChatBubble
                                                        message={message}
                                                        isMe={isMe}
                                                        searchTerm={
                                                            matchingMessageIdSet.has(
                                                                messageId,
                                                            )
                                                                ? normalizedThreadSearch
                                                                : ''
                                                        }
                                                        isActiveMatch={
                                                            activeMatchMessageId ===
                                                            messageId
                                                        }
                                                    />
                                                </div>
                                            );
                                        })}
                                        <div
                                            ref={messagesEndRef}
                                            className="h-1"
                                        />
                                    </div>
                                ) : (
                                    <div className="mt-10 rounded-xl border border-dashed bg-muted/50 p-8 text-center shadow-sm">
                                        <span className="mx-auto mb-3 inline-flex size-10 items-center justify-center rounded-full bg-background text-muted-foreground shadow-sm">
                                            <Users className="size-5" />
                                        </span>
                                        <p className="text-sm font-semibold text-foreground">
                                            Belum ada thread yang dipilih
                                        </p>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Pilih thread di panel kiri untuk
                                            mulai berdiskusi.
                                        </p>
                                    </div>
                                )}
                            </div>
                        </ScrollArea>
                    </CardContent>

                    <Separator />

                    <CardFooter
                        className={cn(
                            'shrink-0 flex-col items-stretch gap-3 pt-4',
                            isMobile ? 'px-4 pb-4' : 'p-6',
                        )}
                    >
                        <input
                            ref={fileRef}
                            type="file"
                            accept=".pdf,.doc,.docx"
                            className="hidden"
                            onChange={pickAttachment}
                        />
                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                onClick={() => fileRef.current?.click()}
                                disabled={activeThread === null}
                            >
                                <Paperclip className="size-4" />
                            </Button>
                            <Input
                                value={form.data.message}
                                onChange={(event) =>
                                    form.setData('message', event.target.value)
                                }
                                placeholder="Tulis pesan..."
                                disabled={activeThread === null}
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        event.preventDefault();
                                        sendMessage();
                                    }
                                }}
                            />
                            <Button
                                type="button"
                                onClick={sendMessage}
                                disabled={!canSend}
                                className="bg-primary text-primary-foreground hover:bg-primary/90"
                            >
                                <Send className="size-4" />
                            </Button>
                        </div>
                        {attachmentName && (
                            <div className="truncate text-xs text-muted-foreground">
                                Lampiran: {attachmentName}
                            </div>
                        )}
                        {form.errors.attachment && (
                            <p className="text-xs text-destructive">
                                {form.errors.attachment}
                            </p>
                        )}
                        {form.errors.message && (
                            <p className="text-xs text-destructive">
                                {form.errors.message}
                            </p>
                        )}
                    </CardFooter>
                </Card>
            </div>
        </AppLayout>
    );
}
