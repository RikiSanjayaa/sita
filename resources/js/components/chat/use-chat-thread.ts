import { useForm } from '@inertiajs/react';
import {
    useEffect,
    useEffectEvent,
    useMemo,
    useRef,
    useState,
    type Dispatch,
    type SetStateAction,
} from 'react';

import { isImageFile, validateChatAttachment } from './attachment';
import {
    chatValidationErrors,
    isChatSendAcknowledgement,
} from './send-response';
import {
    type ChatAttachmentKind,
    type ChatMessage,
    type ChatThread,
    type ChatReadSnapshot,
} from './types';
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
    /** Endpoint marking a conversation as read after its messages are visible. */
    readUrl?: (threadId: number) => string;
    isChatVisible?: boolean;
    /**
     * Threads shown in the list, in display order, given the current threads
     * and the active thread id. Holds the page's mode/search/filter rules.
     */
    selectVisible: (threads: T[], activeThreadId: number | null) => T[];
    /** Endpoint for posting a message to a thread. */
    sendUrl: (threadId: number) => string;
    /** Whether a thread accepts a custom "Nama dokumen" for file attachments. */
    supportsDocumentName?: (thread: T) => boolean;
    userId?: number | null;
    /** Called when a document event arrives live; lets the page refetch per-viewer data. */
    onDocumentEvent?: () => void;
};

export type ChatComposerState = {
    /** Staged file, or null when nothing is attached. */
    attachment: File | null;
    canSend: boolean;
    /** Aborts the upload in progress; the staged file stays for a retry. */
    cancelSend: () => void;
    /** Custom "Nama dokumen"; empty means the file name is used. */
    documentName: string;
    errors: { attachment: string | null; message: string | null };
    /** Changes whenever the file input must remount. */
    inputKey: number;
    message: string;
    /** Validates and stages a file. Null is ignored; invalid files keep the previous selection. */
    pickAttachment: (file: File | null) => void;
    /** Object URL for a staged image; null for documents. */
    previewUrl: string | null;
    processing: boolean;
    /** Upload percentage, or null before the upload reports one. */
    progress: number | null;
    removeAttachment: () => void;
    send: () => void;
    setDocumentName: (value: string) => void;
    setMessage: (value: string) => void;
    /** Whether the "Nama dokumen" field applies to the staged file. */
    showDocumentName: boolean;
};

type ChatThreadState<T extends ChatThread> = {
    activeMessages: ChatMessage[];
    activeThread: T | null;
    /** Active thread id after falling back to the first visible thread. */
    activeThreadId: number | null;
    composer: ChatComposerState;
    messagesFor: (thread: T) => ChatMessage[];
    requestedThreadId: number | null;
    /** Requests a thread; resolves to a visible one if it is filtered out. */
    setActiveThreadId: Dispatch<SetStateAction<number | null>>;
    threadItems: T[];
    visibleThreads: T[];
};

/** Message shown optimistically while an upload is in flight. */
type PendingSend = { threadId: number; message: ChatMessage };

