import { Inbox, Search } from 'lucide-react';
import * as React from 'react';

import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/* ─────────────────────────────────────────────────────────────────────
 * Types
 * ───────────────────────────────────────────────────────────────────── */

export type FilterTab = {
    label: string;
    value: string;
    count?: number;
};

export type FilterGroup = {
    tabs: FilterTab[];
    value: string;
    onChange: (value: string) => void;
};

/* ─────────────────────────────────────────────────────────────────────
 * DataTableContainer
 *
 * Wraps a <table> with the standard visual treatment:
 * rounded-xl border, bg-card, shadow-sm, horizontal scroll.
 * ───────────────────────────────────────────────────────────────────── */

export function DataTableContainer({
    children,
    className,
}: {
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'overflow-hidden rounded-xl border bg-card shadow-sm',
                className,
            )}
        >
            <div className="overflow-x-auto">{children}</div>
        </div>
    );
}

/* ─────────────────────────────────────────────────────────────────────
 * DataTableToolbar
 *
 * Layout: search (left) + filter pills (right).
 * Optional alertBadge renders above the toolbar row.
 * ───────────────────────────────────────────────────────────────────── */

export function DataTableToolbar({
    search,
    onSearchChange,
    searchPlaceholder = 'Cari...',
    filterGroups,
    alertBadge,
    className,
}: {
    search?: string;
    onSearchChange?: (value: string) => void;
    searchPlaceholder?: string;
    filterGroups?: FilterGroup[];
    alertBadge?: React.ReactNode;
    className?: string;
}) {
    const hasSearch = search !== undefined && onSearchChange !== undefined;
    const hasFilters = filterGroups && filterGroups.length > 0;

    if (!hasSearch && !hasFilters && !alertBadge) return null;

    return (
        <div className={cn('flex flex-col gap-3', className)}>
            {alertBadge && (
                <div className="flex justify-end">{alertBadge}</div>
            )}
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                {/* Search */}
                {hasSearch && (
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => onSearchChange(e.target.value)}
                            placeholder={searchPlaceholder}
                            className="h-8 pl-8 text-sm"
                        />
                    </div>
                )}

                {/* Filter pills */}
                {hasFilters && (
                    <div className="flex flex-wrap gap-2">
                        {filterGroups.map((group, gi) => (
                            <div key={gi} className="flex gap-1">
                                {group.tabs.map((tab) => (
                                    <button
                                        key={tab.value}
                                        type="button"
                                        onClick={() =>
                                            group.onChange(tab.value)
                                        }
                                        className={cn(
                                            'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium transition-colors',
                                            group.value === tab.value
                                                ? 'bg-primary text-primary-foreground shadow-sm'
                                                : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground',
                                        )}
                                    >
                                        {tab.label}
                                        {tab.count !== undefined &&
                                            tab.count > 0 && (
                                                <span
                                                    className={cn(
                                                        'rounded-full px-1.5 py-0.5 text-[10px] leading-none font-bold',
                                                        group.value ===
                                                            tab.value
                                                            ? 'bg-white/20 text-white'
                                                            : 'bg-amber-600/15 text-amber-700',
                                                    )}
                                                >
                                                    {tab.count}
                                                </span>
                                            )}
                                    </button>
                                ))}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

/* ─────────────────────────────────────────────────────────────────────
 * DataTablePagination
 *
 * Footer row with range label + Prev/Next buttons.
 * ───────────────────────────────────────────────────────────────────── */

export function DataTablePagination({
    currentPage,
    totalPages,
    totalItems,
    pageSize,
    onPageChange,
    itemLabel = 'item',
}: {
    currentPage: number;
    totalPages: number;
    totalItems: number;
    pageSize: number;
    onPageChange: (page: number) => void;
    itemLabel?: string;
}) {
    const safePage = Math.min(currentPage, totalPages);
    const rangeStart = totalItems === 0 ? 0 : (safePage - 1) * pageSize + 1;
    const rangeEnd = Math.min(safePage * pageSize, totalItems);

    return (
        <div className="flex items-center justify-between border-t px-5 py-2.5">
            <p className="text-xs text-muted-foreground">
                {totalItems === 0
                    ? `Tidak ada ${itemLabel}`
                    : `${rangeStart}\u2013${rangeEnd} dari ${totalItems} ${itemLabel}`}
            </p>
            {totalPages > 1 && (
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        disabled={safePage <= 1}
                        onClick={() => onPageChange(Math.max(1, safePage - 1))}
                        className="rounded px-2 py-1 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-40"
                    >
                        ← Prev
                    </button>
                    <span className="text-xs text-muted-foreground">
                        Hal {safePage} / {totalPages}
                    </span>
                    <button
                        type="button"
                        disabled={safePage >= totalPages}
                        onClick={() =>
                            onPageChange(Math.min(totalPages, safePage + 1))
                        }
                        className="rounded px-2 py-1 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-40"
                    >
                        Next →
                    </button>
                </div>
            )}
        </div>
    );
}

/* ─────────────────────────────────────────────────────────────────────
 * DataTableEmptyState
 *
 * Centered empty placeholder when table has no rows.
 * ───────────────────────────────────────────────────────────────────── */

export function DataTableEmptyState({
    icon: Icon = Inbox,
    title = 'Tidak ada data',
    description,
}: {
    icon?: React.ComponentType<{ className?: string }>;
    title?: string;
    description?: string;
}) {
    return (
        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-center">
            <Icon className="mb-3 size-10 text-muted-foreground/40" />
            <p className="text-sm font-semibold">{title}</p>
            {description && (
                <p className="mt-1 max-w-md text-sm text-muted-foreground">
                    {description}
                </p>
            )}
        </div>
    );
}

/* ─────────────────────────────────────────────────────────────────────
 * usePagination hook
 *
 * Manages page state + provides sliced data, totalPages, safePage.
 * Automatically resets when deps change.
 * ───────────────────────────────────────────────────────────────────── */

export function usePagination<T>(
    data: T[],
    pageSize: number,
    resetDeps: React.DependencyList = [],
) {
    const [page, setPage] = React.useState(1);

    // Reset to page 1 when any dep changes
    // eslint-disable-next-line react-hooks/exhaustive-deps
    React.useEffect(() => setPage(1), resetDeps);

    const totalPages = Math.max(1, Math.ceil(data.length / pageSize));
    const safePage = Math.min(page, totalPages);
    const paginated = data.slice(
        (safePage - 1) * pageSize,
        safePage * pageSize,
    );

    return { page: safePage, setPage, totalPages, paginated, totalItems: data.length };
}
