import { type ReactNode } from 'react';

import { cn } from '@/lib/utils';

interface CardListItemProps {
    children: ReactNode;
    className?: string;
}

export function CardListItem({ children, className }: CardListItemProps) {
    return (
        <div
            className={cn(
                'rounded-xl border bg-background p-5 shadow-sm transition-shadow hover:shadow-md',
                className,
            )}
        >
            {children}
        </div>
    );
}

interface CardListItemHeaderProps {
    title: string;
    subtitle?: string;
    endContent?: ReactNode;
    className?: string;
}

export function CardListItemHeader({
    title,
    subtitle,
    endContent,
    className,
}: CardListItemHeaderProps) {
    return (
        <div
            className={cn(
                'flex flex-col gap-1.5 md:flex-row md:items-start md:justify-between',
                className,
            )}
        >
            <div>
                <p className="text-base font-semibold">{title}</p>
                {subtitle && (
                    <p className="mt-0.5 text-sm font-medium text-muted-foreground">
                        {subtitle}
                    </p>
                )}
            </div>
            {endContent && <div className="shrink-0">{endContent}</div>}
        </div>
    );
}

interface CardListItemContentProps {
    children: ReactNode;
    className?: string;
}

export function CardListItemContent({
    children,
    className,
}: CardListItemContentProps) {
    return <div className={cn('mt-4 grid gap-3', className)}>{children}</div>;
}

interface CardListItemFooterProps {
    children: ReactNode;
    className?: string;
}

export function CardListItemFooter({
    children,
    className,
}: CardListItemFooterProps) {
    return (
        <div
            className={cn(
                'mt-4 flex items-start gap-2 border-t border-dashed pt-3 text-sm text-muted-foreground',
                className,
            )}
        >
            {children}
        </div>
    );
}
