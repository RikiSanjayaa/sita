import { cn } from '@/lib/utils';

import { type ChatTone } from './types';

/** Class names for the list/chat cards and their headers, per tone. */
export const chatStyles = {
    chatCard: (tone: ChatTone, isMobile: boolean): string =>
        cn(
            'flex min-h-0 min-w-0 flex-1 flex-col !gap-0 overflow-hidden rounded-none border-0 !p-0 shadow-none lg:h-full',
            tone === 'raised' ? 'bg-card' : 'bg-background',
            isMobile && 'w-full',
        ),
    chatHeader: (tone: ChatTone, isMobile: boolean): string =>
        cn(
            'shrink-0 border-b px-4 py-3',
            tone === 'raised' ? 'bg-muted/20' : 'bg-muted/30',
            isMobile && 'py-4',
        ),
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
