import idLocale from '@fullcalendar/core/locales/id';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import FullCalendar from '@fullcalendar/react';
import timeGridPlugin from '@fullcalendar/timegrid';
import { Calendar as CalendarIcon, List } from 'lucide-react';
import { useMemo, useState } from 'react';

import { FilterPills } from '@/components/patterns/filter-pills';
import { StatusBadge } from '@/components/patterns/status-badge';
import { Button } from '@/components/ui/button';
import { statusTone, type StatusTone } from '@/lib/status-styles';
import { cn } from '@/lib/utils';

import { ErrorBoundary } from './error-boundary';

export type BimbinganEvent = {
    id: number | string;
    title: string;
    topic: string;
    person: string;
    category?: 'bimbingan' | 'ujian';
    start: string;
    end: string;
    location: string;
    status:
        | 'scheduled'
        | 'pending'
        | 'approved'
        | 'rescheduled'
        | 'rejected'
        | 'completed'
        | 'cancelled';
    personRole: 'lecturer' | 'student';
};

export type BimbinganCalendarProps = {
    events: BimbinganEvent[];
    onEventClick?: (event: BimbinganEvent) => void;
    defaultView?: 'calendar' | 'list';
    showLegend?: boolean;
    className?: string;
};

// Solid event fills follow the same domain tones as StatusBadge; values are CSS
// variables so FullCalendar's inline styles follow light/dark mode.
const toneFill: Record<StatusTone, { background: string; text: string }> = {
    neutral: {
        background: 'var(--muted-foreground)',
        text: 'var(--background)',
    },
    primary: {
        background: 'var(--primary)',
        text: 'var(--primary-foreground)',
    },
    success: {
        background: 'var(--success)',
        text: 'var(--success-foreground)',
    },
    warning: {
        background: 'var(--warning)',
        text: 'var(--warning-foreground)',
    },
    danger: { background: 'var(--danger)', text: 'var(--danger-foreground)' },
    info: { background: 'var(--info)', text: 'var(--info-foreground)' },
};

const statusLabels: Record<BimbinganEvent['status'], string> = {
    scheduled: 'Terjadwal',
    pending: 'Menunggu',
    approved: 'Disetujui',
    rescheduled: 'Diubah',
    rejected: 'Ditolak',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
};

function getEventFill(status: BimbinganEvent['status']) {
    return toneFill[statusTone('schedule', status)];
}

function startOfDay(d: Date): Date {
    return new Date(d.getFullYear(), d.getMonth(), d.getDate());
}

function startOfWeek(d: Date): Date {
    const day = d.getDay();
    const diff = d.getDate() - day + (day === 0 ? -6 : 1);

    return new Date(d.getFullYear(), d.getMonth(), diff);
}

function startOfMonth(d: Date): Date {
    return new Date(d.getFullYear(), d.getMonth(), 1);
}

function endOfDay(d: Date): Date {
    return new Date(
        d.getFullYear(),
        d.getMonth(),
        d.getDate(),
        23,
        59,
        59,
        999,
    );
}

function endOfWeek(d: Date): Date {
    const sw = startOfWeek(d);

    return new Date(
        sw.getFullYear(),
        sw.getMonth(),
        sw.getDate() + 6,
        23,
        59,
        59,
        999,
    );
}

function endOfMonth(d: Date): Date {
    return new Date(d.getFullYear(), d.getMonth() + 1, 0, 23, 59, 59, 999);
}

