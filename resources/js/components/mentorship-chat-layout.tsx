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
                'chat-workspace flex h-[calc(100dvh-3rem)] min-h-0 w-full min-w-0 flex-auto overflow-hidden bg-card pb-2 lg:grid lg:grid-cols-[320px_minmax(0,1fr)] lg:grid-rows-[minmax(0,1fr)] lg:pb-0',
                !isMobile && 'border-t',
            )}
        >
            {children}
        </div>
    );
}
