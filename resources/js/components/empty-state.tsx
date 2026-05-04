import { type LucideIcon } from 'lucide-react';

import { cn } from '@/lib/utils';

interface EmptyStateProps {
    icon: LucideIcon;
    title: string;
    description?: string;
    className?: string;
}

export function EmptyState({
    icon: Icon,
    title,
    description,
    className,
}: EmptyStateProps) {
    return (
        <div
            className={cn(
                'rounded-xl border border-dashed bg-muted/20 p-6 text-center',
                className,
            )}
        >
            <span className="mx-auto mb-3 inline-flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                <Icon className="size-5" />
            </span>
            <p className="text-sm font-medium">{title}</p>
            {description && (
                <p className="mt-1 text-sm text-muted-foreground">
                    {description}
                </p>
            )}
        </div>
    );
}
