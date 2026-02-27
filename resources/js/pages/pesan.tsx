import { Head, useForm, usePage } from '@inertiajs/react';
import { Paperclip, Send, Users } from 'lucide-react';
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

export default function PesanPage() {
    const { thread, flashMessage, auth } = usePage<
        SharedData & PesanPageProps
    >().props;

    const [messages, setMessages] = useState<ChatMessage[]>(thread.messages);
    const [attachmentName, setAttachmentName] = useState<string | null>(null);
    const fileRef = useRef<HTMLInputElement | null>(null);
    const messagesEndRef = useRef<HTMLDivElement | null>(null);

    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    // Scroll automatically on initial load or when messages change
    useEffect(() => {
        scrollToBottom();
    }, [messages.length]);

    // Synchronize local state when server finishes processing messages via inertial response
    /* eslint-disable react-hooks/set-state-in-effect */
    useEffect(() => {
        setMessages(thread.messages);
    }, [thread.messages]);
    /* eslint-enable react-hooks/set-state-in-effect */
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
    }, [thread.id, myName]);

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

                scrollToBottom();
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

            <div className="mx-auto box-border flex min-h-0 w-full max-w-7xl flex-1 flex-col overflow-hidden px-4 py-6 md:px-6">
                <Card className="flex min-h-0 flex-1 flex-col !gap-0 overflow-hidden !p-0">
                    <CardHeader className="p-6 pb-4">
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

                                <div className="grid gap-3">
                                    {messages.map((message) => {
                                        const isMe = message.author === myName;
                                        return (
                                            <ChatBubble
                                                key={message.id}
                                                message={message}
                                                isMe={isMe}
                                            />
                                        );
                                    })}

                                    <div ref={messagesEndRef} className="h-1" />
                                </div>
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
