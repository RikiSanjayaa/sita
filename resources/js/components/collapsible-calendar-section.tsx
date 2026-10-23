import { ChevronDown } from 'lucide-react';
import { useEffect, useId, useMemo, useState, type ReactNode } from 'react';

import {
    BimbinganCalendar,
    summarizeSchedule,
    type BimbinganCalendarProps,
} from '@/components/bimbingan-calendar';
import { SectionCard } from '@/components/patterns/section-card';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const STORAGE_PREFIX = 'sita:calendar-open:';

type CollapsibleCalendarSectionProps = BimbinganCalendarProps & {
    /** Unique per page; the open/closed choice is remembered under this key. */
    storageKey: string;
    title: ReactNode;
    description?: ReactNode;
    /** Controls shown next to the calendar only while it is expanded. */
    toolbar?: ReactNode;
};

function readStoredOpen(storageKey: string): boolean {
    try {
        return localStorage.getItem(STORAGE_PREFIX + storageKey) === '1';
    } catch {
        return false;
    }
}

export function CollapsibleCalendarSection({
    storageKey,
    title,
    description,
    toolbar,
    events,
    ...calendarProps
}: CollapsibleCalendarSectionProps) {
    const contentId = useId();
    const [open, setOpen] = useState(false);

    // Read after mount so server and client markup agree; collapsed by default.
    useEffect(() => {
        setOpen(readStoredOpen(storageKey));
    }, [storageKey]);

    const toggle = () => {
        const next = !open;
        setOpen(next);

        try {
            localStorage.setItem(STORAGE_PREFIX + storageKey, next ? '1' : '0');
        } catch {
            // Storage unavailable (private mode): the choice just is not remembered.
        }
    };

    const summary = useMemo(() => summarizeSchedule(events), [events]);

    return (
        <SectionCard
            title={title}
            description={description}
            actions={
                <>
                    {!open && (
                        <p
                            data-testid="calendar-summary"
                            className="text-sm text-balance text-muted-foreground"
                        >
                            {summary.week} jadwal minggu ini, {summary.today}{' '}
                            hari ini
                        </p>
                    )}
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        aria-expanded={open}
                        aria-controls={contentId}
                        onClick={toggle}
                        className="gap-1.5 max-md:h-11"
                    >
                        {open ? 'Ciutkan kalender' : 'Buka kalender'}
                        <ChevronDown
                            className={cn(
                                'size-4 transition-transform',
                                open && 'rotate-180',
                            )}
                        />
                    </Button>
                </>
            }
            className={cn(!open && 'gap-0 py-4')}
            contentClassName={cn(!open && 'hidden')}
        >
            <div id={contentId} className="space-y-4">
                {open && toolbar}
                {open && (
                    <BimbinganCalendar events={events} {...calendarProps} />
                )}
            </div>
        </SectionCard>
    );
}
