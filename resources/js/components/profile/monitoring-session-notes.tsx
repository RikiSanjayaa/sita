import { ChevronDown, Eye } from 'lucide-react';
import { type ReactNode, useId, useRef, useState } from 'react';

import { RowIconButton } from '@/components/kaprodi/row-icon-button';
import { type MentorshipSessionNotesContext } from '@/components/mentorship-session-notes';
import { StatusBadge } from '@/components/patterns/status-badge';
import {
    ScheduleDetailDrawer,
    type ScheduleDetail,
} from '@/components/schedule-detail-drawer';
import { Button } from '@/components/ui/button';

const scheduleStatusLabels: Record<string, string> = {
    pending: 'Menunggu persetujuan',
    approved: 'Disetujui',
    scheduled: 'Terjadwal',
    rescheduled: 'Dijadwalkan ulang',
    rejected: 'Ditolak',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
};

function formatSessionDate(value: string): string {
    return `${new Date(value).toLocaleString('id-ID', {
        timeZone: 'Asia/Makassar',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })} WITA`;
}

export function MonitoringSessionNotes({
    lecturerId,
    lecturerName,
    sessions,
    children,
}: {
    lecturerId: number;
    lecturerName: string;
    sessions: MentorshipSessionNotesContext[];
    children: ReactNode;
}) {
    const [expanded, setExpanded] = useState(false);
    const [selected, setSelected] =
        useState<MentorshipSessionNotesContext | null>(null);
    const contentId = useId();
    const detailTrigger = useRef<HTMLButtonElement | null>(null);

    return (
        <div data-mentorship-lecturer={lecturerId}>
            <div className="flex min-w-0 items-center gap-2 pr-4 md:pr-6">
                <div className="min-w-0 flex-1">{children}</div>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    aria-label={`Sesi bimbingan dengan ${lecturerName}`}
                    aria-expanded={expanded}
                    aria-controls={expanded ? contentId : undefined}
                    className="shrink-0 text-muted-foreground max-md:h-11"
                    onClick={() => setExpanded(!expanded)}
                >
                    <span className="tabular-nums">{sessions.length} sesi</span>
                    <ChevronDown
                        aria-hidden="true"
                        className={`size-4 ${expanded ? 'rotate-180' : ''}`}
                    />
                </Button>
            </div>
            {expanded ? (
                <div id={contentId} className="border-t bg-muted/30">
                    <ul className="divide-y px-4 md:px-6">
                        {sessions.map((session) => (
                            <li
                                key={session.id}
                                data-monitoring-session={session.id}
                                className="grid min-w-0 grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1 py-2 md:grid-cols-[11rem_minmax(0,1fr)_auto_auto] md:gap-x-4"
                            >
                                <p className="text-xs text-muted-foreground tabular-nums max-md:col-start-1 max-md:row-start-2">
                                    {session.scheduledAt ? (
                                        <time dateTime={session.scheduledAt}>
                                            {formatSessionDate(
                                                session.scheduledAt,
                                            )}
                                        </time>
                                    ) : (
                                        'Waktu sesi tidak tercatat'
                                    )}
                                </p>
                                <p className="min-w-0 text-sm font-medium [overflow-wrap:anywhere] max-md:col-start-1 max-md:row-start-1">
                                    {session.topic}
                                </p>
                                <div className="flex flex-wrap items-center gap-x-3 gap-y-1 max-md:col-start-1 max-md:row-start-3 md:justify-end">
                                    <StatusBadge
                                        domain="schedule"
                                        status={session.status}
                                    >
                                        {scheduleStatusLabels[session.status] ??
                                            session.status}
                                    </StatusBadge>
                                    <span className="text-xs text-muted-foreground">
                                        {session.sessionNotes.entries.length > 0
                                            ? `${session.sessionNotes.entries.length} catatan tersedia`
                                            : 'Belum ada catatan sesi'}
                                    </span>
                                </div>
                                <RowIconButton
                                    label={`Detail sesi ${session.topic}`}
                                    className="max-md:col-start-2 max-md:row-span-3 max-md:row-start-1"
                                    onClick={(event) => {
                                        detailTrigger.current =
                                            event.currentTarget;
                                        setSelected(session);
                                    }}
                                >
                                    <Eye
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                </RowIconButton>
                            </li>
                        ))}
                    </ul>
                    {sessions.length === 0 ? (
                        <p className="px-4 py-3 text-sm text-muted-foreground md:px-6">
                            Belum ada sesi bimbingan dengan dosen ini.
                        </p>
                    ) : null}
                </div>
            ) : null}
            {selected ? (
                <ScheduleDetailDrawer
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setSelected(null);
                            requestAnimationFrame(() =>
                                detailTrigger.current?.focus(),
                            );
                        }
                    }}
                    schedule={{
                        id: selected.id,
                        topic: selected.topic,
                        person: selected.lecturer,
                        personRole: 'lecturer',
                        start: selected.scheduledAt,
                        end: null,
                        location: null,
                        status: selected.status as ScheduleDetail['status'],
                        sessionNotes: {
                            ...selected.sessionNotes,
                            updateUrl: null,
                        },
                    }}
                />
            ) : null}
        </div>
    );
}
