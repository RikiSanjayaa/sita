import { Users } from 'lucide-react';
import { useEffect, useMemo, useRef, type ReactNode } from 'react';

import { ChatBubble } from '@/components/chat-bubble';
import { CardContent } from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';
import { cn } from '@/lib/utils';

import { ChatEmptyState } from './empty-state';
import { type ChatMessage, type ChatTone } from './types';
import { type MessageSearchState } from './use-message-search';

type MessageListProps = {
    isMobile: boolean;
    messages: ChatMessage[];
    /** Rendered above the messages (e.g. a status alert). */
    notice?: ReactNode;
    search: MessageSearchState;
    /** Whether a thread is selected; otherwise the empty state shows. */
    threadId: number | null;
    tone: ChatTone;
    userId?: number | null;
};

/** Scrollable message body that follows new messages and search matches. */
export function MessageList({
    isMobile,
    messages,
    notice,
    search,
    threadId,
    tone,
    userId,
}: MessageListProps) {
    const endRef = useRef<HTMLDivElement | null>(null);
    const messageCount = messages.length;

    /** Newest review status per document; later rows win over earlier ones. */
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

    useEffect(() => {
        if (messageCount < 1 || threadId === null) {
            return;
        }

        endRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messageCount, threadId]);

    return (
        <CardContent className="relative flex-1 overflow-hidden p-0">
            <ScrollArea className="h-full w-full">
                <div
                    className={cn(
                        'flex min-h-full flex-col',
                        isMobile ? 'px-4 py-3' : 'p-4',
                    )}
                >
                    {threadId !== null && notice}

                    {threadId !== null ? (
                        <div className="grid">
                            {messages.map((message) => {
                                const messageId = String(message.id);

                                return (
                                    <div
                                        key={message.id}
                                        ref={(element) => {
                                            search.messageRefs.current[
                                                messageId
                                            ] = element;
                                        }}
                                    >
                                        <ChatBubble
                                            message={message}
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
                            <div ref={endRef} className="h-1" />
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
            </ScrollArea>
        </CardContent>
    );
}
