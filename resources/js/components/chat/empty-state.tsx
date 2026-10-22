import { type LucideIcon } from 'lucide-react';

import { cn } from '@/lib/utils';

import { type ChatTone } from './types';

type ChatEmptyStateProps = {
    className?: string;
    description: string;
    icon: LucideIcon;
    title: string;
    tone: ChatTone;
};

/** Dashed placeholder used by the thread list and the empty chat panel. */
export function ChatEmptyState({
    className,
    description,
    icon: Icon,
    title,
    tone,
}: ChatEmptyStateProps) {
    const isRaised = tone === 'raised';

    return (
        <div
            className={cn(
                'rounded-xl border border-dashed text-center',
                isRaised ? 'bg-muted/50 shadow-sm' : 'bg-muted/20',
                className,
            )}
        >
            <span
                className={cn(
                    'mx-auto mb-3 inline-flex size-10 items-center justify-center rounded-full text-muted-foreground',
                    isRaised ? 'bg-background shadow-sm' : 'bg-muted',
                )}
            >
                <Icon className="size-5" />
            </span>
            <p
                className={cn(
                    'text-sm',
                    isRaised ? 'font-semibold text-foreground' : 'font-medium',
                )}
            >
                {title}
            </p>
            <p className="mt-1 text-sm text-muted-foreground">{description}</p>
        </div>
    );
}
