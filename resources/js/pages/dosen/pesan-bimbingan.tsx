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
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { useInitials } from '@/hooks/use-initials';
import { useIsMobile } from '@/hooks/use-mobile';
import DosenLayout from '@/layouts/dosen-layout';
import { cn } from '@/lib/utils';
import {
    type BreadcrumbItem,
    type SharedData,
    type UserProfileSummary,
} from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dosen/dashboard' },
    { title: 'Pesan Bimbingan', href: '/dosen/pesan-bimbingan' },
];

type ThreadMessage = {
    id: number;
    senderUserId: number | null;
    author: string;
    authorAvatar: string | null;
    authorProfileUrl: string | null;
    message: string;
    time: string;
    type:
        | 'text'
        | 'document_event'
        | 'attachment'
        | 'revision_suggestion'
        | string;
    documentName: string | null;
    documentUrl: string | null;
};

type ThreadItem = {
    id: number;
    student: string;
    studentProfile: UserProfileSummary | null;
    members: string[];
    memberProfiles: UserProfileSummary[];
    unread: number;
    preview: string;
    lastTime: string;
    latestActivityAt: string | null;
    isEscalated: boolean;
    isArchived: boolean;
    threadType: string;
    threadLabel: string;
    messages: ThreadMessage[];
};

function messageMatches(message: ThreadMessage, query: string) {
    if (!query) {
        return false;
    }

    return [message.author, message.message, message.documentName, message.type]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()
        .includes(query);
}

type PesanBimbinganProps = {
    threads: ThreadItem[];
    tab: 'aktif' | 'arsip';
    flashMessage?: string | null;
};

type PesanBimbinganContentProps = Pick<SharedData, 'auth'> & {
    initialThreads: ThreadItem[];
    tab: 'aktif' | 'arsip';
    flashMessage?: string | null;
};

