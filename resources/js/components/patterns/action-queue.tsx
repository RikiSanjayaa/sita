import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

interface ActionQueueProps {
    /** Accessible name for the list. */
    label: string;
    children: ReactNode;
    className?: string;
}

export function ActionQueue({ label, children, className }: ActionQueueProps) {
    return (
        <ul aria-label={label} className={cn('space-y-2', className)}>
            {children}
        </ul>
    );
}

interface ActionQueueItemProps {
    title: ReactNode;
    description?: ReactNode;
    icon?: LucideIcon;
    /** Status or metadata rendered beside the title (e.g. a badge). */
    meta?: ReactNode;
    /**
     * Interactive control(s) such as a Button, Inertia Link or anchor.
     * Rendered as a sibling of the text so controls are never nested.
     */
    action?: ReactNode;
    className?: string;
}

export function ActionQueueItem({
    title,
    description,
    icon: Icon,
    meta,
    action,
    className,
}: ActionQueueItemProps) {
    return (
        <li
            className={cn(
                'flex flex-col gap-3 rounded-lg border bg-card p-4 text-card-foreground sm:flex-row sm:items-center sm:justify-between',
                className,
            )}
        >
            <div className="flex min-w-0 items-start gap-3">
                {Icon && (
                    <span className="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary-muted text-primary-muted-foreground">
                        <Icon className="size-4" aria-hidden="true" />
                    </span>
                )}
                <div className="min-w-0 space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="text-sm font-medium">{title}</p>
                        {meta}
                    </div>
                    {description && (
                        <p className="text-sm text-pretty text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
            </div>
            {action && <div className="shrink-0">{action}</div>}
        </li>
    );
}
