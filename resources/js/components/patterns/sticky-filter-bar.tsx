import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

interface StickyFilterBarProps {
    children: ReactNode;
    className?: string;
    layout?: 'portal' | 'public';
}

/**
 * Keep the complete toolbar together, without covering phone-sized lists.
 * PublicLayout's desktop header is a 48px logo + 32px padding + 1px border;
 * portal headers are 64px, or 48px with the icon-only sidebar.
 */
export function StickyFilterBar({
    children,
    className,
    layout = 'portal',
}: StickyFilterBarProps) {
    return (
        <div
            data-slot="sticky-filter-bar"
            className={cn(
                'space-y-3 bg-background py-3 md:sticky md:z-20',
                layout === 'public'
                    ? 'md:top-[calc(5rem+1px)]'
                    : 'md:top-16 md:group-has-data-[collapsible=icon]/sidebar-wrapper:top-12',
                className,
            )}
        >
            {children}
        </div>
    );
}
