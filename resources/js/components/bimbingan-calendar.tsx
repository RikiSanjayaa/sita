import idLocale from '@fullcalendar/core/locales/id';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import FullCalendar from '@fullcalendar/react';
import timeGridPlugin from '@fullcalendar/timegrid';
import { Calendar as CalendarIcon, List } from 'lucide-react';
import { useMemo, useState } from 'react';

import { Button } from '@/components/ui/button';
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

const statusColors: Record<BimbinganEvent['status'], string> = {
    scheduled: '#2563eb',
    pending: '#f59e0b',
    approved: '#22c55e',
    rescheduled: '#3b82f6',
    rejected: '#ef4444',
    completed: '#6b7280',
    cancelled: '#9ca3af',
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

function getEventColor(status: BimbinganEvent['status']): string {
    return statusColors[status] ?? '#3b82f6';
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
            backgroundColor: getEventColor(event.status),
            borderColor: getEventColor(event.status),
            textColor: 'white',
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
                                        backgroundColor: statusColors[status],
                                    }}
                                />
                                <span className="text-muted-foreground">
                                    {statusLabels[status]}
                                </span>
                            </div>
                        ))}
                    </div>
                )}
                <div className="rounded-lg border bg-card p-4 shadow-sm">
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
                                        backgroundColor: getEventColor(
                                            event.status,
                                        ),
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

    const now = new Date();

    function startOfDay(d: Date) {
        return new Date(d.getFullYear(), d.getMonth(), d.getDate());
    }
    function startOfWeek(d: Date) {
        const day = d.getDay();
        const diff = d.getDate() - day + (day === 0 ? -6 : 1);
        return new Date(d.getFullYear(), d.getMonth(), diff);
    }
    function startOfMonth(d: Date) {
        return new Date(d.getFullYear(), d.getMonth(), 1);
    }
    function endOfDay(d: Date) {
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
    function endOfWeek(d: Date) {
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
    function endOfMonth(d: Date) {
        return new Date(d.getFullYear(), d.getMonth() + 1, 0, 23, 59, 59, 999);
    }

    const periodBounds = useMemo(() => {
        return {
            hari: { start: startOfDay(now), end: endOfDay(now) },
            minggu: { start: startOfWeek(now), end: endOfWeek(now) },
            bulan: { start: startOfMonth(now), end: endOfMonth(now) },
        };
    }, [now.toDateString()]);

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
                    <div className="flex flex-wrap gap-1.5">
                        {periodTabs.map((tab) => (
                            <button
                                key={tab.value}
                                type="button"
                                onClick={() => setPeriodFilter(tab.value)}
                                className={cn(
                                    'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium transition-colors',
                                    periodFilter === tab.value
                                        ? 'bg-primary text-primary-foreground shadow-sm'
                                        : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground',
                                )}
                            >
                                {tab.label}
                                <span
                                    className={cn(
                                        'rounded-full px-1.5 py-0.5 text-[10px] leading-none font-bold',
                                        periodFilter === tab.value
                                            ? 'bg-white/20 text-white'
                                            : 'bg-background text-foreground',
                                    )}
                                >
                                    {tab.count}
                                </span>
                            </button>
                        ))}
                    </div>

                    <div className="rounded-lg border bg-card shadow-sm">
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
                                                backgroundColor: getEventColor(
                                                    event.status,
                                                ),
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
                                        <span
                                            className="mt-1 shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold text-white"
                                            style={{
                                                backgroundColor: getEventColor(
                                                    event.status,
                                                ),
                                            }}
                                        >
                                            {statusLabels[event.status]}
                                        </span>
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
