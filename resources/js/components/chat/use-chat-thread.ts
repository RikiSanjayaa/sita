import { useForm } from '@inertiajs/react';
import {
    useEffect,
    useMemo,
    useState,
    type ChangeEvent,
    type Dispatch,
    type SetStateAction,
} from 'react';

import { type ChatMessage, type ChatThread } from './types';
import {
    mergeMessages,
    resolveInitialThreadId,
    sortThreads,
    syncThreadSearchParam,
} from './utils';

type ChatThreadOptions<T extends ChatThread> = {
    /** URL params dropped from the address bar whenever the thread changes. */
    clearParams?: readonly string[];
    initialThreads: T[];
    /** Called after a message was sent successfully. */
    onSent?: () => void;
    /**
     * Endpoint marking a thread as read. When set, threads are expected to
     * carry an `unread` counter that is maintained here.
     */
    readUrl?: (threadId: number) => string;
    /**
     * Threads shown in the list, in display order, given the current threads
     * and the active thread id. Holds the page's mode/search/filter rules.
     */
    selectVisible: (threads: T[], activeThreadId: number | null) => T[];
    /** Endpoint for posting a message to a thread. */
    sendUrl: (threadId: number) => string;
    userId?: number | null;
};

export type ChatComposerState = {
    attachmentName: string | null;
    canSend: boolean;
    errors: { attachment?: string; message?: string };
    /** Changes after each send so the file input remounts empty. */
    inputKey: number;
    message: string;
    pickAttachment: (event: ChangeEvent<HTMLInputElement>) => void;
    send: () => void;
    setMessage: (value: string) => void;
};

type ChatThreadState<T extends ChatThread> = {
    activeMessages: ChatMessage[];
    activeThread: T | null;
    /** Active thread id after falling back to the first visible thread. */
    activeThreadId: number | null;
    composer: ChatComposerState;
    markThreadAsRead: (threadId: number) => Promise<void>;
    messagesFor: (thread: T) => ChatMessage[];
    requestedThreadId: number | null;
    /** Requests a thread; resolves to a visible one if it is filtered out. */
    setActiveThreadId: Dispatch<SetStateAction<number | null>>;
    threadItems: T[];
    visibleThreads: T[];
};

type WithUnread = { unread: number };

/**
 * Shared thread state for the mahasiswa and dosen chat pages: live updates
 * over Echo, active thread resolution, message sending (with attachment) and
 * read tracking.
 */
