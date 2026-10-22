import { cn } from '@/lib/utils';

import { type ChatTone } from './types';

const raisedPanelClass =
    'overflow-hidden border-border/70 !gap-0 !p-0 shadow-sm';
const flatPanelClass = '!gap-0 overflow-hidden !p-0';
const mobilePanelClass = 'rounded-none border-0 shadow-none';

/** Class names for the list/chat cards and their headers, per tone. */
export const chatStyles = {
    chatCard: (tone: ChatTone, isMobile: boolean): string =>
        cn(
            'flex min-h-0 min-w-0 flex-1 flex-col !gap-0 overflow-hidden !p-0 lg:h-full',
            isMobile ? mobilePanelClass : tone === 'raised' && raisedPanelClass,
        ),
    chatHeader: (tone: ChatTone, isMobile: boolean): string =>
        cn(
            tone === 'raised'
                ? 'shrink-0 border-b bg-muted/20 px-6 py-4'
                : 'shrink-0 bg-muted/20 p-6 pb-4',
            isMobile && 'px-4 py-4',
        ),
    listCard: (tone: ChatTone, isMobile: boolean): string =>
        cn(
            'flex min-h-0 min-w-0 flex-col lg:h-full lg:w-[340px] lg:shrink-0',
            tone === 'raised'
                ? isMobile
                    ? mobilePanelClass
                    : raisedPanelClass
                : cn(flatPanelClass, isMobile && mobilePanelClass),
        ),
    listHeader: (tone: ChatTone, isMobile: boolean): string =>
        cn(
            tone === 'raised'
                ? 'shrink-0 border-b bg-muted/20 px-6 py-4'
                : 'shrink-0 space-y-3 bg-muted/20 p-6 pb-4',
            isMobile && 'px-4 py-4',
        ),
};
