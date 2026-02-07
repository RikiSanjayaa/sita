import { Head, useForm, usePage } from '@inertiajs/react';
import { BellDot, Download, MessageSquareText, Paperclip } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import DosenLayout from '@/layouts/dosen-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dosen/dashboard' },
    { title: 'Pesan Bimbingan', href: '/dosen/pesan-bimbingan' },
];

type ThreadMessage = {
    id: number;
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

export default function DosenPesanBimbinganPage() {
    const { threads, flashMessage, auth } = usePage<
        SharedData & PesanBimbinganProps
    >().props;

    const [search, setSearch] = useState('');
    const [activeThreadId, setActiveThreadId] = useState<number | null>(
        threads[0]?.id ?? null,
    );
    const [messagesByThread, setMessagesByThread] = useState<
        Record<number, ThreadMessage[]>
    >(() =>
        Object.fromEntries(
            threads.map((thread) => [thread.id, thread.messages]),
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
        if (typeof window === 'undefined' || !window.Echo) {
            return;
        }

        const channels = threads.map((thread) => {
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
                },
            );
        });

        return () => {
            for (const [index, thread] of threads.entries()) {
                channels[index]?.stopListening('.chat.message.created');
                window.Echo.leaveChannel(
                    `private-mentorship.thread.${thread.id}`,
                );
            }
        };
    }, [threads]);

    const filteredThreads = useMemo(
        () =>
            threads.filter((thread) =>
                thread.student.toLowerCase().includes(search.toLowerCase()),
            ),
        [threads, search],
    );

    const activeThread =
        filteredThreads.find((thread) => thread.id === activeThreadId) ??
        filteredThreads[0] ??
        null;

    const activeMessages =
        activeThread === null ? [] : (messagesByThread[activeThread.id] ?? []);

    function submitMessage() {
        if (!activeThread || form.processing) {
            return;
        }

        const hasMessage = form.data.message.trim() !== '';
        const hasAttachment = form.data.attachment !== null;
        if (!hasMessage && !hasAttachment) {
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
            subtitle="Group chat per mahasiswa bimbingan"
        >
            <Head title="Pesan Bimbingan Dosen" />

            <div className="mx-auto grid w-full max-w-7xl flex-1 gap-6 px-4 py-6 md:px-6 lg:grid-cols-[340px_1fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>Ruang Bimbingan</CardTitle>
                        <CardDescription>
                            Satu grup untuk tiap mahasiswa
                        </CardDescription>
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Cari mahasiswa..."
                        />
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {filteredThreads.map((thread) => {
                            const threadMessages =
                                messagesByThread[thread.id] ?? thread.messages;
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
                                    onClick={() => setActiveThreadId(thread.id)}
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <p className="text-sm font-semibold">
                                            {thread.student}
                                        </p>
                                        <Badge variant="outline">
                                            {thread.lastTime}
                                        </Badge>
                                    </div>
                                    <p className="mt-1 line-clamp-2 text-xs text-muted-foreground">
                                        {latestMessage?.message ??
                                            thread.preview}
                                    </p>
                                    <div className="mt-2 flex items-center gap-2">
                                        {thread.unread > 0 && (
                                            <Badge className="bg-primary text-primary-foreground">
                                                {thread.unread} baru
                                            </Badge>
                                        )}
                                        <Badge variant="secondary">
                                            Group Bimbingan
                                        </Badge>
                                    </div>
                                </button>
                            );
                        })}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{activeThread?.student ?? '-'}</CardTitle>
                        <CardDescription>
                            Aktivitas chat dan event dokumen terbaru
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {flashMessage && (
                            <Alert>
                                <AlertTitle>Berhasil</AlertTitle>
                                <AlertDescription>
                                    {flashMessage}
                                </AlertDescription>
                            </Alert>
                        )}

                        {activeMessages.map((message) => {
                            if (
                                message.type === 'document_event' ||
                                message.type === 'revision_suggestion'
                            ) {
                                const isRevision =
                                    message.type === 'revision_suggestion';

                                return (
                                    <div
                                        key={message.id}
                                        className="rounded-lg border border-primary/25 bg-primary/10 p-4"
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <div className="inline-flex items-center gap-2 text-sm text-primary">
                                                <BellDot className="size-4" />
                                                {isRevision
                                                    ? 'File Revisi Dosen'
                                                    : 'Event Upload Dokumen'}
                                            </div>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                disabled={!message.documentUrl}
                                                onClick={() => {
                                                    if (message.documentUrl) {
                                                        window.open(
                                                            message.documentUrl,
                                                            '_blank',
                                                            'noopener,noreferrer',
                                                        );
                                                    }
                                                }}
                                            >
                                                <Download className="size-4" />
                                                Unduh
                                            </Button>
                                        </div>
                                        <p className="mt-2 text-sm">
                                            {message.documentName ??
                                                message.message}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {message.author} - {message.time}
                                        </p>
                                    </div>
                                );
                            }

                            const isMe = message.author === auth.user?.name;

                            return (
                                <div
                                    key={message.id}
                                    className={`rounded-lg border p-4 ${
                                        isMe
                                            ? 'border-primary/25 bg-primary/10'
                                            : 'bg-background'
                                    }`}
                                >
                                    {message.documentName && (
                                        <div className="mb-2 rounded border bg-muted/20 p-2 text-xs">
                                            {message.documentName}
                                        </div>
                                    )}
                                    <p className="text-sm">
                                        {message.author}: "{message.message}"
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {message.time}
                                    </p>
                                </div>
                            );
                        })}

                        <input
                            ref={fileRef}
                            type="file"
                            accept=".pdf,.doc,.docx"
                            className="hidden"
                            onChange={(event) => {
                                const nextFile =
                                    event.currentTarget.files?.[0] ?? null;
                                form.setData('attachment', nextFile);
                                setAttachmentName(nextFile?.name ?? null);
                            }}
                        />

                        <div className="flex items-center gap-2 pt-2">
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => fileRef.current?.click()}
                            >
                                <Paperclip className="size-4" />
                                Lampirkan File
                            </Button>
                            <Input
                                value={form.data.message}
                                onChange={(event) =>
                                    form.setData('message', event.target.value)
                                }
                                placeholder="Tulis balasan di grup..."
                            />
                            <Button
                                size="sm"
                                className="bg-primary text-primary-foreground hover:bg-primary/90"
                                disabled={
                                    !activeThread ||
                                    form.processing ||
                                    (form.data.message.trim() === '' &&
                                        form.data.attachment === null)
                                }
                                onClick={submitMessage}
                            >
                                <MessageSquareText className="size-4" />
                                Kirim
                            </Button>
                        </div>

                        {attachmentName && (
                            <p className="text-xs text-muted-foreground">
                                Lampiran: {attachmentName}
                            </p>
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
                    </CardContent>
                </Card>
            </div>
        </DosenLayout>
    );
}
