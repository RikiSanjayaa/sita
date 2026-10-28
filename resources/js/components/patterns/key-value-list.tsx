import { cva } from 'class-variance-authority';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

const keyValueItem = cva('min-w-0 gap-1', {
    variants: {
        layout: {
            stacked: 'flex flex-col',
            inline: 'flex flex-col sm:flex-row sm:items-baseline sm:gap-4',
            grid: 'flex min-w-0 flex-col gap-0.5',
        },
    },
    defaultVariants: { layout: 'stacked' },
});

interface KeyValueItem {
    key?: string;
    label: ReactNode;
    value: ReactNode;
    fullWidth?: boolean;
}

interface KeyValueListProps {
    items: readonly KeyValueItem[];
    layout?: 'stacked' | 'inline' | 'grid';
    className?: string;
}

export function KeyValueList({
    items,
    layout = 'stacked',
    className,
}: KeyValueListProps) {
    return (
        <dl
            className={cn(
                layout === 'grid'
                    ? 'grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-3'
                    : 'grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:block sm:space-y-3',
                className,
            )}
        >
            {items.map((item, index) => (
                <div
                    key={item.key ?? index}
                    className={cn(
                        keyValueItem({ layout }),
                        item.fullWidth &&
                            (layout === 'grid'
                                ? 'col-span-full'
                                : 'col-span-full sm:col-span-1'),
                    )}
                >
                    <dt
                        className={cn(
                            'text-muted-foreground max-sm:text-xs',
                            layout === 'inline' && 'sm:w-40 sm:shrink-0',
                            layout === 'grid' && 'text-xs',
                        )}
                    >
                        {item.label}
                    </dt>
                    <dd className="min-w-0 font-medium text-pretty [overflow-wrap:anywhere]">
                        {item.value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
