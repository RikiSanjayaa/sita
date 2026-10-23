import { cva } from 'class-variance-authority';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

const pageHeaderTitle = cva('font-semibold tracking-tight text-balance', {
    variants: {
        level: {
            1: 'text-2xl',
            2: 'text-xl',
            3: 'text-lg',
        },
    },
    defaultVariants: { level: 1 },
});

interface PageHeaderProps {
    title: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
    /** Heading level rendered for the title. Defaults to 1. */
    level?: 1 | 2 | 3;
    className?: string;
}

export function PageHeader({
    title,
    description,
    actions,
    level = 1,
    className,
}: PageHeaderProps) {
    const Heading = `h${level}` as const;

    return (
        <header
            className={cn(
                'flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between',
                className,
            )}
        >
            <div className="min-w-0 space-y-1">
                <Heading className={pageHeaderTitle({ level })}>
                    {title}
                </Heading>
                {description && (
                    <p className="text-sm text-pretty text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </header>
    );
}
