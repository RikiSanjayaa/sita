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
}

export function SectionCard({
    title,
    description,
    actions,
    children,
    className,
    contentClassName,
}: SectionCardProps) {
    const titleId = useId();
    const hasHeader = Boolean(title || description || actions);

    return (
        <Card
            role={title ? 'region' : undefined}
            aria-labelledby={title ? titleId : undefined}
            className={cn('gap-4 py-6', className)}
        >
            {hasHeader && (
                <CardHeader className="flex flex-col items-start justify-between gap-4 sm:flex-row">
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
            <CardContent className={cn('px-6', contentClassName)}>
                {children}
            </CardContent>
        </Card>
    );
}
