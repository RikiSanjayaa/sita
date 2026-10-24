import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { toneClassName, type StatusTone } from '@/lib/status-styles';
import { cn } from '@/lib/utils';

interface ActionQueueProps {
    /** Accessible name for the list. */
    label: string;
    children: ReactNode;
    className?: string;
    layout?: 'list' | 'bento';
}

export function ActionQueue({
    label,
    children,
    className,
    layout = 'list',
}: ActionQueueProps) {
    return (
        <ul
            aria-label={label}
            className={cn(
                layout === 'bento' ? 'grid gap-4 md:grid-cols-2' : 'space-y-2',
                className,
            )}
        >
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
    variant?: 'row' | 'card';
    count?: number;
    tone?: StatusTone;
}

export function ActionQueueItem({
    title,
    description,
    icon: Icon,
    meta,
    action,
    className,
    variant = 'row',
    count,
    tone = 'primary',
}: ActionQueueItemProps) {
    if (variant === 'card') {
        return (
            <li
                className={cn(
                    'flex min-w-0 flex-col gap-4 rounded-xl border bg-card p-5 text-card-foreground',
                    className,
                )}
            >
                <div className="flex items-center justify-between gap-4">
                    {Icon && (
                        <span
                            className={cn(
                                'inline-flex size-10 shrink-0 items-center justify-center rounded-lg',
                                toneClassName(tone),
                            )}
                        >
                            <Icon className="size-5" aria-hidden="true" />
                        </span>
                    )}
                    {count !== undefined && (
                        <span className="text-4xl leading-none font-bold tracking-tight tabular-nums">
                            {count}
                        </span>
                    )}
                </div>
                <div className="flex-1 space-y-2">
                    <h3 className="text-base leading-snug font-semibold text-pretty">
                        {title}
                    </h3>
                    {description && (
                        <p className="text-sm text-pretty text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
                {meta}
                {action && <div>{action}</div>}
            </li>
        );
    }

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
