import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Download,
    Inbox,
    Paperclip,
    Search,
    Send,
    Users,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type ChangeEvent } from 'react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
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
import { Separator } from '@/components/ui/separator';
import DosenLayout from '@/layouts/dosen-layout';
import { playPopSound } from '@/lib/sounds';
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
    isEscalated: boolean;
    isArchived: boolean;
    messages: ThreadMessage[];
};

type PesanBimbinganProps = {
    threads: ThreadItem[];
    tab: 'aktif' | 'arsip';
    flashMessage?: string | null;
};

function initials(name: string) {
    return name
        .split(' ')
        .map((chunk) => chunk[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
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
        // eslint-disable-next-line react-hooks/set-state-in-effect
        setThreadItems(initialThreads);
        setMessagesByThread(
            Object.fromEntries(
                initialThreads.map((thread) => [thread.id, thread.messages]),
            )
        );
        setActiveThreadId(resolveInitialThreadId(initialThreads));
    }, [initialThreads]);

    async function markThreadAsRead(threadId: number) {
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
                            playPopSound();
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

                    setThreadItems((current) =>
                        current.map((t) => {
                            if (t.id !== event.threadId) {
                                return t;
                            }

                            const latestPreview =
                                event.message.message ||
                                event.message.documentName ||
                                t.preview;
                            const isIncomingMessage =
                                event.message.senderUserId !== null &&
                                event.message.senderUserId !== auth.user?.id;
                            const shouldIncrementUnread =
                                isIncomingMessage &&
                                activeThreadIdRef.current !== event.threadId;

                            return {
                                ...t,
                                preview: latestPreview,
                                lastTime: 'baru saja',
                                unread: shouldIncrementUnread
                                    ? t.unread + 1
                                    : t.unread,
                            };
                        }),
                    );

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
                window.Echo.leaveChannel(
                    `private-mentorship.thread.${thread.id}`,
                );
            }
        };
    }, [auth.user?.id, initialThreads]);

    useEffect(() => {
        if (activeThreadId === null) {
            return;
        }
        void markThreadAsRead(activeThreadId);
    }, [activeThreadId]);

    const visibleThreads = useMemo(() => {
        return threadItems
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
                    playPopSound();
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

            <div className="mx-auto flex h-[calc(100vh-8rem)] w-full max-w-7xl flex-1 gap-6 px-4 py-6 md:px-6 lg:h-auto lg:grid lg:grid-cols-[340px_1fr]">

                {/* Thread List / Side Panel */}
                <Card
                    className={cn(
                        'flex min-h-0 flex-col overflow-hidden lg:h-[700px] lg:w-[340px] lg:shrink-0',
                        mobileView === 'chat' && 'hidden lg:flex',
                        mobileView === 'threads' && 'flex-1 lg:flex-initial'
                    )}
                >
                    <CardHeader className="space-y-3 shrink-0">
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
                                    'flex flex-1 items-center justify-center whitespace-nowrap rounded-md px-3 py-1.5 transition-all text-muted-foreground',
                                    tab === 'aktif' &&
                                    'bg-background text-foreground shadow-sm'
                                )}
                            >
                                Aktif
                            </Link>
                            <Link
                                href="/dosen/pesan-bimbingan?tab=arsip"
                                className={cn(
                                    'flex flex-1 items-center justify-center whitespace-nowrap rounded-md px-3 py-1.5 transition-all text-muted-foreground',
                                    tab === 'arsip' &&
                                    'bg-background text-foreground shadow-sm'
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
                    <CardContent className="flex-1 overflow-y-auto flex flex-col gap-2 p-4 pt-0">
                        {visibleThreads.length > 0 ? (
                            visibleThreads.map((thread) => {
                                const threadMessages =
                                    messagesByThread[thread.id] ??
                                    thread.messages;
                                const latestMessage = threadMessages.at(-1);

                                return (
                                    <button
                                        key={thread.id}
                                        type="button"
                                        className={cn(
                                            'w-full rounded-lg border p-3 text-left transition hover:bg-muted/30 shrink-0',
                                            activeThread?.id === thread.id &&
                                            'border-primary/30 bg-muted/40'
                                        )}
                                        onClick={() => selectThread(thread.id)}
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <p className="text-sm font-semibold truncate">
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
                                        <div className="mt-2 flex items-center justify-end">
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
                                    Coba kata kunci lain atau ubah filter
                                    percakapan.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Chat Panel */}
                <Card
                    className={cn(
                        'flex min-h-0 flex-1 flex-col overflow-hidden lg:h-[700px]',
                        mobileView === 'threads' && 'hidden lg:flex'
                    )}
                >
                    <CardHeader className="shrink-0">
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

                    <CardContent className="flex-1 overflow-auto pt-4 relative">
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
                                        message.author === auth.user?.name;

                                    if (
                                        message.type === 'document_event' ||
                                        message.type === 'revision_suggestion'
                                    ) {
                                        const isRevision =
                                            message.type ===
                                            'revision_suggestion';

                                        return (
                                            <div
                                                key={message.id}
                                                className="animate-pop relative overflow-hidden rounded-lg border border-primary/25 bg-background p-3"
                                            >
                                                <div className="pointer-events-none absolute inset-0 bg-primary/10" />
                                                <div className="relative z-10">
                                                    <div className="text-sm font-medium text-primary">
                                                        {isRevision
                                                            ? 'File revisi dari dosen'
                                                            : 'Dokumen baru diunggah'}
                                                    </div>
                                                    <div className="mt-1 text-sm text-primary">
                                                        {message.message}
                                                    </div>
                                                    {message.documentName && (
                                                        <div className="mt-2 rounded border bg-background p-2 text-sm max-w-[200px] truncate sm:max-w-none">
                                                            {message.documentName}
                                                        </div>
                                                    )}
                                                    <div className="mt-2 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                                        <span className="text-xs text-muted-foreground">
                                                            {message.author} -{' '}
                                                            {message.time}
                                                        </span>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            className="h-8 gap-2 w-full sm:w-auto"
                                                            disabled={
                                                                !message.documentUrl
                                                            }
                                                            onClick={() => {
                                                                if (
                                                                    message.documentUrl
                                                                ) {
                                                                    window.open(
                                                                        message.documentUrl,
                                                                        '_blank',
                                                                        'noopener,noreferrer'
                                                                    );
                                                                }
                                                            }}
                                                        >
                                                            <Download className="size-3.5" />
                                                            Unduh
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        );
                                    }

                                    return (
                                        <div
                                            key={message.id}
                                            className={`animate-pop flex ${isMe ? 'justify-end' : ''
                                                }`}
                                        >
                                            {!isMe && (
                                                <Avatar className="mt-0.5 mr-2 size-7 shrink-0">
                                                    <AvatarFallback>
                                                        {initials(
                                                            message.author
                                                        )}
                                                    </AvatarFallback>
                                                </Avatar>
                                            )}
                                            <div
                                                className={`max-w-[85%] sm:max-w-[78%] rounded-2xl border px-3 py-2 text-sm ${isMe
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'bg-background'
                                                    }`}
                                            >
                                                {message.documentName && (
                                                    <div
                                                        className={`mb-2 rounded border p-2 text-xs break-all ${isMe
                                                            ? 'border-primary-foreground/25 bg-primary-foreground/15'
                                                            : 'bg-muted/30'
                                                            }`}
                                                    >
                                                        {message.documentName}
                                                    </div>
                                                )}
                                                {message.message && (
                                                    <div className="break-words">
                                                        {message.message}
                                                    </div>
                                                )}
                                                <div
                                                    className={`mt-1 text-[11px] ${isMe
                                                        ? 'text-primary-foreground/70'
                                                        : 'text-muted-foreground'
                                                        }`}
                                                >
                                                    {message.author} -{' '}
                                                    {message.time}
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                                <div ref={messagesEndRef} className="h-1" />
                            </div>
                        ) : (
                            <div className="rounded-xl border border-dashed bg-muted/20 p-8 text-center mt-10">
                                <span className="mx-auto mb-3 inline-flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                    <Users className="size-5" />
                                </span>
                                <p className="text-sm font-medium">
                                    Belum ada grup yang dipilih
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Pilih salah satu mahasiswa di panel kiri
                                    untuk mulai berdiskusi.
                                </p>
                            </div>
                        )}
                    </CardContent>

                    <Separator />

                    {activeThread?.isArchived ? (
                        <div className="p-4 text-center shrink-0">
                            <p className="text-sm font-medium text-muted-foreground">
                                Sesi bimbingan telah selesai. Percakapan ini
                                diarsipkan dan tidak dapat diperbarui.
                            </p>
                        </div>
                    ) : (
                        <CardFooter className="flex-col items-stretch gap-3 shrink-0 p-4">
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
                                            event.target.value
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
                                <div className="text-xs text-muted-foreground truncate">
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
