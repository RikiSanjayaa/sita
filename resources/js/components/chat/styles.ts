import { cn } from '@/lib/utils';

import { type ChatTone } from './types';

/** Class names for the list/chat cards and their headers, per tone. */
export const chatStyles = {
    chatCard: (isMobile: boolean): string =>
        cn(
            'm-3 flex min-h-0 min-w-0 flex-1 flex-col !gap-0 overflow-hidden rounded-2xl border !p-0 shadow-none',
            'bg-card',
            isMobile && 'm-2',
        ),
    chatHeader: (isMobile: boolean): string =>
        cn('shrink-0 border-b bg-card px-3 py-2.5', isMobile && 'px-2'),
    listCard: (tone: ChatTone, isMobile: boolean): string =>
        cn(
            'flex min-h-0 min-w-0 flex-col overflow-hidden rounded-none border-0 !p-0 shadow-none lg:h-full lg:border-r',
            tone === 'raised' ? 'bg-card' : 'bg-background',
            isMobile && 'w-full',
        ),
    listHeader: (tone: ChatTone, isMobile: boolean): string =>
        cn(
            'shrink-0 space-y-3 border-b px-4 py-3',
            tone === 'raised' ? 'bg-muted/20' : 'bg-muted/30',
            isMobile && 'py-4',
        ),
};
