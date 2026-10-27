import { type ReactNode } from 'react';

import { cn } from '@/lib/utils';

type MentorshipChatFrameProps = {
    children: ReactNode;
    isMobile: boolean;
};

export function MentorshipChatFrame({
    children,
    isMobile,
}: MentorshipChatFrameProps) {
    return (
        <div
            className={cn(
                'flex h-[calc(100dvh-4rem-var(--desktop-notice-height,0px))] min-h-0 w-full min-w-0 flex-auto overflow-hidden bg-card lg:grid lg:grid-cols-[320px_minmax(0,1fr)] lg:grid-rows-[minmax(0,1fr)]',
                !isMobile && 'border-t',
            )}
        >
            {children}
        </div>
    );
}