function CalendarWrapper({
    events,
    onEventClick,
    showLegend = true,
}: {
    events: BimbinganEvent[];
    onEventClick?: (event: BimbinganEvent) => void;
    showLegend?: boolean;
}) {
    const calendarEvents = useMemo(() => {
        return events.map((event) => ({
            id: String(event.id),
            title: `${event.topic} - ${event.person}`,
            start: event.start,
            end: event.end,
            backgroundColor: getEventFill(event.status).background,
            borderColor: getEventFill(event.status).background,
            textColor: getEventFill(event.status).text,
            extendedProps: { ...event },
        }));
    }, [events]);

    return (
        <ErrorBoundary>
            <div className="space-y-3">
                {showLegend && (
                    <div className="flex flex-wrap items-center gap-3 text-xs">
                        <span className="font-medium text-muted-foreground">
                            Keterangan:
                        </span>
                        {(
                            [
                                'pending',
                                'scheduled',
                                'approved',
                                'rescheduled',
                                'completed',
                                'rejected',
                                'cancelled',
                            ] as const
                        ).map((status) => (
                            <div
                                key={status}
                                className="flex items-center gap-1.5"
                            >
                                <div
                                    className="size-3 rounded-sm"
                                    style={{
                                        backgroundColor:
                                            getEventFill(status).background,
                                    }}
                                />
                                <span className="text-muted-foreground">
                                    {statusLabels[status]}
                                </span>
                            </div>
                        ))}
                    </div>
                )}
                <div className="rounded-lg border bg-card p-4">
                    <FullCalendar
                        plugins={[
                            dayGridPlugin,
                            timeGridPlugin,
                            interactionPlugin,
                        ]}
                        initialView="dayGridMonth"
                        locale={idLocale}
                        headerToolbar={{
                            left: 'prev,next today',
                            center: 'title',
                            right: 'dayGridMonth,timeGridWeek,timeGridDay',
                        }}
                        buttonText={{
                            today: 'Hari Ini',
                            month: 'Bulan',
                            week: 'Minggu',
                            day: 'Hari',
                        }}
                        events={calendarEvents}
                        eventClick={(info) => {
                            if (onEventClick) {
                                onEventClick(
                                    info.event.extendedProps as BimbinganEvent,
                                );
                            }
                        }}
                        height="auto"
                        eventTimeFormat={{
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: false,
                        }}
                        slotLabelFormat={{
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: false,
                        }}
                        eventContent={(arg) => {
                            const event = arg.event
                                .extendedProps as BimbinganEvent;
                            return (
                                <div
                                    className="overflow-hidden px-1.5 py-0.5"
                                    style={{
                                        backgroundColor: getEventFill(
                                            event.status,
                                        ).background,
                                        color: getEventFill(event.status).text,
                                        height: '100%',
                                    }}
                                >
                                    <div className="text-xs font-semibold">
                                        {arg.timeText}
                                    </div>
                                    <div className="truncate text-xs">
                                        {event.topic}
                                    </div>
                                    <div className="truncate text-xs opacity-80">
                                        {event.person}
                                    </div>
                                </div>
                            );
                        }}
                    />
                </div>
            </div>
        </ErrorBoundary>
    );
}

