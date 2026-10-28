import { SlidersHorizontal, X } from 'lucide-react';
import {
    useCallback,
    useId,
    useMemo,
    useRef,
    useState,
    type ReactNode,
} from 'react';

import {
    MobileFilterContext,
    type FilterField,
    type FilterValues,
} from '@/components/patterns/mobile-filter-scope';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useIsMobile } from '@/hooks/use-mobile';
import { cn } from '@/lib/utils';

interface StickyFilterBarProps {
    children: ReactNode;
    className?: string;
    layout?: 'portal' | 'public';
    mobileTitle?: ReactNode;
    onApplyFilters?: (values: FilterValues) => void;
}

function fieldTitle(label: string): string {
    const title = label.replace(/^Filter\s+/i, '');
    return title.charAt(0).toUpperCase() + title.slice(1);
}

/** Desktop stays sticky; phone filters expand inline without hiding the results. */
export function StickyFilterBar({
    children,
    className,
    layout = 'portal',
    mobileTitle,
    onApplyFilters,
}: StickyFilterBarProps) {
    const mobile = useIsMobile();
    const panelId = useId();
    const trigger = useRef<HTMLButtonElement>(null);
    const [fields, setFields] = useState(new Map<string, FilterField>());
    const [chosen, setChosen] = useState(new Set<string>());
    const [open, setOpen] = useState(false);
    const [searchTarget, setSearchTarget] = useState<HTMLDivElement | null>(
        null,
    );
    const register = useCallback((label: string, field: FilterField) => {
        setFields((current) => {
            const previous = current.get(label);
            if (
                previous?.value === field.value &&
                previous.resetValue === field.resetValue &&
                previous.options === field.options &&
                previous.commit === field.commit
            )
                return current;
            const next = new Map(current);
            next.set(label, field);
            return next;
        });
    }, []);
    const unregister = useCallback((label: string) => {
        setFields((current) => {
            if (!current.has(label)) return current;
            const next = new Map(current);
            next.delete(label);
            return next;
        });
    }, []);
    const scope = useMemo(
        () => ({ searchTarget, register, unregister }),
        [searchTarget, register, unregister],
    );
    const active = [...fields.values()].filter(
        (field) => field.value !== field.resetValue,
    ).length;
    const progressive = fields.size > 2;
    const available = [...fields].filter(
        ([label, field]) =>
            !chosen.has(label) && field.value === field.resetValue,
    );
    function change(label: string, value: string): void {
        const field = fields.get(label);
        if (!field || value === field.value) return;
        if (onApplyFilters) {
            onApplyFilters(
                Object.fromEntries(
                    [...fields].map(([key, entry]) => [
                        key,
                        key === label ? value : entry.value,
                    ]),
                ),
            );
        } else field.commit(value);
    }
    function remove(label: string): void {
        const field = fields.get(label);
        if (field) change(label, field.resetValue);
        setChosen((current) => {
            const next = new Set(current);
            next.delete(label);
            return next;
        });
    }
    function reset(): void {
        const values = Object.fromEntries(
            [...fields].map(([label, field]) => [label, field.resetValue]),
        );
        if (onApplyFilters) onApplyFilters(values);
        else
            for (const field of fields.values()) {
                if (field.value !== field.resetValue)
                    field.commit(field.resetValue);
            }
        setChosen(new Set());
    }
    if (mobile) {
        return (
            <MobileFilterContext value={scope}>
                <div
                    data-slot="sticky-filter-bar"
                    className={cn('space-y-2 bg-background py-2', className)}
                    onKeyDown={(event) => {
                        if (open && event.key === 'Escape') {
                            event.stopPropagation();
                            setOpen(false);
                            trigger.current?.focus();
                        }
                    }}
                >
                    {mobileTitle && (
                        <h2 className="text-lg font-semibold">{mobileTitle}</h2>
                    )}
                    <div className="flex items-center gap-2">
                        <div ref={setSearchTarget} className="min-w-0 flex-1" />
                        <Button
                            ref={trigger}
                            variant="outline"
                            className="relative size-11 shrink-0 p-0"
                            aria-label={
                                active ? `Filter, ${active} aktif` : 'Filter'
                            }
                            aria-expanded={open}
                            aria-controls={panelId}
                            onClick={() => setOpen(!open)}
                        >
                            <SlidersHorizontal aria-hidden className="size-4" />
                            {active > 0 && (
                                <span
                                    aria-hidden
                                    className="absolute top-2 right-2 size-1.5 rounded-full bg-primary"
                                />
                            )}
                        </Button>
                    </div>
                    <div hidden>{children}</div>
                    {open && (
                        <div
                            id={panelId}
                            role="region"
                            aria-label="Pilihan filter"
                            className="space-y-2"
                        >
                            <div
                                className={cn(
                                    'grid gap-2',
                                    !progressive && 'grid-cols-2',
                                )}
                            >
                                {[...fields]
                                    .filter(
                                        ([label, field]) =>
                                            !progressive ||
                                            chosen.has(label) ||
                                            field.value !== field.resetValue,
                                    )
                                    .map(([label, field]) => (
                                        <div
                                            key={label}
                                            className="flex min-w-0 items-end gap-1"
                                        >
                                            <div className="grid min-w-0 flex-1 gap-1">
                                                <span className="text-xs font-medium text-muted-foreground">
                                                    {fieldTitle(label)}
                                                </span>
                                                <Select
                                                    value={field.value}
                                                    onValueChange={(value) =>
                                                        change(label, value)
                                                    }
                                                >
                                                    <SelectTrigger
                                                        aria-label={label}
                                                        className="h-11 w-full min-w-0 bg-card text-base"
                                                    >
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {field.options.map(
                                                            (option) => (
                                                                <SelectItem
                                                                    key={
                                                                        option.value
                                                                    }
                                                                    value={
                                                                        option.value
                                                                    }
                                                                    disabled={
                                                                        option.disabled
                                                                    }
                                                                    className="min-h-11"
                                                                >
                                                                    {
                                                                        option.label
                                                                    }
                                                                    {option.count !==
                                                                    undefined
                                                                        ? ` (${option.count})`
                                                                        : ''}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            {progressive && (
                                                <Button
                                                    variant="ghost"
                                                    className="size-11 shrink-0 p-0"
                                                    aria-label={`Hapus filter ${fieldTitle(label)}`}
                                                    onClick={() =>
                                                        remove(label)
                                                    }
                                                >
                                                    <X
                                                        aria-hidden
                                                        className="size-4"
                                                    />
                                                </Button>
                                            )}
                                        </div>
                                    ))}
                            </div>
                            {progressive && available.length > 0 && (
                                <Select
                                    value=""
                                    onValueChange={(label) =>
                                        setChosen(
                                            (current) =>
                                                new Set([...current, label]),
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        aria-label="Tambah filter"
                                        className="h-11 w-full bg-card text-base"
                                    >
                                        <SelectValue placeholder="Filter berdasarkan..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {available.map(([label]) => (
                                            <SelectItem
                                                key={label}
                                                value={label}
                                                className="min-h-11"
                                            >
                                                {fieldTitle(label)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                            {(active > 0 || chosen.size > 0) && (
                                <Button
                                    variant="ghost"
                                    className="min-h-11 px-2"
                                    onClick={reset}
                                >
                                    Reset filter
                                </Button>
                            )}
                        </div>
                    )}
                </div>
            </MobileFilterContext>
        );
    }
    return (
        <div
            data-slot="sticky-filter-bar"
            className={cn(
                'space-y-3 bg-background py-3 md:sticky md:z-20',
                layout === 'public' ? 'md:top-[calc(5rem+1px)]' : 'md:top-12',
                className,
            )}
        >
            {children}
        </div>
    );
}
