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
                <div className="rounded-lg border p-4">
                    {events.length === 0 ? (
                        <p className="py-8 text-center text-muted-foreground">
                            Tidak ada jadwal untuk ditampilkan
                        </p>
                    ) : (
                        <div className="space-y-3">
                            {events
                                .sort(
                                    (a, b) =>
                                        new Date(a.start).getTime() -
                                        new Date(b.start).getTime(),
                                )
                                .map((event) => (
                                    <div
                                        key={event.id}
                                        className="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors hover:bg-muted/50"
                                        onClick={() => onEventClick?.(event)}
                                    >
                                        <div
                                            className={cn(
                                                'mt-1 size-3 shrink-0 rounded-full',
                                            )}
                                            style={{
                                                backgroundColor: getEventColor(
                                                    event.status,
                                                ),
                                            }}
                                        />
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-medium">
                                                {event.topic}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {event.person}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
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
                                    </div>
                                ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
