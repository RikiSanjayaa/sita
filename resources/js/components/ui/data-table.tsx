import { Inbox, Search } from 'lucide-react';
import * as React from 'react';

import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export const DEFAULT_PAGE_SIZE_OPTIONS = [10, 15, 25, 50, 100];

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

export type DataTablePaginationProps = {
    currentPage: number;
    totalPages: number;
    totalItems?: number;
    currentItemCount?: number;
    pageSize: number;
    onPageChange: (page: number) => void;
    itemLabel?: string;
    placement?: 'top' | 'bottom';
    pageSizeOptions?: number[];
    onPageSizeChange?: (pageSize: number) => void;
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
    pagination,
}: {
    children: React.ReactNode;
    className?: string;
    pagination?: React.ReactElement<DataTablePaginationProps>;
}) {
    const topScrollRef = React.useRef<HTMLDivElement>(null);
    const tableScrollRef = React.useRef<HTMLDivElement>(null);
    const [scrollWidth, setScrollWidth] = React.useState(0);
    const [hasHorizontalOverflow, setHasHorizontalOverflow] =
        React.useState(false);
    const isSyncingScroll = React.useRef(false);

    React.useEffect(() => {
        const tableScrollElement = tableScrollRef.current;

        if (!tableScrollElement) return;

        const updateScrollState = () => {
            setScrollWidth(tableScrollElement.scrollWidth);
            setHasHorizontalOverflow(
                tableScrollElement.scrollWidth > tableScrollElement.clientWidth,
            );
        };

        updateScrollState();

        const resizeObserver = new ResizeObserver(updateScrollState);
        resizeObserver.observe(tableScrollElement);

        const tableElement = tableScrollElement.firstElementChild;

        if (tableElement) {
            resizeObserver.observe(tableElement);
        }

        return () => resizeObserver.disconnect();
    }, [children]);

    const syncScroll = (
        source: HTMLDivElement | null,
        target: HTMLDivElement | null,
    ) => {
        if (!source || !target || isSyncingScroll.current) return;

        isSyncingScroll.current = true;
        target.scrollLeft = source.scrollLeft;
        requestAnimationFrame(() => {
            isSyncingScroll.current = false;
        });
    };

    const renderPagination = (placement: 'top' | 'bottom') =>
        pagination
            ? React.cloneElement(pagination, {
                  placement,
              })
            : null;

    return (
        <div
            className={cn(
                'overflow-hidden rounded-xl border bg-card shadow-sm',
                className,
            )}
        >
            {renderPagination('top')}
            <div
                ref={topScrollRef}
                className={cn(
                    'h-4 overflow-x-auto overflow-y-hidden border-b',
                    !hasHorizontalOverflow && 'hidden',
                )}
                aria-hidden="true"
                onScroll={() =>
                    syncScroll(topScrollRef.current, tableScrollRef.current)
                }
            >
                <div className="h-px" style={{ width: scrollWidth }} />
            </div>
            <div
                ref={tableScrollRef}
                className="overflow-x-auto"
                onScroll={() =>
                    syncScroll(tableScrollRef.current, topScrollRef.current)
                }
            >
                {children}
            </div>
            {renderPagination('bottom')}
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
    currentItemCount,
    pageSize,
    onPageChange,
    itemLabel = 'item',
    placement = 'bottom',
    pageSizeOptions = DEFAULT_PAGE_SIZE_OPTIONS,
    onPageSizeChange,
}: DataTablePaginationProps) {
    const safePage = Math.min(currentPage, totalPages);
    const hasKnownTotal = totalItems !== undefined;
    const canChangePageSize =
        onPageSizeChange !== undefined && pageSizeOptions.length > 0;
    const normalizedPageSizeOptions = Array.from(
        new Set([...pageSizeOptions, pageSize]),
    ).sort((left, right) => left - right);
    const rangeStart =
        hasKnownTotal && totalItems === 0 ? 0 : (safePage - 1) * pageSize + 1;
    const rangeEnd = hasKnownTotal
        ? Math.min(safePage * pageSize, totalItems)
        : Math.max(rangeStart - 1, rangeStart + (currentItemCount ?? pageSize) - 1);

    return (
        <div
            className={cn(
                'flex flex-col gap-2 px-5 py-2.5 sm:flex-row sm:items-center sm:justify-between',
                placement === 'top' ? 'border-b' : 'border-t',
            )}
        >
            <div className="flex flex-wrap items-center gap-4">
                <p className="text-xs text-muted-foreground">
                    {hasKnownTotal
                        ? `Showing ${rangeStart} - ${rangeEnd} of ${totalItems}`
                        : `Showing ${rangeStart} - ${rangeEnd}`}
                </p>
                {canChangePageSize && (
                    <label className="flex items-center gap-4 text-xs text-muted-foreground">
                        <span
                            className="h-5 w-px bg-border"
                            aria-hidden="true"
                        />
                        <span>Per page:</span>
                        <select
                            value={pageSize}
                            onChange={(event) =>
                                onPageSizeChange(Number(event.target.value))
                            }
                            className="h-8 rounded-lg border bg-transparent px-3 text-xs text-foreground outline-none transition-colors hover:bg-muted/30 focus-visible:ring-2 focus-visible:ring-ring/20"
                        >
                            {normalizedPageSizeOptions.map((option) => (
                                <option key={option} value={option}>
                                    {option}
                                </option>
                            ))}
                        </select>
                    </label>
                )}
            </div>
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
                        {hasKnownTotal
                            ? `Hal ${safePage} / ${totalPages}`
                            : `Halaman ${safePage}`}
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
    initialPageSize: number,
    resetDeps: React.DependencyList = [],
    pageState?: [number, (page: number) => void],
) {
    const internalPageState = React.useState(1);
    const [page, setPage] = pageState ?? internalPageState;
    const [pageSize, setPageSizeState] = React.useState(initialPageSize);
    const didMount = React.useRef(false);
    const resetKey = JSON.stringify([...resetDeps, pageSize]);

    // Reset to page 1 when any dep changes
    React.useEffect(() => {
        if (!didMount.current) {
            didMount.current = true;
            return;
        }

        setPage(1);
    }, [resetKey, setPage]);

    const setPageSize = React.useCallback(
        (nextPageSize: number) => {
            setPageSizeState(nextPageSize);
            setPage(1);
        },
        [setPage],
    );

    const totalPages = Math.max(1, Math.ceil(data.length / pageSize));
    const safePage = Math.min(page, totalPages);
    const paginated = data.slice(
        (safePage - 1) * pageSize,
        safePage * pageSize,
    );

    return {
        page: safePage,
        setPage,
        pageSize,
        setPageSize,
        totalPages,
        paginated,
        totalItems: data.length,
    };
}
