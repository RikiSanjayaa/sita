import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Inbox, Paperclip, Search, Send, Users } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type ChangeEvent } from 'react';

import { ChatBubble } from '@/components/chat-bubble';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import DosenLayout from '@/layouts/dosen-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dosen/dashboard' },
    { title: 'Pesan Bimbingan', href: '/dosen/pesan-bimbingan' },
];

type ThreadMessage = {
    id: number;
    senderUserId: number | null;
    author: string;
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

type PesanBimbinganProps = {
    threads: ThreadItem[];
    tab: 'aktif' | 'arsip';
    flashMessage?: string | null;
};

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

    const [mobileView, setMobileView] = useState<'threads' | 'chat'>('threads');
    const [search, setSearch] = useState('');
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

    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    const activeThreadIdRef = useRef(activeThreadId);
    useEffect(() => {
        activeThreadIdRef.current = activeThreadId;
    }, [activeThreadId]);

    const form = useForm<{
        message: string;
        attachment: File | null;
    }>({
        message: '',
        attachment: null,
    });

    useEffect(() => {
        router.reload({
            only: ['threads'],
        });
    }, [tab]);

    useEffect(() => {
        // Wait until initialThreads are swapped out during tab change
        /* eslint-disable react-hooks/set-state-in-effect */
        setThreadItems(initialThreads);
        setMessagesByThread(
            Object.fromEntries(
                initialThreads.map((thread) => [thread.id, thread.messages]),
            ),
        );
        setActiveThreadId(resolveInitialThreadId(initialThreads));
        /* eslint-enable react-hooks/set-state-in-effect */
    }, [initialThreads]);

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
                                (m) => m.id === event.message.id,
                            )
                        ) {
                            return current;
                        }

                        if (
                            activeThreadIdRef.current === event.threadId &&
                            event.message.senderUserId !== auth.user?.id
                        ) {
                            // small delay for smooth scrolling on receive
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
                            activeThreadIdRef.current !== event.threadId;

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
                        event.threadId === activeThreadIdRef.current &&
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
    }, [auth.user?.id, initialThreads]);

    useEffect(() => {
        // @ts-expect-error - injected globally for app-sidebar-header to read
        window.activeMentorshipThreadId = activeThreadId;

        if (activeThreadId === null) {
            return;
        }
        void markThreadAsRead(activeThreadId);

        return () => {
            // @ts-expect-error - injected globally
            window.activeMentorshipThreadId = null;
        };
    }, [activeThreadId]);

    const visibleThreads = useMemo(() => {
        return [...threadItems]
            .sort((a, b) => {
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
            })
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
    }, [search, threadItems, activeThreadId]);

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

    useEffect(() => {
        if (evaluatedActiveThreadId === null) return;
        /* eslint-disable-next-line react-hooks/set-state-in-effect */
        void markThreadAsRead(evaluatedActiveThreadId);
    }, [evaluatedActiveThreadId]);

    const activeThread =
        visibleThreads.find((t) => t.id === evaluatedActiveThreadId) ?? null;

    const activeMessages =
        activeThread === null ? [] : (messagesByThread[activeThread.id] ?? []);

    useEffect(() => {
        scrollToBottom();
    }, [activeMessages.length, activeThreadId]);

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
        setMobileView('chat');
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

    return (
        <DosenLayout
            breadcrumbs={breadcrumbs}
            title="Pesan Bimbingan"
            subtitle="Kelola group chat bimbingan per mahasiswa"
        >
            <Head title="Pesan Bimbingan Dosen" />

            <div className="mx-auto flex h-[calc(100vh-4rem)] w-full max-w-7xl flex-1 gap-6 px-4 py-6 md:px-6 lg:grid lg:h-[calc(100vh-4rem-3rem)] lg:grid-cols-[340px_1fr]">
                {/* Thread List / Side Panel */}
                <Card
                    className={cn(
                        'flex min-h-0 flex-col !gap-0 overflow-hidden !p-0 shadow-sm lg:h-full lg:w-[340px] lg:shrink-0',
                        mobileView === 'chat' && 'hidden lg:flex',
                        mobileView === 'threads' && 'flex-1 lg:flex-initial',
                    )}
                >
                    <CardHeader className="shrink-0 space-y-3 p-6 pb-4">
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
                                    setSearch(event.target.value)
                                }
                                className="pl-9"
                                placeholder="Cari mahasiswa..."
                            />
                        </div>
                    </CardHeader>
                    <Separator />
                    <CardContent className="relative flex-1 overflow-hidden p-0">
                        <ScrollArea className="h-full w-full">
                            <div className="flex flex-col gap-2 p-4">
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
                                                    'w-full shrink-0 rounded-xl border p-3.5 text-left transition-all hover:bg-muted/50',
                                                    activeThread?.id ===
                                                        thread.id &&
                                                        'border-primary/40 bg-primary/5 shadow-sm ring-1 ring-primary/20',
                                                )}
                                                onClick={() =>
                                                    selectThread(thread.id)
                                                }
                                            >
                                                <div className="flex items-center justify-between gap-2">
                                                    <p className="truncate text-sm font-semibold">
                                                        {thread.student}
                                                    </p>
                                                    <span className="shrink-0 text-xs text-muted-foreground">
                                                        {thread.lastTime}
                                                    </span>
                                                </div>
                                                <p className="mt-1 line-clamp-2 text-xs text-muted-foreground">
                                                    {latestMessage?.message ||
                                                        latestMessage?.documentName ||
                                                        thread.preview}
                                                </p>
                                                <div className="mt-2 flex items-center justify-between gap-1">
                                                    <Badge
                                                        variant="soft"
                                                        className={cn(
                                                            'px-2 py-0.5 text-[10px] font-medium',
                                                            thread.threadType ===
                                                                'pembimbing'
                                                                ? 'bg-primary/10 text-primary hover:bg-primary/20'
                                                                : 'bg-amber-600/10 text-amber-600 hover:bg-amber-600/20',
                                                        )}
                                                    >
                                                        {thread.threadLabel}
                                                    </Badge>
                                                    {thread.unread > 0 && (
                                                        <Badge className="bg-primary text-primary-foreground">
                                                            {thread.unread} baru
                                                        </Badge>
                                                    )}
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
                        'flex min-h-0 flex-1 flex-col !gap-0 overflow-hidden !p-0 shadow-sm lg:h-full',
                        mobileView === 'threads' && 'hidden lg:flex',
                    )}
                >
                    <CardHeader className="shrink-0 p-6 pb-4">
                        {activeThread ? (
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
                                    <CardTitle className="truncate">
                                        {activeThread.student}
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
                                <CardDescription className="inline-flex items-center gap-1">
                                    <Users className="size-3.5 shrink-0" />
                                    <span className="truncate">
                                        {activeThread.student} -{' '}
                                        {auth.user?.name}
                                    </span>
                                </CardDescription>
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

                    <CardContent className="relative flex-1 overflow-hidden p-0">
                        <ScrollArea className="h-full w-full">
                            <div className="flex min-h-full flex-col p-4">
                                {flashMessage && (
                                    <Alert className="mb-3">
                                        <AlertTitle>Info</AlertTitle>
                                        <AlertDescription>
                                            {flashMessage}
                                        </AlertDescription>
                                    </Alert>
                                )}

                                {activeThread ? (
                                    <div className="grid gap-3">
                                        {activeMessages.map((message) => {
                                            const isMe =
                                                message.author ===
                                                auth.user?.name;
                                            return (
                                                <ChatBubble
                                                    key={message.id}
                                                    message={message}
                                                    isMe={isMe}
                                                />
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
                        <CardFooter className="shrink-0 flex-col items-stretch gap-3 p-6 pt-4">
                            <input
                                ref={fileRef}
                                type="file"
                                accept=".pdf,.doc,.docx"
                                className="hidden"
                                onChange={pickAttachment}
                            />
                            <div className="flex items-center gap-3">
                                <Button
                                    type="button"
                                    variant="soft"
                                    size="icon"
                                    className="size-10 shrink-0 rounded-full bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground"
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
                                    className="h-10 rounded-full bg-primary px-6 font-semibold text-primary-foreground hover:bg-primary/90"
                                >
                                    <span className="mr-2 hidden sm:inline-block">
                                        Kirim
                                    </span>
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
