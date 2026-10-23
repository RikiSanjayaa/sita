import { Search } from 'lucide-react';
import { useId, type ReactNode } from 'react';

import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

/** Column header cell of a data table. */
export const dataTh =
    'px-4 py-3 text-left text-xs font-medium text-muted-foreground';

/**
 * Classes that turn a `<table>` into a stacked list below `md`, so phones read
 * rows top to bottom instead of scrolling sideways. Cells carry `data-label`
 * and show it above their value on phones.
 */
export const stackedTable = {
    table: 'w-full text-sm max-md:block',
    thead: 'max-md:sr-only',
    tbody: 'max-md:block max-md:divide-y',
    tr: 'max-md:block max-md:px-4 max-md:py-3',
    /** Cell without a label above it on phones, e.g. the name column. */
    tdPlain: 'px-4 py-3 max-md:block max-md:px-0 max-md:py-1',
    /**
     * Row-actions cell: a compact icon/menu cluster at the right of the row,
     * and on phones the last line of the entry. Put it last in the row, wrap
     * the controls in `flex items-center justify-end gap-1 max-md:justify-start`,
     * and give its header cell `<span className="sr-only">Aksi</span>`.
     */
    tdActions:
        'w-px px-4 py-3 whitespace-nowrap max-md:block max-md:w-auto max-md:px-0 max-md:pt-2 max-md:pb-0',
    td: 'px-4 py-3 max-md:block max-md:px-0 max-md:py-1 max-md:before:mb-0.5 max-md:before:block max-md:before:text-xs max-md:before:font-medium max-md:before:text-muted-foreground max-md:before:content-[attr(data-label)]',
} as const;

/** A titled block of a page: heading and summary, then its content. */
export function DataSection({
    title,
    summary,
    children,
    className,
}: {
    title?: ReactNode;
    summary?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    const titleId = useId();

    return (
        <section
            aria-labelledby={title ? titleId : undefined}
            className={cn('space-y-4', className)}
        >
            {title || summary ? (
                <div className="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                    {title ? (
                        <h2
                            id={titleId}
                            className="text-xl font-semibold tracking-tight"
                        >
                            {title}
                        </h2>
                    ) : null}
                    {summary ? (
                        <p className="text-sm text-muted-foreground tabular-nums">
                            {summary}
                        </p>
                    ) : null}
                </div>
            ) : null}
            {children}
        </section>
    );
}

/** Search field on the left, filters on the right; 44px controls on phones. */
export function DataToolbar({
    search,
    onSearchChange,
    placeholder,
    children,
}: {
    search: string;
    onSearchChange: (value: string) => void;
    placeholder: string;
    children?: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div className="relative w-full md:max-w-sm">
                <Search
                    aria-hidden="true"
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    type="search"
                    value={search}
                    onChange={(event) => onSearchChange(event.target.value)}
                    placeholder={placeholder}
                    aria-label={placeholder.replace(/\.{3}$/, '')}
                    className="h-11 bg-card pl-9 md:h-9"
                />
            </div>
            {children ? (
                <div className="flex flex-wrap items-center gap-2">
                    {children}
                </div>
            ) : null}
        </div>
    );
}

/** Program studi (or similar) filter with a visible label. */
export function DataSelect({
    label,
    value,
    onValueChange,
    placeholder,
    options,
    className,
}: {
    label: string;
    value: string;
    onValueChange: (value: string) => void;
    placeholder: string;
    options: ReadonlyArray<{ value: string; label: string }>;
    className?: string;
}) {
    return (
        <Select value={value} onValueChange={onValueChange}>
            <SelectTrigger
                aria-label={label}
                className={cn('h-11 w-full bg-card md:h-9 md:w-64', className)}
            >
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