type ActiveSend = {
    id: string;
    xhr: XMLHttpRequest;
    acknowledged: boolean;
    finish: () => void;
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
    onDocumentEvent,
    onSent,
    readUrl,
    isChatVisible = true,
    selectVisible,
    sendUrl,
    supportsDocumentName,
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
    const [clientError, setClientError] = useState<string | null>(null);
    const [inputKey, setInputKey] = useState(0);
    const [pendingSend, setPendingSend] = useState<PendingSend | null>(null);
    const [processing, setProcessing] = useState(false);
    const [progress, setProgress] = useState<number | null>(null);
    const activeSend = useRef<ActiveSend | null>(null);
    const sentCorrelations = useRef(new Set<string>());
    const messageVersion = useRef(0);
    const latestReadRequest = useRef(0);
    const readRequests = useRef(new Map<number, number>());
    const receivedMessageIds = useRef(new Set<number>());

    const form = useForm<{
        message: string;
        attachment: File | null;
        document_name: string;
    }>({
        message: '',
        attachment: null,
        document_name: '',
    });

    const attachment = form.data.attachment;

    // Object URLs for previews are revoked when the file changes or unmounts.
    const previewUrl = useMemo(
        () =>
            attachment !== null && isImageFile(attachment)
                ? URL.createObjectURL(attachment)
                : null,
        [attachment],
    );

    useEffect(() => {
        return () => {
            if (previewUrl !== null) {
                URL.revokeObjectURL(previewUrl);
            }
        };
    }, [previewUrl]);

    useEffect(
        () => () => {
            const request = activeSend.current;
            activeSend.current = null;
            request?.xhr.abort();
        },
        [],
    );

    useEffect(() => {
        const url = pendingSend?.message.previewUrl ?? null;

        return () => {
            if (url !== null) {
                URL.revokeObjectURL(url);
            }
        };
    }, [pendingSend]);

    // Server refreshes (after a send, review, or reload) replace the thread list;
    // realtime rows stay in messagesByThread and are deduplicated against it.
    useEffect(() => {
        setThreadItems(sortThreads(initialThreads));
        for (const thread of initialThreads) {
            for (const message of thread.messages) {
                receivedMessageIds.current.add(message.id);
            }
        }
    }, [initialThreads]);

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

    const activeMessages = useMemo(() => {
        const messages =
            activeThread === null
                ? []
                : mergeMessages(
                      activeThread.messages,
                      messagesByThread[activeThread.id] ?? [],
                  );

        if (pendingSend === null || pendingSend.threadId !== activeThread?.id) {
            return messages;
        }

        return [...messages, pendingSend.message];
    }, [activeThread, messagesByThread, pendingSend]);

    const showDocumentName =
        attachment !== null &&
        !isImageFile(attachment) &&
        activeThread !== null &&
        (supportsDocumentName?.(activeThread) ?? false);

    async function markThreadAsRead(
        threadId: number,
        lastMessageId: number,
    ): Promise<void> {
        if (
            !readUrl ||
            !isChatVisible ||
            document.visibilityState !== 'visible' ||
            !document.hasFocus()
        ) {
            return;
        }

        const csrfToken =
            document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content') ?? '';
        const requestId = ++latestReadRequest.current;
        readRequests.current.set(threadId, requestId);

        const response = await fetch(readUrl(threadId), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ last_message_id: lastMessageId }),
        });
        if (response.ok) {
            const data: ChatReadSnapshot = await response.json();
            window.dispatchEvent(
                new CustomEvent<
                    Pick<ChatReadSnapshot, 'userId' | 'readNotificationIds'>
                >('chat:notifications-read', { detail: data }),
            );
            if (readRequests.current.get(threadId) === requestId) {
                setThreadItems((current) =>
                    current.map((thread) =>
                        thread.id === threadId && 'unread' in thread
                            ? ({
                                  ...thread,
                                  unread: data.threadUnreadCount,
                              } as T & WithUnread)
                            : thread,
                    ),
                );
            }
            // A late acknowledgement must not replace a newer read's total.
            window.dispatchEvent(
                requestId === latestReadRequest.current
                    ? new CustomEvent<ChatReadSnapshot>('chat:read', {
                          detail: data,
                      })
                    : new Event('chat:read'),
            );
        }
    }

    const latestVisibleMessageId = (
        pendingSend?.threadId === activeThreadId
            ? activeMessages.at(-2)
            : activeMessages.at(-1)
    )?.id;
    const readVisibleThread = useEffectEvent(() => {
        if (activeThreadId !== null && latestVisibleMessageId !== undefined) {
            void markThreadAsRead(activeThreadId, latestVisibleMessageId).catch(
                console.error,
            );
        }
    });
    const readNotifiedThread = useEffectEvent((event: Event) => {
        const notification = (
            event as CustomEvent<Pick<ChatReadSnapshot, 'userId' | 'threadId'>>
        ).detail;
        if (
            notification.userId === userId &&
            notification.threadId === activeThreadId &&
            isChatVisible &&
            document.visibilityState === 'visible' &&
            document.hasFocus()
        ) {
            event.preventDefault();
            readVisibleThread();
        }
    });
    useEffect(() => {
        readVisibleThread();
        window.addEventListener('focus', readVisibleThread);
        window.addEventListener('chat:notification', readNotifiedThread);
        document.addEventListener('visibilitychange', readVisibleThread);
        return () => {
            window.removeEventListener('focus', readVisibleThread);
            window.removeEventListener('chat:notification', readNotifiedThread);
            document.removeEventListener('visibilitychange', readVisibleThread);
        };
    }, [activeThreadId, latestVisibleMessageId, isChatVisible, initialThreads]);

    useEffect(() => {
        if (typeof window === 'undefined' || !window.Echo) {
            return;
        }

        const echo = window.Echo;
        const channels = initialThreads.map((thread) => {
            const channelName = `mentorship.thread.${thread.id}`;

            const channel = echo.private(channelName);

            return channel.listen(
                '.chat.message.created',
                (event: { threadId: number; message: ChatMessage }) => {
                    const request = activeSend.current;
                    if (
                        request !== null &&
                        event.message.clientMessageId === request.id
                    ) {
                        request.acknowledged = true;
                        setPendingSend(null);
                    }
                    if (receivedMessageIds.current.has(event.message.id)) {
                        return;
                    }
                    receivedMessageIds.current.add(event.message.id);
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

                    if (
                        event.message.type === 'document_event' &&
                        !sentCorrelations.current.has(
                            event.message.clientMessageId ?? '',
                        )
                    ) {
                        onDocumentEvent?.();
                    }

                    const isIncomingMessage =
                        event.message.type !== 'document_event' &&
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

                        if (readUrl && 'unread' in targetThread) {
                            const withUnread = updatedThread as T & WithUnread;

                            withUnread.unread =
                                isIncomingMessage &&
                                (activeThreadId !== event.threadId ||
                                    !isChatVisible ||
                                    document.visibilityState !== 'visible' ||
                                    !document.hasFocus())
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
                },
            );
        });

        return () => {
            for (const [index, thread] of initialThreads.entries()) {
                channels[index]?.stopListening('.chat.message.created');
                echo.leave(`mentorship.thread.${thread.id}`);
            }
        };
        // Subscription callbacks use this render's read visibility and document handler.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [userId, initialThreads, activeThreadId, isChatVisible]);

    const canSend = useMemo(
        () =>
            !processing &&
            activeThreadId !== null &&
            (form.data.message.trim() !== '' || form.data.attachment !== null),
        [activeThreadId, form.data.attachment, form.data.message, processing],
    );

    function pickAttachment(file: File | null): void {
        if (file === null) {
            return;
        }

        const problem = validateChatAttachment(file);

        setClientError(problem);
        form.clearErrors('attachment', 'document_name');

        if (problem === null) {
            form.setData('attachment', file);
        }

        // Remount the file input so picking the same file again fires change.
        setInputKey((current) => current + 1);
    }

    function removeAttachment(): void {
        setClientError(null);
        form.clearErrors('attachment', 'document_name');
        form.setData('attachment', null);
        form.setData('document_name', '');
        setInputKey((current) => current + 1);
    }

    function cancelSend(): void {
        const request = activeSend.current;
        if (request?.acknowledged) {
            request.finish();
        } else {
            activeSend.current = null;
            setProcessing(false);
            setProgress(null);
            setPendingSend(null);
        }
        request?.xhr.abort();
    }

    function send(): void {
        if (
            !canSend ||
            activeThreadId === null ||
            activeSend.current !== null
        ) {
            return;
        }

        const threadId = activeThreadId;
        const clientMessageId = crypto.randomUUID();
        const draftVersion = messageVersion.current;

        syncThreadSearchParam(threadId, clearParams);
        const trimmedMessage = form.data.message.trim();
        const file = form.data.attachment;
        const documentName = showDocumentName
            ? form.data.document_name.trim()
            : '';
        const sentFileName = file === null ? null : documentName || file.name;

        let attachmentKind: ChatAttachmentKind | null = null;

        if (file !== null) {
            attachmentKind = isImageFile(file) ? 'image' : 'document';
        }

        const pendingPreviewUrl =
            file !== null && attachmentKind === 'image'
                ? URL.createObjectURL(file)
                : null;

        setPendingSend({
            threadId,
            message: {
                id: -Date.now(),
                clientMessageId,
                senderUserId: userId ?? null,
                author: '',
                authorAvatar: null,
                authorProfileUrl: null,
                message: trimmedMessage,
                time: 'Mengirim...',
                type: 'pending',
                documentName: sentFileName,
                documentUrl: null,
                attachmentKind,
                attachmentSizeKb:
                    file === null ? null : Math.ceil(file.size / 1024),
                previewUrl: pendingPreviewUrl,
                relatedDocumentId: null,
                reviewStatus: null,
            },
        });

        const xhr = new XMLHttpRequest();
        const request: ActiveSend = {
            id: clientMessageId,
            xhr,
            acknowledged: false,
            finish: () => {
                if (activeSend.current !== request) return;
                form.setData((current) => ({
                    ...current,
                    message:
                        messageVersion.current === draftVersion
                            ? ''
                            : current.message,
                    attachment:
                        current.attachment === file ? null : current.attachment,
                    document_name:
                        current.attachment === file
                            ? ''
                            : current.document_name,
                }));
                setThreadItems((current) =>
                    sortThreads(
                        current.map((thread) =>
                            thread.id === threadId
                                ? {
                                      ...thread,
                                      preview:
                                          trimmedMessage ||
                                          sentFileName ||
                                          thread.preview,
                                      lastTime: 'baru saja',
                                      latestActivityAt:
                                          new Date().toISOString(),
                                  }
                                : thread,
                        ),
                    ),
                );
                activeSend.current = null;
                setPendingSend(null);
                setProcessing(false);
                setProgress(null);
                setClientError(null);
                setInputKey((current) => current + 1);
                onSent?.();
            },
        };
        activeSend.current = request;
        sentCorrelations.current.add(clientMessageId);
        setProcessing(true);
        setProgress(null);
        form.clearErrors();

        const fail = (errors: Record<string, string[]> = {}) => {
            if (activeSend.current !== request) return;
            // Echo is authoritative even if the HTTP connection subsequently fails.
            if (request.acknowledged) {
                request.finish();
                return;
            }
            form.setError({
                message:
                    errors.message?.[0] ??
                    errors.ticket?.[0] ??
                    'Pesan belum terkirim. Periksa koneksi dan coba lagi.',
                ...(errors.attachment
                    ? { attachment: errors.attachment[0] }
                    : {}),
                ...(errors.document_name
                    ? { document_name: errors.document_name[0] }
                    : {}),
            });
            activeSend.current = null;
            setPendingSend(null);
            setProcessing(false);
            setProgress(null);
        };
        xhr.open('POST', sendUrl(threadId));
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader(
            'X-CSRF-TOKEN',
            document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content') ?? '',
        );
        xhr.upload.onprogress = (event) => {
            if (activeSend.current === request && event.lengthComputable) {
                setProgress(Math.round((event.loaded / event.total) * 100));
            }
        };
        xhr.onerror = () => fail();
        xhr.onabort = () => fail();
        xhr.onload = () => {
            if (activeSend.current !== request) return;
            try {
                const acknowledgement: unknown = JSON.parse(xhr.responseText);
                if (xhr.status !== 201) {
                    fail(chatValidationErrors(acknowledgement));
                    return;
                }
                if (
                    !isChatSendAcknowledgement(acknowledgement) ||
                    acknowledgement.clientMessageId !== clientMessageId ||
                    acknowledgement.threadId !== threadId
                ) {
                    fail();
                    return;
                }
                setMessagesByThread((current) => ({
                    ...current,
                    [threadId]: mergeMessages(
                        current[threadId] ?? [],
                        acknowledgement.messages,
                    ).sort((left, right) => left.id - right.id),
                }));
                for (const message of acknowledgement.messages) {
                    receivedMessageIds.current.add(message.id);
                }
                request.finish();
            } catch {
                fail();
            }
        };
        const data = new FormData();
        data.set('client_message_id', clientMessageId);
        data.set('message', trimmedMessage);
        data.set('document_name', documentName);
        if (file !== null) data.set('attachment', file);
        xhr.send(data);
    }

    return {
        activeMessages,
        activeThread,
        activeThreadId,
        composer: {
            attachment,
            canSend,
            cancelSend,
            documentName: form.data.document_name,
            errors: {
                attachment:
                    clientError ??
                    form.errors.attachment ??
                    form.errors.document_name ??
                    null,
                message: form.errors.message ?? null,
            },
            inputKey,
            message: form.data.message,
            pickAttachment,
            previewUrl,
            processing,
            progress,
            removeAttachment,
            send,
            setDocumentName: (value) => form.setData('document_name', value),
            setMessage: (value) => {
                messageVersion.current += 1;
                form.setData('message', value);
            },
            showDocumentName,
        },
        messagesFor: (thread) =>
            mergeMessages(thread.messages, messagesByThread[thread.id] ?? []),
        requestedThreadId,
        setActiveThreadId,
        threadItems,
        visibleThreads,
    };
}
