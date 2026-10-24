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
    variant?: 'row' | 'card' | 'featured';
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
    if (variant !== 'row') {
        const featured = variant === 'featured';

        return (
            <li
                className={cn(
                    'flex min-w-0 flex-col gap-1 rounded-xl border bg-card p-3 text-card-foreground',
                    featured && 'gap-3 border-primary/25 p-5 md:p-6',
                    className,
                )}
            >
                <div className="flex items-start justify-between gap-3">
                    <div className="flex min-w-0 items-start gap-3">
                        {Icon && (
                            <span
                                className={cn(
                                    'inline-flex size-7 shrink-0 items-center justify-center rounded-lg',
                                    featured && 'size-10 rounded-xl',
                                    toneClassName(tone),
                                )}
                            >
                                <Icon className="size-4" aria-hidden="true" />
                            </span>
                        )}
                        <h3
                            className={cn(
                                'text-sm leading-snug font-semibold text-pretty',
                                featured && 'max-w-md text-lg md:text-xl',
                            )}
                        >
                            {title}
                        </h3>
                    </div>
                    {count !== undefined && (
                        <span
                            className={cn(
                                'max-w-2/5 shrink-0 text-right text-2xl leading-none font-semibold tracking-tight break-all tabular-nums',
                                featured && 'text-4xl text-primary md:text-5xl',
                            )}
                        >
                            {count}
                        </span>
                    )}
                </div>
                {description && (
                    <p
                        className={cn(
                            'text-xs leading-5 text-pretty text-muted-foreground',
                            featured && 'text-sm leading-relaxed',
                        )}
                    >
                        {description}
                    </p>
                )}
                {(meta || action) && (
                    <div
                        className={cn(
                            'mt-auto flex items-end justify-end gap-3',
                            featured &&
                                'flex-wrap justify-between border-t pt-2',
                        )}
                    >
                        {meta && (
                            <div className="min-w-0 flex-1 basis-32">
                                {meta}
                            </div>
                        )}
                        {action && <div className="shrink-0">{action}</div>}
                    </div>
                )}
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
