import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

type InteractiveTableRowProps = Omit<
    ComponentProps<'tr'>,
    'onClick' | 'onKeyDown'
> & {
    onOpen: () => void;
};

export function InteractiveTableRow({
    onOpen,
    className,
    ...props
}: InteractiveTableRowProps) {
    return (
        <tr
            {...props}
            tabIndex={0}
            className={cn(
                'cursor-pointer transition-colors hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset',
                className,
            )}
            onClick={(event) => {
                if (
                    event.target instanceof Element &&
                    event.target.closest(
                        'a, button, input, textarea, select, [role="button"]',
                    )
                ) {
                    return;
                }
                if (window.getSelection()?.isCollapsed === false) return;
                onOpen();
            }}
            onKeyDown={(event) => {
                if (
                    event.target === event.currentTarget &&
                    (event.key === 'Enter' || event.key === ' ')
                ) {
                    event.preventDefault();
                    onOpen();
                }
            }}
        />
    );
}
