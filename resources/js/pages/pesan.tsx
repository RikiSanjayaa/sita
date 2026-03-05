import { Head, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Inbox, Paperclip, Send, Users } from 'lucide-react';
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
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { dashboard, pesan } from '@/routes';
import { type BreadcrumbItem, type SharedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Pesan', href: pesan().url },
];

type ChatMessage = {
    id: number;
    senderUserId: number | null;
    author: string;
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
    messages: ChatMessage[];
    preview: string;
    lastTime: string;
};

type PesanPageProps = {
    hasDosbing: boolean;
    threads: ThreadItem[];
    flashMessage?: string | null;
};

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
        flashMessage,
        auth,
    } = usePage<SharedData & PesanPageProps>().props;

    const [mobileView, setMobileView] = useState<'threads' | 'chat'>('threads');
    const [activeThreadId, setActiveThreadId] = useState<number | null>(
        resolveInitialThreadId(initialThreads),
    );
    const [messagesByThread, setMessagesByThread] = useState<
        Record<number, ChatMessage[]>
    >(() => Object.fromEntries(initialThreads.map((t) => [t.id, t.messages])));
    const [attachmentName, setAttachmentName] = useState<string | null>(null);
    const fileRef = useRef<HTMLInputElement | null>(null);
    const messagesEndRef = useRef<HTMLDivElement | null>(null);

    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    const form = useForm<{ message: string; attachment: File | null }>({
        message: '',
        attachment: null,
    });

    useEffect(() => {
        /* eslint-disable react-hooks/set-state-in-effect */
        setMessagesByThread(
            Object.fromEntries(initialThreads.map((t) => [t.id, t.messages])),
        );
        /* eslint-enable react-hooks/set-state-in-effect */
    }, [initialThreads]);

    useEffect(() => {
        // @ts-expect-error - injected globally for app-sidebar-header to read
        window.activeMentorshipThreadId = activeThreadId;
        return () => {
            // @ts-expect-error - injected globally
            window.activeMentorshipThreadId = null;
        };
    }, [activeThreadId]);

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
                            activeThreadId === event.threadId &&
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
                },
            );
        });

        return () => {
            for (const [index, thread] of initialThreads.entries()) {
                channels[index]?.stopListening('.chat.message.created');
                window.Echo.leave(`mentorship.thread.${thread.id}`);
            }
        };
    }, [auth.user?.id, initialThreads, activeThreadId]);

    const activeThread = useMemo(
        () => initialThreads.find((t) => t.id === activeThreadId) ?? null,
        [activeThreadId, initialThreads],
    );

    const activeMessages = useMemo(
        () => (activeThread ? (messagesByThread[activeThread.id] ?? []) : []),
        [activeThread, messagesByThread],
    );

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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pesan" />

            <div className="mx-auto flex h-[calc(100vh-4rem)] w-full max-w-7xl flex-1 gap-6 px-4 py-6 md:px-6 lg:grid lg:h-[calc(100vh-4rem-3rem)] lg:grid-cols-[340px_1fr]">
                {/* Thread List */}
                <Card
                    className={cn(
                        'flex min-h-0 flex-col !gap-0 overflow-hidden !p-0 lg:h-full lg:w-[340px] lg:shrink-0',
                        mobileView === 'chat' && 'hidden lg:flex',
                        mobileView === 'threads' && 'flex-1 lg:flex-initial',
                    )}
                >
                    <CardHeader className="shrink-0 p-6 pb-4">
                        <CardTitle>Pesan</CardTitle>
                        <CardDescription>
                            Thread bimbingan dan sempro Anda
                        </CardDescription>
                    </CardHeader>
                    <Separator />
                    <CardContent className="relative flex-1 overflow-hidden p-0">
                        <ScrollArea className="h-full w-full">
                            <div className="flex flex-col gap-2 p-4 pt-0">
                                {initialThreads.length > 0 ? (
                                    initialThreads.map((thread) => {
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
                                                    'w-full shrink-0 rounded-lg border p-3 text-left transition hover:bg-muted/30',
                                                    activeThread?.id ===
                                                        thread.id &&
                                                        'border-primary/30 bg-muted/40',
                                                )}
                                                onClick={() =>
                                                    selectThread(thread.id)
                                                }
                                            >
                                                <div className="flex items-center justify-between gap-2">
                                                    <p className="truncate text-sm font-semibold">
                                                        {thread.name}
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
                                                <div className="mt-2 flex items-center gap-1">
                                                    <Badge
                                                        variant={
                                                            thread.threadType ===
                                                            'pembimbing'
                                                                ? 'secondary'
                                                                : 'outline'
                                                        }
                                                        className={
                                                            thread.threadType !==
                                                            'pembimbing'
                                                                ? 'border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-300'
                                                                : ''
                                                        }
                                                    >
                                                        {thread.threadLabel}
                                                    </Badge>
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
                        'flex min-h-0 flex-1 flex-col !gap-0 overflow-hidden !p-0 lg:h-full',
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
                                        {activeThread.name}
                                    </CardTitle>
                                    <Badge
                                        variant={
                                            activeThread.threadType ===
                                            'pembimbing'
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                        className={
                                            activeThread.threadType !==
                                            'pembimbing'
                                                ? 'border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-300'
                                                : ''
                                        }
                                    >
                                        {activeThread.threadLabel}
                                    </Badge>
                                </div>
                                <CardDescription className="inline-flex items-center gap-1">
                                    <Users className="size-3.5 shrink-0" />
                                    <span className="truncate">
                                        {activeThread.members.join(', ')}
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
                                    <CardTitle>Pilih Thread</CardTitle>
                                </div>
                                <CardDescription>
                                    Buka salah satu percakapan untuk melihat
                                    chat.
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
                                    <div className="grid gap-3">
                                        {activeMessages.map((message) => {
                                            const isMe =
                                                message.senderUserId ===
                                                auth.user?.id;
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

                    <CardFooter className="shrink-0 flex-col items-stretch gap-3 p-6 pt-4">
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
