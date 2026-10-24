import { type UserProfileSummary } from '@/types';

import { type ChatMessage, type ChatThread, type MobileView } from './types';

export function buildThreadStateKey(threads: ChatThread[]): string {
    return threads.map((thread) => String(thread.id)).join('|');
}

export function messageMatches(message: ChatMessage, query: string): boolean {
    if (!query) {
        return false;
    }

    return [message.author, message.message, message.documentName, message.type]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()
        .includes(query);
}

export function sortThreads<T extends ChatThread>(threads: T[]): T[] {
    return [...threads].sort((a, b) => {
        if (a.latestActivityAt === b.latestActivityAt) {
            return b.id - a.id;
        }

        if (a.latestActivityAt === null) {
            return 1;
        }

        if (b.latestActivityAt === null) {
            return -1;
        }

        return b.latestActivityAt.localeCompare(a.latestActivityAt);
    });
}

/**
 * Threads of completed theses go after the rest, so the default selection is
 * never a read-only thread while an active one exists. Keeps relative order.
 */
export function activeFirst<T extends ChatThread>(threads: T[]): T[] {
    return [
        ...threads.filter((thread) => !thread.isCompleted),
        ...threads.filter((thread) => thread.isCompleted),
    ];
}

/** Server messages followed by locally received ones not yet on the server. */
export function mergeMessages(
    serverMessages: ChatMessage[],
    localMessages: ChatMessage[],
): ChatMessage[] {
    if (localMessages.length === 0) {
        return serverMessages;
    }

    const serverMessageIds = new Set(
        serverMessages.map((message) => message.id),
    );

    return [
        ...serverMessages,
        ...localMessages.filter((message) => !serverMessageIds.has(message.id)),
    ];
}

export function syncThreadSearchParam(
    threadId: number | null,
    clearParams: readonly string[] = [],
): void {
    if (typeof window === 'undefined') {
        return;
    }

    const nextUrl = new URL(window.location.href);

    if (threadId === null) {
        nextUrl.searchParams.delete('thread');
    } else {
        nextUrl.searchParams.set('thread', String(threadId));
    }

    for (const param of clearParams) {
        nextUrl.searchParams.delete(param);
    }

    window.history.replaceState(
        window.history.state,
        '',
        `${nextUrl.pathname}${nextUrl.search}${nextUrl.hash}`,
    );
}

function queryThreadId(threads: ChatThread[]): number | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const queryThread = Number(
        new URLSearchParams(window.location.search).get('thread'),
    );

    return Number.isInteger(queryThread) &&
        threads.some((thread) => thread.id === queryThread)
        ? queryThread
        : null;
}

export function resolveInitialThreadId(threads: ChatThread[]): number | null {
    return (
        queryThreadId(threads) ??
        threads.find((thread) => !thread.isCompleted)?.id ??
        threads[0]?.id ??
        null
    );
}

export function resolveInitialMobileView(threads: ChatThread[]): MobileView {
    return queryThreadId(threads) === null ? 'threads' : 'chat';
}

export function privateTargetProfile(
    thread: ChatThread | null,
    currentUserId?: number | null,
    fallback: UserProfileSummary | null = null,
): UserProfileSummary | null {
    if (thread?.threadType !== 'private') {
        return null;
    }

    return (
        thread.memberProfiles.find((member) => member.id !== currentUserId) ??
        fallback
    );
}