export function BimbinganCalendar({
    events,
    onEventClick,
    defaultView = 'calendar',
    showLegend = true,
    className,
}: BimbinganCalendarProps) {
    const [view, setView] = useState<'calendar' | 'list'>(defaultView);
    const [periodFilter, setPeriodFilter] = useState<
        'semua' | 'hari' | 'minggu' | 'bulan'
    >('semua');

    const todayKey = new Date().toDateString();

    const periodBounds = useMemo(() => {
        const today = new Date(todayKey);

        return {
            hari: { start: startOfDay(today), end: endOfDay(today) },
            minggu: { start: startOfWeek(today), end: endOfWeek(today) },
            bulan: { start: startOfMonth(today), end: endOfMonth(today) },
        };
    }, [todayKey]);

    const sortedEvents = useMemo(
        () =>
            [...events].sort(
                (a, b) =>
                    new Date(a.start).getTime() - new Date(b.start).getTime(),
            ),
        [events],
    );

    const filteredListEvents = useMemo(() => {
        if (periodFilter === 'semua') return sortedEvents;
        const { start, end } = periodBounds[periodFilter];
        return sortedEvents.filter((e) => {
            const t = new Date(e.start).getTime();
            return t >= start.getTime() && t <= end.getTime();
        });
    }, [sortedEvents, periodFilter, periodBounds]);

    const counts = useMemo(
        () => ({
            hari: sortedEvents.filter((e) => {
                const t = new Date(e.start).getTime();
                return (
                    t >= periodBounds.hari.start.getTime() &&
                    t <= periodBounds.hari.end.getTime()
                );
            }).length,
            minggu: sortedEvents.filter((e) => {
                const t = new Date(e.start).getTime();
                return (
                    t >= periodBounds.minggu.start.getTime() &&
                    t <= periodBounds.minggu.end.getTime()
                );
            }).length,
            bulan: sortedEvents.filter((e) => {
                const t = new Date(e.start).getTime();
                return (
                    t >= periodBounds.bulan.start.getTime() &&
                    t <= periodBounds.bulan.end.getTime()
                );
            }).length,
        }),
        [sortedEvents, periodBounds],
    );

    const periodTabs: {
        label: string;
        value: typeof periodFilter;
        count: number;
    }[] = [
        { label: 'Semua', value: 'semua', count: events.length },
        { label: 'Bulan Ini', value: 'bulan', count: counts.bulan },
        { label: 'Minggu Ini', value: 'minggu', count: counts.minggu },
        { label: 'Hari Ini', value: 'hari', count: counts.hari },
    ];

    const emptyListMessage =
        periodFilter === 'hari'
            ? 'Tidak ada jadwal hari ini'
            : periodFilter === 'minggu'
              ? 'Tidak ada jadwal minggu ini'
              : periodFilter === 'bulan'
                ? 'Tidak ada jadwal bulan ini'
                : 'Tidak ada jadwal untuk ditampilkan';

    return (
        <div className={cn('space-y-4', className)}>
            <div className="flex items-center justify-center gap-2">
                <Button
                    type="button"
                    variant={view === 'calendar' ? 'default' : 'outline'}
                    size="sm"
                    onClick={() => setView('calendar')}
                    className="gap-2"
                >
                    <CalendarIcon className="size-4" />
                    Kalender
                </Button>
                <Button
                    type="button"
                    variant={view === 'list' ? 'default' : 'outline'}
                    size="sm"
                    onClick={() => setView('list')}
                    className="gap-2"
                >
                    <List className="size-4" />
                    Daftar
                </Button>
            </div>

            {view === 'calendar' ? (
                <CalendarWrapper
                    events={events}
                    onEventClick={onEventClick}
                    showLegend={showLegend}
                />
            ) : (
                <div className="space-y-3">
                    <FilterPills
                        label="Filter periode jadwal"
                        value={periodFilter}
                        onValueChange={setPeriodFilter}
                        options={periodTabs}
                    />

                    <div className="rounded-lg border bg-card">
                        {filteredListEvents.length === 0 ? (
                            <p className="py-10 text-center text-sm text-muted-foreground">
                                {emptyListMessage}
                            </p>
                        ) : (
                            <div className="divide-y">
                                {filteredListEvents.map((event) => (
                                    <div
                                        key={event.id}
                                        className="flex cursor-pointer items-start gap-3 px-4 py-3 transition-colors hover:bg-muted/50"
                                        onClick={() => onEventClick?.(event)}
                                    >
                                        <div
                                            className="mt-1.5 size-2.5 shrink-0 rounded-full"
                                            style={{
                                                backgroundColor: getEventFill(
                                                    event.status,
                                                ).background,
                                            }}
                                        />
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium">
                                                {event.topic}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {event.person}
                                            </p>
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                {new Date(
                                                    event.start,
                                                ).toLocaleString('id-ID', {
                                                    day: 'numeric',
                                                    month: 'long',
                                                    year: 'numeric',
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                })}
                                            </p>
                                        </div>
                                        <StatusBadge
                                            domain="schedule"
                                            status={event.status}
                                            className="mt-1 shrink-0"
                                        >
                                            {statusLabels[event.status]}
                                        </StatusBadge>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

/** Counts events falling today and in the current (Monday-based) week. */
export function summarizeSchedule(
    events: BimbinganEvent[],
    now: Date = new Date(),
): { today: number; week: number } {
    const day = { start: startOfDay(now), end: endOfDay(now) };
    const week = { start: startOfWeek(now), end: endOfWeek(now) };
    const within = (t: number, range: { start: Date; end: Date }) =>
        t >= range.start.getTime() && t <= range.end.getTime();

    return events.reduce(
        (acc, event) => {
            const t = new Date(event.start).getTime();

            return {
                today: acc.today + (within(t, day) ? 1 : 0),
                week: acc.week + (within(t, week) ? 1 : 0),
            };
        },
        { today: 0, week: 0 },
    );
}
