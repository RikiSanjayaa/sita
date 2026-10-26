import {
    MentorshipSessionNotes,
    type MentorshipSessionNotesContext,
} from '@/components/mentorship-session-notes';
import { StatusBadge } from '@/components/patterns/status-badge';

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
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })} WITA`;
}

export function MonitoringSessionNotes({
    sessions,
}: {
    sessions: MentorshipSessionNotesContext[];
}) {
    return (
        <section
            aria-labelledby="monitoring-session-notes-heading"
            className="min-w-0 divide-y overflow-hidden rounded-xl border bg-card"
        >
            <div className="space-y-1 px-4 py-5 md:px-6">
                <h2
                    id="monitoring-session-notes-heading"
                    className="text-lg font-semibold text-balance"
                >
                    Catatan sesi bimbingan
                </h2>
                <p className="text-sm text-pretty text-muted-foreground">
                    Catatan mahasiswa dan dosen per sesi, termasuk riwayat yang
                    diarsipkan. Hanya dapat dibaca di sini.
                </p>
            </div>
            {sessions.length > 0 ? (
                sessions.map((session, index) => (
                    <article
                        key={`${session.id}-${index}`}
                        data-monitoring-session={session.id}
                        className="min-w-0 space-y-4 px-4 py-5 md:px-6"
                    >
                        <div className="space-y-2 [overflow-wrap:anywhere]">
                            <h3 className="text-base font-semibold">
                                {session.topic}
                            </h3>
                            <StatusBadge
                                domain="schedule"
                                status={session.status}
                            >
                                {scheduleStatusLabels[session.status] ??
                                    session.status}
                            </StatusBadge>
                            <dl className="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Mahasiswa
                                    </dt>
                                    <dd>{session.student}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Dosen
                                    </dt>
                                    <dd>{session.lecturer}</dd>
                                </div>
                                <div className="sm:col-span-2">
                                    <dt className="text-xs text-muted-foreground">
                                        Waktu sesi
                                    </dt>
                                    <dd className="tabular-nums">
                                        {session.scheduledAt ? (
                                            <time
                                                dateTime={session.scheduledAt}
                                            >
                                                {formatSessionDate(
                                                    session.scheduledAt,
                                                )}
                                            </time>
                                        ) : (
                                            'Waktu sesi tidak tercatat'
                                        )}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        <div className="max-w-prose min-w-0">
                            <MentorshipSessionNotes
                                sessionId={session.id}
                                notes={{
                                    ...session.sessionNotes,
                                    updateUrl: null,
                                }}
                            />
                        </div>
                    </article>
                ))
            ) : (
                <p className="px-4 py-5 text-sm text-muted-foreground md:px-6">
                    Belum ada catatan sesi bimbingan.
                </p>
            )}
        </section>
    );
}
