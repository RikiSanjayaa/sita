import { ArrowDown, Users } from 'lucide-react';
import {
    useEffect,
    useLayoutEffect,
    useMemo,
    useRef,
    useState,
    type ReactNode,
} from 'react';

import { ChatBubble } from '@/components/chat-bubble';
import { Button } from '@/components/ui/button';
import { CardContent } from '@/components/ui/card';

import { ChatEmptyState } from './empty-state';
import { type ChatMessage, type ChatTone } from './types';
import { type MessageSearchState } from './use-message-search';

type MessageListProps = {
    messages: ChatMessage[];
    notice?: ReactNode;
    search: MessageSearchState;
    threadId: number | null;
    tone: ChatTone;
    userId?: number | null;
};

function sameGroup(
    a: ChatMessage | undefined,
    b: ChatMessage | undefined,
): boolean {
    return (
        a !== undefined &&
        b !== undefined &&
        a.senderUserId !== null &&
        a.senderUserId === b.senderUserId &&
        a.time === b.time &&
        a.type === b.type &&
        a.type !== 'document_event' &&
        a.type !== 'revision_suggestion'
    );
}

/** Incoming messages never pull someone away from the history they are reading. */
export function MessageList({
    messages,
    notice,
    search,
    threadId,
    tone,
    userId,
}: MessageListProps) {
    const viewportRef = useRef<HTMLDivElement>(null);
    const contentRef = useRef<HTMLDivElement>(null);
    const pinnedRef = useRef(true);
    const previousRef = useRef<{
        threadId: number | null;
        messageId: number | undefined;
    }>({ threadId: null, messageId: undefined });
    const [atBottom, setAtBottom] = useState(true);
    const latestMessage = messages.at(-1);

    const reviewStatusByDocument = useMemo(() => {
        const statuses = new Map<number, string>();
        for (const message of messages) {
            if (
                message.relatedDocumentId !== null &&
                message.reviewStatus !== null
            ) {
                statuses.set(message.relatedDocumentId, message.reviewStatus);
            }
        }
        return statuses;
    }, [messages]);

    useLayoutEffect(() => {
        const viewport = viewportRef.current;
        const previous = previousRef.current;
        const threadChanged = previous.threadId !== threadId;
        const sentMessage =
            previous.messageId !== latestMessage?.id &&
            latestMessage?.senderUserId === userId;
        if (viewport && (threadChanged || pinnedRef.current || sentMessage)) {
            viewport.scrollTop = viewport.scrollHeight;
            pinnedRef.current = true;
        }
        previousRef.current = { threadId, messageId: latestMessage?.id };
    }, [
        latestMessage?.id,
        latestMessage?.senderUserId,
        messages,
        threadId,
        userId,
    ]);

    useEffect(() => {
        const viewport = viewportRef.current;
        const content = contentRef.current;
        if (!viewport || !content) return;
        // Images, attachment previews and viewport changes can alter the bottom
        // without a new message. Only follow those changes while already pinned.
        const observer = new ResizeObserver(() => {
            if (pinnedRef.current) viewport.scrollTop = viewport.scrollHeight;
        });
        observer.observe(viewport);
        observer.observe(content);
        return () => observer.disconnect();
    }, [threadId]);

    return (
        <CardContent className="relative min-h-0 flex-1 overflow-hidden p-0">
            <div
                ref={viewportRef}
                role="region"
                aria-label="Riwayat pesan"
                tabIndex={0}
                className="h-full overflow-x-hidden overflow-y-auto overscroll-contain px-3 py-2 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring sm:px-4"
                onScroll={(event) => {
                    const viewport = event.currentTarget;
                    const pinned =
                        viewport.scrollHeight -
                            viewport.scrollTop -
                            viewport.clientHeight <
                        48;
                    pinnedRef.current = pinned;
                    setAtBottom(pinned);
                }}
            >
                <div
                    ref={contentRef}
                    className={search.isOpen ? 'pt-36' : 'pt-16'}
                >
                    {threadId !== null && notice}
                    {threadId !== null ? (
                        <div className="flex flex-col pb-1">
                            {messages.map((message, index) => {
                                const messageId = String(message.id);
                                const grouped = sameGroup(
                                    messages[index - 1],
                                    message,
                                );
                                return (
                                    <div
                                        key={message.id}
                                        data-message-id={message.id}
                                        data-message-grouped={grouped}
                                        ref={(element) => {
                                            search.messageRefs.current[
                                                messageId
                                            ] = element;
                                        }}
                                    >
                                        <ChatBubble
                                            message={message}
                                            grouped={grouped}
                                            showTime={
                                                !sameGroup(
                                                    message,
                                                    messages[index + 1],
                                                )
                                            }
                                            reviewStatus={
                                                message.relatedDocumentId ===
                                                null
                                                    ? null
                                                    : (reviewStatusByDocument.get(
                                                          message.relatedDocumentId,
                                                      ) ?? null)
                                            }
                                            isMe={
                                                message.senderUserId === userId
                                            }
                                            searchTerm={
                                                search.matchIds.has(messageId)
                                                    ? search.normalizedQuery
                                                    : ''
                                            }
                                            isActiveMatch={
                                                search.activeMatchId ===
                                                messageId
                                            }
                                        />
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <ChatEmptyState
                            className="mt-10 p-8"
                            description="Chat akan tampil di sini."
                            icon={Users}
                            title="Pilih percakapan"
                            tone={tone}
                        />
                    )}
                </div>
            </div>
            {threadId !== null && !atBottom && (
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    className="absolute right-3 bottom-3 gap-2 rounded-full border shadow-md max-md:min-h-11"
                    onClick={() => {
                        const viewport = viewportRef.current;
                        if (!viewport) return;
                        pinnedRef.current = true;
                        viewport.scrollTo({
                            top: viewport.scrollHeight,
                            behavior: window.matchMedia(
                                '(prefers-reduced-motion: reduce)',
                            ).matches
                                ? 'instant'
                                : 'smooth',
                        });
                    }}
                >
                    <ArrowDown aria-hidden className="size-4" />
                    Pesan terbaru
                </Button>
            )}
        </CardContent>
    );
}
