import { SlidersHorizontal } from 'lucide-react';
import { useCallback, useMemo, useRef, useState, type ReactNode } from 'react';

import {
    MobileFilterContext,
    type FilterScope,
    type FilterValues,
} from '@/components/patterns/mobile-filter-scope';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useIsMobile } from '@/hooks/use-mobile';
import { cn } from '@/lib/utils';

interface StickyFilterBarProps {
    children: ReactNode;
    className?: string;
    layout?: 'portal' | 'public';
    mobileTitle?: ReactNode;
    onApplyFilters?: (values: FilterValues) => void;
}

/**
 * Keep the complete toolbar together, without covering phone-sized lists.
 * PublicLayout's desktop header is a 48px logo + 32px padding + 1px border;
 * portal headers are 48px in both sidebar states.
 */
export function StickyFilterBar({
    children,
    className,
    layout = 'portal',
    mobileTitle,
    onApplyFilters,
}: StickyFilterBarProps) {
    const mobile = useIsMobile();
    const fields = useRef(
        new Map<string, Parameters<FilterScope['register']>[1]>(),
    );
    const [active, setActive] = useState(0);
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState<FilterValues | null>(null);
    const [searchTarget, setSearchTarget] = useState<HTMLDivElement | null>(
        null,
    );
    const register = useCallback<FilterScope['register']>((label, field) => {
        fields.current.set(label, field);
        setActive(
            [...fields.current.values()].filter(
                (entry) => entry.value !== entry.resetValue,
            ).length,
        );
        return () => {
            fields.current.delete(label);
            setActive(
                [...fields.current.values()].filter(
                    (entry) => entry.value !== entry.resetValue,
                ).length,
            );
        };
    }, []);
    const change = useCallback(
        (label: string, value: string) =>
            setDraft((current) => ({ ...current, [label]: value })),
        [],
    );
    const scope = useMemo(
        () => ({ searchTarget, draft, register, change }),
        [searchTarget, draft, register, change],
    );
    function toggle(next: boolean): void {
        setDraft(
            next
                ? Object.fromEntries(
                      [...fields.current].map(([label, field]) => [
                          label,
                          field.value,
                      ]),
                  )
                : null,
        );
        setOpen(next);
    }
    function apply(): void {
        const values = Object.fromEntries(
            [...fields.current].map(([label, field]) => [
                label,
                draft?.[label] ?? field.value,
            ]),
        );
        if (onApplyFilters) onApplyFilters(values);
        else
            for (const [label, field] of fields.current) {
                if (values[label] !== field.value) field.commit(values[label]);
            }
        toggle(false);
    }
    if (mobile) {
        return (
            <MobileFilterContext value={scope}>
                <div
                    data-slot="sticky-filter-bar"
                    className={cn('space-y-2 bg-background py-2', className)}
                >
                    {mobileTitle && (
                        <h2 className="text-lg font-semibold">{mobileTitle}</h2>
                    )}
                    <Dialog open={open} onOpenChange={toggle}>
                        <div className="flex items-center gap-2">
                            <div
                                ref={setSearchTarget}
                                className="min-w-0 flex-1"
                            />
                            <DialogTrigger asChild>
                                <Button
                                    variant="outline"
                                    className="h-11 shrink-0 gap-1.5 px-3"
                                    aria-label={
                                        active
                                            ? `Filter, ${active} aktif`
                                            : 'Filter'
                                    }
                                >
                                    <SlidersHorizontal
                                        aria-hidden
                                        className="size-4"
                                    />
                                    Filter
                                    {active > 0 && (
                                        <span className="text-xs tabular-nums">
                                            ({active})
                                        </span>
                                    )}
                                </Button>
                            </DialogTrigger>
                        </div>
                        {open ? (
                            <DialogContent className="max-h-[85dvh] gap-3 overflow-y-auto p-4 [&_[data-desktop-filter-reset]]:hidden">
                                <DialogHeader>
                                    <DialogTitle>Filter</DialogTitle>
                                    <DialogDescription>
                                        Pilih filter, lalu terapkan untuk
                                        memperbarui daftar.
                                    </DialogDescription>
                                </DialogHeader>
                                <div className="space-y-3">{children}</div>
                                <DialogFooter className="flex-row flex-wrap justify-end">
                                    <Button
                                        variant="ghost"
                                        className="min-h-11"
                                        onClick={() =>
                                            setDraft(
                                                Object.fromEntries(
                                                    [...fields.current].map(
                                                        ([label, field]) => [
                                                            label,
                                                            field.resetValue,
                                                        ],
                                                    ),
                                                ),
                                            )
                                        }
                                    >
                                        Reset
                                    </Button>
                                    <Button
                                        variant="outline"
                                        className="min-h-11"
                                        onClick={() => toggle(false)}
                                    >
                                        Batal
                                    </Button>
                                    <Button
                                        className="min-h-11"
                                        onClick={apply}
                                    >
                                        Terapkan
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        ) : (
                            <div hidden>{children}</div>
                        )}
                    </Dialog>
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
