import { useEffect, useMemo, useRef, useState, type RefObject } from 'react';

import { type ChatMessage } from './types';
import { messageMatches } from './utils';

export type MessageSearchState = {
    activeMatchId: string | null;
    activeMatchIndex: number;
    changeQuery: (value: string) => void;
    isOpen: boolean;
    matchIds: ReadonlySet<string>;
    matchCount: number;
    messageRefs: RefObject<Record<string, HTMLDivElement | null>>;
    normalizedQuery: string;
    query: string;
    reset: () => void;
    step: (direction: 1 | -1) => void;
    toggle: () => void;
};

/** In-thread message search: query, match stepping and scroll-to-match. */
export function useMessageSearch(messages: ChatMessage[]): MessageSearchState {
    const [query, setQuery] = useState('');
    const [isOpen, setIsOpen] = useState(false);
    const [activeMatchIndex, setActiveMatchIndex] = useState(0);
    const messageRefs = useRef<Record<string, HTMLDivElement | null>>({});

    const normalizedQuery = query.trim().toLowerCase();

    const matchIdList = useMemo(
        () =>
            normalizedQuery
                ? messages
                      .filter((message) =>
                          messageMatches(message, normalizedQuery),
                      )
                      .map((message) => String(message.id))
                : [],
        [messages, normalizedQuery],
    );

    const matchIds = useMemo(() => new Set(matchIdList), [matchIdList]);

    const resolvedIndex =
        matchIdList.length > 0
            ? Math.min(activeMatchIndex, matchIdList.length - 1)
            : 0;

    const activeMatchId =
        matchIdList.length > 0 ? matchIdList[resolvedIndex] : null;

    useEffect(() => {
        if (!activeMatchId) {
            return;
        }

        messageRefs.current[activeMatchId]?.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
        });
    }, [activeMatchId]);

    function reset(): void {
        setQuery('');
        setIsOpen(false);
        setActiveMatchIndex(0);
    }

    return {
        activeMatchId,
        activeMatchIndex: resolvedIndex,
        changeQuery: (value) => {
            setQuery(value);
            setActiveMatchIndex(0);
        },
        isOpen,
        matchIds,
        matchCount: matchIdList.length,
        messageRefs,
        normalizedQuery,
        query,
        reset,
        step: (direction) => {
            if (matchIdList.length === 0) {
                return;
            }

            setActiveMatchIndex((current) => {
                const next = current + direction;

                if (next < 0) {
                    return matchIdList.length - 1;
                }

                if (next >= matchIdList.length) {
                    return 0;
                }

                return next;
            });
        },
        toggle: () => {
            setIsOpen((current) => !current);

            if (isOpen) {
                setQuery('');
                setActiveMatchIndex(0);
            }
        },
    };
}
