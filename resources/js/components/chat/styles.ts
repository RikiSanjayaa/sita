import { cn } from '@/lib/utils';

/** Shared workspace, conversation and list surfaces. */
export const chatStyles = {
    chatCard: (isMobile: boolean): string =>
        cn(
            'relative my-2 mr-2 ml-0 flex min-h-0 min-w-0 flex-1 flex-col !gap-0 overflow-hidden rounded-xl border-0 bg-[var(--chat-surface)] !p-0 shadow-none',
            isMobile && 'm-0 rounded-none',
        ),
    chatHeader: (isMobile: boolean): string =>
        cn(
            'pointer-events-none absolute inset-x-0 top-0 z-10 !block bg-linear-to-b from-[var(--chat-surface)] from-60% to-transparent px-3 pt-3 pb-8',
            isMobile && 'px-2',
        ),
    listCard: (isMobile: boolean): string =>
        cn(
            'flex min-h-0 min-w-0 flex-col overflow-hidden rounded-none border-0 bg-card !p-0 shadow-none lg:h-full',
            isMobile && 'w-full',
        ),
    listHeader: (isMobile: boolean): string =>
        cn('shrink-0 space-y-3 bg-card px-4 py-3', isMobile && 'py-4'),
};
