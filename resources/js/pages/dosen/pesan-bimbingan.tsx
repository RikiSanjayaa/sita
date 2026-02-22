import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Download, Inbox, Paperclip, Search, Send, Users } from 'lucide-react';
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
    messages: ThreadMessage[];
};

type PesanBimbinganProps = {
    threads: ThreadItem[];
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

export default function DosenPesanBimbinganPage() {
    const {
        threads: initialThreads,
        flashMessage,
        auth,
    } = usePage<SharedData & PesanBimbinganProps>().props;

    const [search, setSearch] = useState('');
    const [threadItems, setThreadItems] =
        useState<ThreadItem[]>(initialThreads);
    const [activeThreadId, setActiveThreadId] = useState<number | null>(
        initialThreads[0]?.id ?? null,
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
    }, []);

    useEffect(() => {
        setThreadItems(initialThreads);
        setMessagesByThread(
            Object.fromEntries(
                initialThreads.map((thread) => [thread.id, thread.messages]),
            ),
        );
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
                                (message) => message.id === event.message.id,
                            )
                        ) {
                            return current;
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
                        current.map((thread) => {
                            if (thread.id !== event.threadId) {
                                return thread;
                            }

                            const latestPreview =
                                event.message.message ||
                                event.message.documentName ||
                                thread.preview;
                            const isIncomingMessage =
                                event.message.senderUserId !== null &&
                                event.message.senderUserId !== auth.user?.id;
                            const shouldIncrementUnread =
                                isIncomingMessage &&
                                activeThreadId !== event.threadId;

                            return {
                                ...thread,
                                preview: latestPreview,
                                lastTime: 'baru saja',
                                unread: shouldIncrementUnread
                                    ? thread.unread + 1
                                    : thread.unread,
                            };
                        }),
                    );

                    if (
                        event.threadId === activeThreadId &&
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
    }, [activeThreadId, auth.user?.id, initialThreads]);

    useEffect(() => {
        if (activeThreadId === null) {
            return;
        }

        setThreadItems((current) =>
            current.map((thread) =>
                thread.id === activeThreadId
                    ? { ...thread, unread: 0 }
                    : thread,
            ),
        );
        void markThreadAsRead(activeThreadId);
    }, [activeThreadId]);

    const visibleThreads = useMemo(() => {
        return threadItems.filter((thread) => {
            return thread.student
                .toLowerCase()
                .includes(search.trim().toLowerCase());
        });
    }, [search, threadItems]);

    useEffect(() => {
        if (visibleThreads.length === 0) {
            setActiveThreadId(null);

            return;
        }

        if (
            activeThreadId === null ||
            !visibleThreads.some((thread) => thread.id === activeThreadId)
        ) {
            setActiveThreadId(visibleThreads[0].id);
        }
    }, [activeThreadId, visibleThreads]);

    const activeThread =
        visibleThreads.find((thread) => thread.id === activeThreadId) ?? null;

    const activeMessages =
        activeThread === null ? [] : (messagesByThread[activeThread.id] ?? []);

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

    function pickAttachment(event: ChangeEvent<HTMLInputElement>) {
        const nextFile = event.target.files?.[0] ?? null;
        form.setData('attachment', nextFile);
        setAttachmentName(nextFile?.name ?? null);
    }

    function sendMessage() {
        if (!canSend || activeThread === null) {
            return;
        }

        form.transform((data) => ({
            ...data,
            message: data.message.trim(),
        }));

        form.post(`/dosen/pesan-bimbingan/${activeThread.id}/messages`, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                form.reset('message', 'attachment');
                setAttachmentName(null);
                if (fileRef.current) {
                    fileRef.current.value = '';
                }
            },
        });
    }

    return (
        <DosenLayout
            breadcrumbs={breadcrumbs}
            title="Pesan Bimbingan"
            subtitle="Kelola group chat bimbingan per mahasiswa"
        >
            <Head title="Pesan Bimbingan Dosen" />

            <div className="mx-auto grid w-full max-w-7xl flex-1 gap-6 px-4 py-6 md:px-6 lg:grid-cols-[340px_1fr]">
                <Card>
                    <CardHeader className="space-y-3">
                        <div>
                            <CardTitle>Ruang Bimbingan</CardTitle>
                            <CardDescription>
                                Pilih grup mahasiswa untuk mulai berdiskusi
                            </CardDescription>
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
                    <CardContent className="grid gap-2">
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
                                            'w-full rounded-lg border p-3 text-left transition hover:bg-muted/30',
                                            activeThread?.id === thread.id &&
                                                'border-primary/30 bg-muted/40',
                                        )}
                                        onClick={() =>
                                            setActiveThreadId(thread.id)
                                        }
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <p className="text-sm font-semibold">
                                                {thread.student}
                                            </p>
                                            <span className="text-xs text-muted-foreground">
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

                <Card className="flex min-h-[620px] flex-col">
                    <CardHeader>
                        {activeThread ? (
                            <>
                                <div className="flex items-center gap-2">
                                    <CardTitle>
                                        {activeThread.student}
                                    </CardTitle>
                                </div>
                                <CardDescription className="inline-flex items-center gap-1">
                                    <Users className="size-3.5" />
                                    {activeThread.student} - {auth.user?.name}
                                </CardDescription>
                            </>
                        ) : (
                            <>
                                <CardTitle>Pilih Grup Bimbingan</CardTitle>
                                <CardDescription>
                                    Buka salah satu percakapan untuk melihat
                                    detail chat.
                                </CardDescription>
                            </>
                        )}
                    </CardHeader>
                    <Separator />

                    <CardContent className="flex-1 overflow-auto pt-4">
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
                                                className="rounded-lg border border-primary/25 bg-primary/10 p-3"
                                            >
                                                <div className="text-sm font-medium text-primary">
                                                    {isRevision
                                                        ? 'File revisi dari dosen'
                                                        : 'Dokumen baru diunggah'}
                                                </div>
                                                <div className="mt-1 text-sm text-primary">
                                                    {message.message}
                                                </div>
                                                {message.documentName && (
                                                    <div className="mt-2 rounded border bg-background p-2 text-sm">
                                                        {message.documentName}
                                                    </div>
                                                )}
                                                <div className="mt-2 flex items-center justify-between gap-2">
                                                    <span className="text-xs text-muted-foreground">
                                                        {message.author} -{' '}
                                                        {message.time}
                                                    </span>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        className="h-8 gap-2"
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
                                                                    'noopener,noreferrer',
                                                                );
                                                            }
                                                        }}
                                                    >
                                                        <Download className="size-3.5" />
                                                        Unduh
                                                    </Button>
                                                </div>
                                            </div>
                                        );
                                    }

                                    return (
                                        <div
                                            key={message.id}
                                            className={`flex ${isMe ? 'justify-end' : ''}`}
                                        >
                                            {!isMe && (
                                                <Avatar className="mt-0.5 mr-2 size-7">
                                                    <AvatarFallback>
                                                        {initials(
                                                            message.author,
                                                        )}
                                                    </AvatarFallback>
                                                </Avatar>
                                            )}
                                            <div
                                                className={`max-w-[78%] rounded-2xl border px-3 py-2 text-sm ${
                                                    isMe
                                                        ? 'bg-primary text-primary-foreground'
                                                        : 'bg-background'
                                                }`}
                                            >
                                                {message.documentName && (
                                                    <div
                                                        className={`mb-2 rounded border p-2 text-xs ${
                                                            isMe
                                                                ? 'border-primary-foreground/25 bg-primary-foreground/15'
                                                                : 'bg-muted/30'
                                                        }`}
                                                    >
                                                        {message.documentName}
                                                    </div>
                                                )}
                                                {message.message && (
                                                    <div>{message.message}</div>
                                                )}
                                                <div
                                                    className={`mt-1 text-[11px] ${
                                                        isMe
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
                            </div>
                        ) : (
                            <div className="rounded-xl border border-dashed bg-muted/20 p-8 text-center">
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

                    <CardFooter className="flex-col items-stretch gap-3">
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
                            <div className="text-xs text-muted-foreground">
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
        </DosenLayout>
    );
}
