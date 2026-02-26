import { Head, useForm, usePage } from '@inertiajs/react';
import { Download, Paperclip, Send, Users } from 'lucide-react';
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
import AppLayout from '@/layouts/app-layout';
import { dashboard, pesan } from '@/routes';
import { type BreadcrumbItem, type SharedData } from '@/types';

type ChatMessage = {
    id: number;
    author: string;
    message: string;
    time: string;
    type: string;
    documentName: string | null;
    documentUrl: string | null;
};

type ChatThreadPayload = {
    id: number;
    name: string;
    members: string[];
    messages: ChatMessage[];
};

type PesanPageProps = {
    thread: ChatThreadPayload;
    flashMessage?: string | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Pesan', href: pesan().url },
];

function initials(name: string) {
    return name
        .split(' ')
        .map((chunk) => chunk[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

export default function PesanPage() {
    const { thread, flashMessage, auth } = usePage<
        SharedData & PesanPageProps
    >().props;

    const [messages, setMessages] = useState<ChatMessage[]>(thread.messages);
    const [attachmentName, setAttachmentName] = useState<string | null>(null);
    const fileRef = useRef<HTMLInputElement | null>(null);

    const form = useForm<{
        message: string;
        attachment: File | null;
    }>({
        message: '',
        attachment: null,
    });

    const myName = auth.user?.name ?? '';

    useEffect(() => {
        if (typeof window === 'undefined' || !window.Echo) {
            return;
        }

        const channelName = `mentorship.thread.${thread.id}`;
        const channel = window.Echo.private(channelName).listen(
            '.chat.message.created',
            (event: { threadId: number; message: ChatMessage }) => {
                if (event.threadId !== thread.id) {
                    return;
                }

                setMessages((current) => {
                    if (
                        current.some(
                            (message) => message.id === event.message.id,
                        )
                    ) {
                        return current;
                    }

                    return [...current, event.message];
                });
            },
        );

        return () => {
            channel.stopListening('.chat.message.created');
            window.Echo.leaveChannel(`private-${channelName}`);
        };
    }, [thread.id]);

    const canSend = useMemo(
        () =>
            !form.processing &&
            (form.data.message.trim() !== '' || form.data.attachment !== null),
        [form.data.attachment, form.data.message, form.processing],
    );

    function pickAttachment(event: ChangeEvent<HTMLInputElement>) {
        const nextFile = event.target.files?.[0] ?? null;
        form.setData('attachment', nextFile);
        setAttachmentName(nextFile?.name ?? null);
    }

    function sendMessage() {
        if (!canSend) {
            return;
        }

        form.transform((data) => ({
            ...data,
            message: data.message.trim(),
        }));

        form.post('/mahasiswa/pesan/messages', {
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
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Pesan"
            subtitle="Group chat bimbingan bersama dosen pembimbing"
        >
            <Head title="Pesan" />

            <div className="mx-auto grid w-full max-w-7xl flex-1 gap-6 px-4 py-6 md:px-6">
                <Card className="flex min-h-[620px] flex-col">
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <CardTitle>{thread.name}</CardTitle>
                            <Badge variant="secondary">Group Bimbingan</Badge>
                        </div>
                        <CardDescription className="inline-flex items-center gap-1">
                            <Users className="size-3.5" />
                            {thread.members.join(' - ')}
                        </CardDescription>
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

                        <div className="grid gap-3">
                            {messages.map((message) => {
                                const isMe = message.author === myName;

                                if (
                                    message.type === 'document_event' ||
                                    message.type === 'revision_suggestion'
                                ) {
                                    const isRevision =
                                        message.type === 'revision_suggestion';

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
                                                    {initials(message.author)}
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
                            >
                                <Paperclip className="size-4" />
                            </Button>
                            <Input
                                value={form.data.message}
                                onChange={(event) =>
                                    form.setData('message', event.target.value)
                                }
                                placeholder="Tulis pesan..."
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
        </AppLayout>
    );
}