export function useChatThread<T extends ChatThread>({
    clearParams,
    initialThreads,
    onSent,
    readUrl,
    selectVisible,
    sendUrl,
    userId,
}: ChatThreadOptions<T>): ChatThreadState<T> {
    const [threadItems, setThreadItems] = useState<T[]>(() =>
        sortThreads(initialThreads),
    );
    const [requestedThreadId, setActiveThreadId] = useState<number | null>(() =>
        resolveInitialThreadId(initialThreads),
    );
    const [messagesByThread, setMessagesByThread] = useState<
        Record<number, ChatMessage[]>
    >({});
    const [attachmentName, setAttachmentName] = useState<string | null>(null);
    const [inputKey, setInputKey] = useState(0);

    const form = useForm<{ message: string; attachment: File | null }>({
        message: '',
        attachment: null,
    });

    const visibleThreads = useMemo(
        () => selectVisible(threadItems, requestedThreadId),
        [requestedThreadId, selectVisible, threadItems],
    );

    const activeThreadId = useMemo(() => {
        if (visibleThreads.some((thread) => thread.id === requestedThreadId)) {
            return requestedThreadId;
        }

        return visibleThreads[0]?.id ?? null;
    }, [requestedThreadId, visibleThreads]);

    const activeThread =
        visibleThreads.find((thread) => thread.id === activeThreadId) ?? null;

    const activeMessages = useMemo(
        () =>
            activeThread === null
                ? []
                : mergeMessages(
                      activeThread.messages,
                      messagesByThread[activeThread.id] ?? [],
                  ),
        [activeThread, messagesByThread],
    );

    async function markThreadAsRead(threadId: number): Promise<void> {
        if (!readUrl) {
            return;
        }

        setThreadItems((current) =>
            current.map((thread) =>
                thread.id === threadId
                    ? ({ ...thread, unread: 0 } as T & WithUnread)
                    : thread,
            ),
        );

        const csrfToken =
            document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content') ?? '';

        await fetch(readUrl(threadId), {
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
                (event: { threadId: number; message: ChatMessage }) => {
                    setMessagesByThread((current) => {
                        const currentThread = initialThreads.find(
                            (thread) => thread.id === event.threadId,
                        );
                        const serverMessages = currentThread?.messages ?? [];
                        const currentMessages = current[event.threadId] ?? [];
                        const knownMessageIds = new Set([
                            ...serverMessages.map((message) => message.id),
                            ...currentMessages.map((message) => message.id),
                        ]);

                        if (knownMessageIds.has(event.message.id)) {
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

                    const isIncomingMessage =
                        event.message.senderUserId !== null &&
                        event.message.senderUserId !== userId;

                    setThreadItems((current) => {
                        const targetThread = current.find(
                            (thread) => thread.id === event.threadId,
                        );

                        if (!targetThread) {
                            return current;
                        }

                        const updatedThread: T = {
                            ...targetThread,
                            preview:
                                event.message.message ||
                                event.message.documentName ||
                                targetThread.preview,
                            lastTime: 'baru saja',
                            latestActivityAt: new Date().toISOString(),
                        };

                        if (readUrl) {
                            const withUnread = updatedThread as T & WithUnread;

                            withUnread.unread =
                                isIncomingMessage &&
                                activeThreadId !== event.threadId
                                    ? withUnread.unread + 1
                                    : withUnread.unread;
                        }

                        return sortThreads([
                            updatedThread,
                            ...current.filter(
                                (thread) => thread.id !== event.threadId,
                            ),
                        ]);
                    });

                    if (
                        readUrl &&
                        event.threadId === activeThreadId &&
                        isIncomingMessage
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
        // markThreadAsRead only closes over stable setters and `readUrl`.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [userId, initialThreads, activeThreadId]);

    const canSend = useMemo(
        () =>
            !form.processing &&
            activeThreadId !== null &&
            (form.data.message.trim() !== '' || form.data.attachment !== null),
        [
            activeThreadId,
            form.data.attachment,
            form.data.message,
            form.processing,
        ],
    );

    function pickAttachment(event: ChangeEvent<HTMLInputElement>): void {
        const nextFile = event.target.files?.[0] ?? null;
        form.setData('attachment', nextFile);
        setAttachmentName(nextFile?.name ?? null);
    }

    function send(): void {
        if (!canSend || activeThreadId === null) {
            return;
        }

        const threadId = activeThreadId;

        syncThreadSearchParam(threadId, clearParams);
        const trimmedMessage = form.data.message.trim();
        const currentAttachmentName = attachmentName;

        form.transform((data) => ({
            ...data,
            message: trimmedMessage,
        }));

        form.post(sendUrl(threadId), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                setThreadItems((current) =>
                    sortThreads(
                        current.map((thread) =>
                            thread.id === threadId
                                ? {
                                      ...thread,
                                      preview:
                                          trimmedMessage ||
                                          currentAttachmentName ||
                                          thread.preview,
                                      lastTime: 'baru saja',
                                      latestActivityAt:
                                          new Date().toISOString(),
                                  }
                                : thread,
                        ),
                    ),
                );
                form.reset('message', 'attachment');
                setAttachmentName(null);
                setInputKey((current) => current + 1);

                onSent?.();
            },
        });
    }

    return {
        activeMessages,
        activeThread,
        activeThreadId,
        composer: {
            attachmentName,
            canSend,
            errors: form.errors,
            inputKey,
            message: form.data.message,
            pickAttachment,
            send,
            setMessage: (value) => form.setData('message', value),
        },
        markThreadAsRead,
        messagesFor: (thread) =>
            mergeMessages(thread.messages, messagesByThread[thread.id] ?? []),
        requestedThreadId,
        setActiveThreadId,
        threadItems,
        visibleThreads,
    };
}