function buildThreadStateKey(
    threads: ThreadItem[],
    tab: 'aktif' | 'arsip',
): string {
    return [
        tab,
        ...threads.map(
            (thread) =>
                `${thread.id}:${thread.messages.length}:${thread.unread}:${thread.latestActivityAt ?? ''}`,
        ),
    ].join('|');
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

function getVisibleThreads(
    threads: ThreadItem[],
    search: string,
    activeThreadId: number | null,
): ThreadItem[] {
    return sortThreads(threads)
        .map((thread) => {
            if (thread.id === activeThreadId) {
                return { ...thread, unread: 0 };
            }

            return thread;
        })
        .filter((thread) => {
            return thread.student
                .toLowerCase()
                .includes(search.trim().toLowerCase());
        });
}

function resolveInitialThreadId(initialThreads: ThreadItem[]): number | null {
    const fallbackThreadId = initialThreads[0]?.id ?? null;

    if (typeof window === 'undefined') {
        return fallbackThreadId;
    }

    const queryThread = Number(
        new URLSearchParams(window.location.search).get('thread'),
    );

    if (
        Number.isInteger(queryThread) &&
        initialThreads.some((thread) => thread.id === queryThread)
    ) {
        return queryThread;
    }

    return fallbackThreadId;
}

export default function DosenPesanBimbinganPage() {
    const {
        threads: initialThreads,
        tab,
        flashMessage,
        auth,
    } = usePage<SharedData & PesanBimbinganProps>().props;

    return (
        <DosenPesanBimbinganContent
            key={buildThreadStateKey(initialThreads, tab)}
            initialThreads={initialThreads}
            tab={tab}
            flashMessage={flashMessage}
            auth={auth}
        />
    );
}

function DosenPesanBimbinganContent({
    initialThreads,
    tab,
    flashMessage,
    auth,
}: PesanBimbinganContentProps) {
    const getInitials = useInitials();
    const isMobile = useIsMobile();

    const [mobileView, setMobileView] = useState<'threads' | 'chat'>('threads');
    const [search, setSearch] = useState('');
    const [threadSearch, setThreadSearch] = useState('');
    const [isThreadSearchOpen, setIsThreadSearchOpen] = useState(false);
    const [activeMatchIndex, setActiveMatchIndex] = useState(0);
    const [threadItems, setThreadItems] =
        useState<ThreadItem[]>(initialThreads);
    const [activeThreadId, setActiveThreadId] = useState<number | null>(
        resolveInitialThreadId(initialThreads),
    );
    const [messagesByThread, setMessagesByThread] = useState<
        Record<number, ThreadMessage[]>
    >(() =>
        Object.fromEntries(
            initialThreads.map((thread) => [thread.id, thread.messages]),
        ),
    );
    const [attachmentName, setAttachmentName] = useState<string | null>(null);
    const fileRef = useRef<HTMLInputElement | null>(null);
    const messagesEndRef = useRef<HTMLDivElement | null>(null);
    const messageRefs = useRef<Record<string, HTMLDivElement | null>>({});

    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    const form = useForm<{
        message: string;
        attachment: File | null;
    }>({
        message: '',
        attachment: null,
    });

    async function markThreadAsRead(threadId: number) {
        setThreadItems((current) =>
            current.map((thread) =>
                thread.id === threadId ? { ...thread, unread: 0 } : thread,
            ),
        );

        const csrfToken =
            document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content') ?? '';

        await fetch(`/dosen/pesan-bimbingan/${threadId}/read`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({}),
        });
    }

    const visibleThreads = useMemo(
        () => getVisibleThreads(threadItems, search, activeThreadId),
        [activeThreadId, search, threadItems],
    );

    const evaluatedActiveThreadId = useMemo(() => {
        if (visibleThreads.length === 0) {
            return null;
        }

        if (
            activeThreadId === null ||
            !visibleThreads.some((thread) => thread.id === activeThreadId)
        ) {
            return visibleThreads[0].id;
        }

        return activeThreadId;
    }, [activeThreadId, visibleThreads]);

    const activeThread =
        visibleThreads.find((t) => t.id === evaluatedActiveThreadId) ?? null;

    const activeMessages =
        activeThread === null ? [] : (messagesByThread[activeThread.id] ?? []);

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
        if (typeof window === 'undefined' || !window.Echo) {
            return;
        }

        const channels = initialThreads.map((thread) => {
            const channelName = `mentorship.thread.${thread.id}`;

            return window.Echo.private(channelName).listen(
                '.chat.message.created',
                (event: { threadId: number; message: ThreadMessage }) => {
                    setMessagesByThread((current) => {
                        const currentMessages = current[event.threadId] ?? [];
                        if (
                            currentMessages.some(
                                (message) => message.id === event.message.id,
                            )
                        ) {
                            return current;
                        }

                        if (
                            evaluatedActiveThreadId === event.threadId &&
                            event.message.senderUserId !== auth.user?.id
                        ) {
                            setTimeout(() => scrollToBottom(), 50);
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

                        const latestPreview =
                            event.message.message ||
                            event.message.documentName ||
                            targetThread.preview;
                        const isIncomingMessage =
                            event.message.senderUserId !== null &&
                            event.message.senderUserId !== auth.user?.id;
                        const shouldIncrementUnread =
                            isIncomingMessage &&
                            evaluatedActiveThreadId !== event.threadId;

                        const updatedThread: ThreadItem = {
                            ...targetThread,
                            preview: latestPreview,
                            lastTime: 'baru saja',
                            latestActivityAt: new Date().toISOString(),
                            unread: shouldIncrementUnread
                                ? targetThread.unread + 1
                                : targetThread.unread,
                        };

                        return [
                            updatedThread,
                            ...current.filter(
                                (thread) => thread.id !== event.threadId,
                            ),
                        ];
                    });

                    if (
                        event.threadId === evaluatedActiveThreadId &&
                        event.message.senderUserId !== null &&
                        event.message.senderUserId !== auth.user?.id
                    ) {
                        void markThreadAsRead(event.threadId);
                    }
                },
            );
        });

        return () => {
            for (const [index, thread] of initialThreads.entries()) {
                channels[index]?.stopListening('.chat.message.created');
                window.Echo.leave(`mentorship.thread.${thread.id}`);
            }
        };
    }, [auth.user?.id, evaluatedActiveThreadId, initialThreads]);

    useEffect(() => {
        scrollToBottom();
    }, [activeMessages.length, evaluatedActiveThreadId]);

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
        void markThreadAsRead(threadId);
    }

    function handleSearchChange(nextSearch: string) {
        const nextVisibleThreads = getVisibleThreads(
            threadItems,
            nextSearch,
            activeThreadId,
        );
        const nextActiveThreadId = nextVisibleThreads.some(
            (thread) => thread.id === activeThreadId,
        )
            ? activeThreadId
            : (nextVisibleThreads[0]?.id ?? null);

        setSearch(nextSearch);

        if (nextActiveThreadId !== activeThreadId) {
            setActiveThreadId(nextActiveThreadId);
            setThreadSearch('');
            setIsThreadSearchOpen(false);
            setActiveMatchIndex(0);
            syncThreadSearchParam(nextActiveThreadId);

            if (nextActiveThreadId !== null) {
                void markThreadAsRead(nextActiveThreadId);
            }
        }
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
        if (
            !canSend ||
            activeThread === null ||
            evaluatedActiveThreadId === null
        ) {
            return;
        }

        form.transform((data) => ({
            ...data,
            message: data.message.trim(),
        }));

        form.post(
            `/dosen/pesan-bimbingan/${evaluatedActiveThreadId}/messages`,
            {
                preserveScroll: true,
                forceFormData: true,
                onSuccess: () => {
                    form.reset('message', 'attachment');
                    setAttachmentName(null);
                    if (fileRef.current) {
                        fileRef.current.value = '';
                    }

                    setTimeout(() => scrollToBottom(), 50);
                },
            },
        );
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
        <DosenLayout
            breadcrumbs={breadcrumbs}
            title="Pesan Bimbingan"
            subtitle="Kelola group chat bimbingan per mahasiswa"
        >
            <Head title="Pesan Bimbingan Dosen" />

            <div
                className={cn(
                    'mx-auto flex h-[calc(100dvh-4rem)] w-full max-w-7xl flex-1 lg:grid lg:h-[calc(100dvh-4rem-3rem)] lg:grid-cols-[340px_1fr]',
                    isMobile ? 'gap-0 px-0 py-0' : 'gap-6 px-4 py-6 md:px-6',
                )}
            >
                {/* Thread List / Side Panel */}
                <Card
                    className={cn(
                        'flex min-h-0 flex-col !gap-0 overflow-hidden !p-0 lg:h-full lg:w-[340px] lg:shrink-0',
                        isMobile && 'rounded-none border-0 shadow-none',
                        mobileView === 'chat' && 'hidden lg:flex',
                        mobileView === 'threads' && 'flex-1 lg:flex-initial',
                    )}
                >
                    <CardHeader
                        className={cn(
                            'shrink-0 space-y-3 bg-muted/20 p-6 pb-4',
                            isMobile && 'px-4 py-4',
                        )}
                    >
                        <div>
                            <CardTitle>Ruang Bimbingan</CardTitle>
                            <CardDescription>
                                Pilih grup mahasiswa untuk mulai berdiskusi
                            </CardDescription>
                        </div>
                        <div className="flex w-full items-center rounded-lg bg-muted p-1 text-sm font-medium">
                            <Link
                                href="/dosen/pesan-bimbingan?tab=aktif"
                                className={cn(
                                    'flex flex-1 items-center justify-center rounded-md px-3 py-1.5 whitespace-nowrap text-muted-foreground transition-all',
                                    tab === 'aktif' &&
                                        'bg-background text-foreground shadow-sm',
                                )}
                            >
                                Aktif
                            </Link>
                            <Link
                                href="/dosen/pesan-bimbingan?tab=arsip"
                                className={cn(
                                    'flex flex-1 items-center justify-center rounded-md px-3 py-1.5 whitespace-nowrap text-muted-foreground transition-all',
                                    tab === 'arsip' &&
                                        'bg-background text-foreground shadow-sm',
                                )}
                            >
                                Arsip
                            </Link>
                        </div>
                        <div className="relative">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    handleSearchChange(event.target.value)
                                }
                                className="pl-9"
                                placeholder="Cari mahasiswa..."
                            />
                        </div>
                    </CardHeader>
                    <Separator />
                    <CardContent className="relative flex-1 overflow-hidden p-0">
                        <ScrollArea className="h-full w-full">
                            <div className="flex flex-col">
                                {visibleThreads.length > 0 ? (
                                    visibleThreads.map((thread) => {
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
                                                            {thread.student}
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
                                                                    : 'bg-muted text-muted-foreground hover:bg-muted/80',
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
                                                            {thread.unread >
                                                                0 && (
                                                                <div className="size-2 rounded-full bg-primary" />
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            </button>
                                        );
                                    })
                                ) : (
                                    <div className="rounded-xl border border-dashed bg-muted/20 p-6 text-center">
                                        <span className="mx-auto mb-3 inline-flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                            <Inbox className="size-5" />
                                        </span>
                                        <p className="text-sm font-medium">
                                            Tidak ada grup yang sesuai
                                        </p>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Coba kata kunci lain atau ubah
                                            filter percakapan.
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
                        'flex min-h-0 flex-1 flex-col !gap-0 overflow-hidden !p-0 lg:h-full',
                        isMobile && 'rounded-none border-0 shadow-none',
                        mobileView === 'threads' && 'hidden lg:flex',
                    )}
                >
                    <CardHeader
                        className={cn(
                            'shrink-0 bg-muted/20 p-6 pb-4',
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
                                                    {activeThread.studentProfile ? (
                                                        <Link
                                                            href={
                                                                activeThread
                                                                    .studentProfile
                                                                    .profileUrl
                                                            }
                                                            className="transition hover:text-primary"
                                                        >
                                                            {
                                                                activeThread
                                                                    .studentProfile
                                                                    .name
                                                            }
                                                        </Link>
                                                    ) : (
                                                        activeThread.student
                                                    )}
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
                                    <CardTitle>Pilih Grup Bimbingan</CardTitle>
                                </div>
                                <CardDescription>
                                    Buka salah satu percakapan untuk melihat
                                    detail chat.
                                </CardDescription>
                            </>
                        )}
                    </CardHeader>
                    <Separator />

                    <CardContent
                        className={cn(
                            'relative flex-1 overflow-hidden',
                            isMobile ? 'pb-3' : 'pb-6',
                        )}
                    >
                        <ScrollArea className="h-full w-full">
                            <div
                                className={cn(
                                    'flex min-h-full flex-col',
                                    isMobile ? 'px-4 py-3' : 'p-4',
                                )}
                            >
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
                                    <div className="mt-10 rounded-xl border border-dashed bg-muted/20 p-8 text-center">
                                        <span className="mx-auto mb-3 inline-flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                            <Users className="size-5" />
                                        </span>
                                        <p className="text-sm font-medium">
                                            Belum ada grup yang dipilih
                                        </p>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Pilih salah satu mahasiswa di panel
                                            kiri untuk mulai berdiskusi.
                                        </p>
                                    </div>
                                )}
                            </div>
                        </ScrollArea>
                    </CardContent>

                    <Separator />

                    {activeThread?.isArchived ? (
                        <div className="shrink-0 p-4 text-center">
                            <p className="text-sm font-medium text-muted-foreground">
                                Sesi bimbingan telah selesai. Percakapan ini
                                diarsipkan dan tidak dapat diperbarui.
                            </p>
                        </div>
                    ) : (
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
                                        form.setData(
                                            'message',
                                            event.target.value,
                                        )
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
                    )}
                </Card>
            </div>
        </DosenLayout>
    );
}
