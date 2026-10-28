import { Search } from 'lucide-react';
import { useId, type ReactNode } from 'react';

import {
    FilterSearchSlot,
    useFilterField,
} from '@/components/patterns/mobile-filter-scope';
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
 * Classes that turn a `<table>` into a compact list below `md`, so phones read
 * rows top to bottom instead of scrolling sideways. Each `<tr>` becomes a
 * wrapping flex row with three tiers, whatever the DOM order of its cells:
 *
 * 1. Main line (`tdPlain`, `tdAside`, `tdActions`): identity at the left,
 *    status badge and actions at the right.
 * 2. Metadata (`td`): short secondary values share two equal columns.
 *    `data-label` places a label directly above its value; use
 *    `data-label-hidden` when the value is already clear on its own.
 * 3. Detail (`tdDetail`): long text (title, advisors, status description) on
 *    its own line. Clamp it to two lines on phones (`max-md:line-clamp-2`)
 *    and keep the full value in a `title` attribute and on the detail page.
 *
 * From `md` every cell is an ordinary table cell and the row is unchanged.
 */
export const stackedTable = {
    table: 'w-full text-sm max-md:block',
    thead: 'max-md:sr-only',
    tbody: 'max-md:block max-md:divide-y',
    tr: "max-md:flex max-md:flex-wrap max-md:items-start max-md:gap-x-3 max-md:gap-y-1 max-md:px-3 max-md:py-2 max-md:before:order-2 max-md:before:h-0 max-md:before:basis-full max-md:before:content-['']",
    /** Identity cell (name and NIM): fills the main line. */
    tdPlain:
        'px-4 py-3 max-md:order-1 max-md:min-w-0 max-md:flex-1 max-md:basis-0 max-md:p-0',
    /** Primary status cell: stays at the right of the main line. */
    tdAside:
        'px-4 py-3 max-md:order-1 max-md:max-w-[45%] max-md:shrink-0 max-md:p-0',
    /**
     * Row-actions cell: a compact icon/menu cluster at the right of the row
     * and of the main line on phones. Put it last in the row, wrap the
     * controls in `flex items-center justify-end gap-1`, and give its header
     * cell `<span className="sr-only">Aksi</span>`.
     */
    tdActions:
        'w-px px-4 py-3 whitespace-nowrap max-md:order-1 max-md:w-auto max-md:shrink-0 max-md:p-0',
    /** Short secondary value in the two-column metadata grid. */
    td: 'stacked-meta px-4 py-3 max-md:order-3 max-md:w-[calc(50%-0.375rem)] max-md:min-w-0 max-md:grow-0 max-md:shrink-0 max-md:p-0 max-md:text-xs max-md:text-muted-foreground max-md:whitespace-normal max-md:[overflow-wrap:anywhere] max-md:[&[data-label]:not([data-label-hidden])]:before:block max-md:[&[data-label]:not([data-label-hidden])]:before:font-medium max-md:[&[data-label]:not([data-label-hidden])]:before:content-[attr(data-label)]',
    /** Long text on its own line under the meta line; clamp it to two lines inside. */
    tdDetail:
        'px-4 py-3 max-md:order-4 max-md:mt-1 max-md:basis-full max-md:p-0',
} as const;

/**
 * A titled block of a page: heading and summary, then its content.
 *
 * Pass `level={1}` when the section is the page's main block and replaces a
 * separate `PageHeader`: the title becomes the page h1 and `description` sits
 * under it instead of a right-aligned `summary`.
 */
export function DataSection({
    title,
    summary,
    description,
    level = 2,
    children,
    className,
}: {
    title?: ReactNode;
    summary?: ReactNode;
    /** Text under the title; use with `level={1}` on pages with one main section. */
    description?: ReactNode;
    /** Heading level of the title. Defaults to 2. */
    level?: 1 | 2;
    children: ReactNode;
    className?: string;
}) {
    const titleId = useId();
    const Heading = `h${level}` as const;
    const heading = title ? (
        <Heading
            id={titleId}
            className={cn(
                'font-semibold tracking-tight',
                level === 1 ? 'text-2xl text-balance' : 'text-xl',
            )}
        >
            {title}
        </Heading>
    ) : null;

    return (
        <section
            aria-labelledby={title ? titleId : undefined}
            className={cn('space-y-4', className)}
        >
            {description ? (
                <div className="space-y-1">
                    {heading}
                    <p className="text-sm text-pretty text-muted-foreground">
                        {description}
                    </p>
                </div>
            ) : title || summary ? (
                <div className="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                    {heading}
                    {summary ? (
                        <p className="text-sm text-pretty text-muted-foreground tabular-nums">
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
            <FilterSearchSlot>
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
            </FilterSearchSlot>
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
    const mobile = useFilterField(
        label,
        value,
        options[0]?.value ?? 'semua',
        onValueChange,
        options,
    );
    if (mobile) return null;
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
                    <SelectItem
                        key={option.value}
                        value={option.value}
                        className="min-h-11 md:min-h-0"
                    >
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
