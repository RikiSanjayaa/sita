import { type ComponentProps } from 'react';

import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

/**
 * Icon-only action in a table row: a ghost icon button with a tooltip and an
 * accessible name, 36px from `md` and 44px on phones. Pass `asChild` to wrap
 * a `Link`.
 */
export function RowIconButton({
    label,
    className,
    children,
    ...props
}: Omit<ComponentProps<typeof Button>, 'size' | 'variant' | 'aria-label'> & {
    label: string;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type={props.asChild ? undefined : 'button'}
                    variant="ghost"
                    size="icon"
                    aria-label={label}
                    className={cn(
                        'text-muted-foreground hover:text-foreground max-md:size-11',
                        className,
                    )}
                    {...props}
                >
                    {children}
                </Button>
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}
