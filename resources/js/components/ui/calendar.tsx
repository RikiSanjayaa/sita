import { ChevronDown, ChevronLeft, ChevronRight, ChevronUp } from 'lucide-react';
import * as React from 'react';
import {
    DayPicker,
    getDefaultClassNames,
    type DayPickerProps,
} from 'react-day-picker';

import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';

function Calendar({
    className,
    classNames,
    showOutsideDays = true,
    ...props
}: DayPickerProps) {
    const defaultClassNames = getDefaultClassNames();

    return (
        <DayPicker
            showOutsideDays={showOutsideDays}
            className={cn('p-3', className)}
            classNames={{
                root: cn(defaultClassNames.root),
                months: 'flex flex-col gap-4 sm:flex-row',
                month: 'space-y-4',
                month_caption: 'relative flex items-center justify-center pt-1',
                caption_label: 'text-sm font-medium',
                nav: 'flex items-center gap-1',
                button_previous: cn(
                    buttonVariants({ variant: 'outline' }),
                    'absolute left-1 size-7 bg-transparent p-0 opacity-50 hover:opacity-100',
                ),
                button_next: cn(
                    buttonVariants({ variant: 'outline' }),
                    'absolute right-1 size-7 bg-transparent p-0 opacity-50 hover:opacity-100',
                ),
                month_grid: 'w-full border-collapse space-y-1',
                weekdays: 'flex',
                weekday:
                    'w-8 rounded-md text-[0.8rem] font-normal text-muted-foreground',
                week: 'mt-2 flex w-full',
                day: cn(
                    'relative size-8 p-0 text-center text-sm focus-within:relative focus-within:z-20 [&:has([aria-selected])]:bg-accent first:[&:has([aria-selected])]:rounded-l-md last:[&:has([aria-selected])]:rounded-r-md',
                    props.mode === 'range' &&
                        '[&:has(>.day-range-end)]:rounded-r-md [&:has(>.day-range-start)]:rounded-l-md',
                ),
                day_button: cn(
                    buttonVariants({ variant: 'ghost' }),
                    'size-8 p-0 font-normal aria-selected:opacity-100',
                ),
                selected:
                    'bg-primary text-primary-foreground hover:bg-primary hover:text-primary-foreground focus:bg-primary focus:text-primary-foreground',
                today: 'bg-accent text-accent-foreground',
                outside:
                    'text-muted-foreground opacity-50 aria-selected:bg-accent/50 aria-selected:text-muted-foreground aria-selected:opacity-30',
                disabled: 'text-muted-foreground opacity-50',
                range_start:
                    'day-range-start rounded-l-md bg-primary text-primary-foreground',
                range_middle:
                    'aria-selected:bg-accent aria-selected:text-accent-foreground',
                range_end:
                    'day-range-end rounded-r-md bg-primary text-primary-foreground',
                hidden: 'invisible',
                ...classNames,
            }}
            components={{
                Chevron: ({ orientation, className, ...chevronProps }) => {
                    const Icon =
                        orientation === 'left'
                            ? ChevronLeft
                            : orientation === 'up'
                              ? ChevronUp
                              : orientation === 'down'
                                ? ChevronDown
                                : ChevronRight;

                    return (
                        <Icon
                            className={cn('size-4', className)}
                            {...chevronProps}
                        />
                    );
                },
            }}
            {...props}
        />
    );
}

export { Calendar };
