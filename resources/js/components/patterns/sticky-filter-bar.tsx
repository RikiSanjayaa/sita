import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

interface StickyFilterBarProps {
    children: ReactNode;
    className?: string;
}

/**
 * Keeps a search/filter bar visible while a long list scrolls underneath.
 * The offset matches the sticky layout header (h-16, h-12 when the sidebar is
 * collapsed to icons); the opaque background and border keep rows from showing
 * through.
 */
export function StickyFilterBar({ children, className }: StickyFilterBarProps) {
    return (
        <div
            data-slot="sticky-filter-bar"
            className={cn(
                'sticky top-16 z-20 rounded-lg border bg-background p-3 shadow-xs group-has-data-[collapsible=icon]/sidebar-wrapper:top-12',
                className,
            )}
        >
            {children}
        </div>
    );
}
