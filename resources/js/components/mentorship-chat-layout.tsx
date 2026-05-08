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
                'mx-auto flex h-[calc(100dvh-4rem)] w-full max-w-7xl min-w-0 flex-1 overflow-hidden lg:grid lg:h-[calc(100dvh-4rem-3rem)] lg:grid-cols-[340px_minmax(0,1fr)]',
                isMobile ? 'gap-0 px-0 py-0' : 'gap-6 px-4 py-6 md:px-6',
            )}
        >
            {children}
        </div>
    );
}
