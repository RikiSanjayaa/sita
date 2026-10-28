import { useId, type ReactNode } from 'react';

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

interface SectionCardProps {
    title?: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
    children: ReactNode;
    className?: string;
    contentClassName?: string;
    density?: 'default' | 'compact';
}

export function SectionCard({
    title,
    description,
    actions,
    children,
    className,
    contentClassName,
    density = 'default',
}: SectionCardProps) {
    const titleId = useId();
    const hasHeader = Boolean(title || description || actions);

    return (
        <Card
            role={title ? 'region' : undefined}
            aria-labelledby={title ? titleId : undefined}
            className={cn(
                density === 'compact' ? 'gap-3 py-3' : 'gap-4 py-6',
                className,
            )}
        >
            {hasHeader && (
                <CardHeader
                    className={cn(
                        'flex items-start justify-between',
                        density === 'compact'
                            ? 'flex-row gap-2 px-4'
                            : 'flex-col gap-4 sm:flex-row',
                    )}
                >
                    <div className="min-w-0 space-y-1.5">
                        {title && (
                            <h2
                                id={titleId}
                                className="leading-none font-semibold"
                            >
                                {title}
                            </h2>
                        )}
                        {description && (
                            <CardDescription>{description}</CardDescription>
                        )}
                    </div>
                    {actions && (
                        <div className="flex max-w-full flex-wrap items-center gap-2 sm:shrink-0">
                            {actions}
                        </div>
                    )}
                </CardHeader>
            )}
            <CardContent
                className={cn(
                    density === 'compact' ? 'px-4' : 'px-6',
                    contentClassName,
                )}
            >
                {children}
            </CardContent>
        </Card>
    );
}
