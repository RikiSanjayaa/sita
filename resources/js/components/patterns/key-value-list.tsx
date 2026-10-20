import { cva } from 'class-variance-authority';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

const keyValueItem = cva('gap-1', {
    variants: {
        layout: {
            stacked: 'flex flex-col',
            inline: 'flex flex-col sm:flex-row sm:items-baseline sm:gap-4',
        },
    },
    defaultVariants: { layout: 'stacked' },
});

interface KeyValueItem {
    key?: string;
    label: ReactNode;
    value: ReactNode;
}

interface KeyValueListProps {
    items: readonly KeyValueItem[];
    layout?: 'stacked' | 'inline';
    className?: string;
}

export function KeyValueList({
    items,
    layout = 'stacked',
    className,
}: KeyValueListProps) {
    return (
        <dl className={cn('space-y-3 text-sm', className)}>
            {items.map((item, index) => (
                <div
                    key={item.key ?? index}
                    className={keyValueItem({ layout })}
                >
                    <dt
                        className={cn(
                            'text-muted-foreground',
                            layout === 'inline' && 'sm:w-40 sm:shrink-0',
                        )}
                    >
                        {item.label}
                    </dt>
                    <dd className="min-w-0 font-medium break-words">
                        {item.value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
