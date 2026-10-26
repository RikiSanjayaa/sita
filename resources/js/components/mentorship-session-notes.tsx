import { Form, usePage } from '@inertiajs/react';
import { useId } from 'react';

import { DataSection } from '@/components/patterns/data-section';
import { StatusBadge } from '@/components/patterns/status-badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { type SharedData } from '@/types';

export type MentorshipSessionNotesData = {
    entries: {
        authorId: number;
        authorName: string;
        authorRole: 'Mahasiswa' | 'Dosen';
        body: string;
        updatedAt: string | null;
    }[];
    updateUrl: string | null;
    readOnlyReason: string | null;
};

export type MentorshipSessionNotesContext = {
    id: number;
    topic: string;
    status: string;
    scheduledAt: string | null;
    lecturer: string;
    student: string;
    studentUserId: number;
    lecturerUserId: number;
    sessionNotes: MentorshipSessionNotesData;
};

function formatNoteDate(value: string): string {
    return `${new Date(value).toLocaleString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'Asia/Makassar',
    })} WITA`;
}

const scheduleStatusLabels: Record<string, string> = {
    pending: 'Menunggu Konfirmasi',
    approved: 'Terjadwal',
    rescheduled: 'Dijadwalkan Ulang',
    completed: 'Selesai',
    rejected: 'Ditolak',
    cancelled: 'Dibatalkan',
};

export function MentorshipSessionNotes({
    sessionId,
    notes,
}: {
    sessionId: number;
    notes: MentorshipSessionNotesData;
}) {
    const { auth } = usePage<SharedData>().props;
    const id = useId();
    const headingId = `${id}-heading`;
    const fieldId = `${id}-body`;
    const errorId = `${id}-error`;
    const ownEntry = notes.entries.find(
        (entry) => entry.authorId === auth.user.id,
    );

    return (
        <section
            aria-labelledby={headingId}
            data-session-notes={sessionId}
            className="min-w-0 space-y-4"
        >
            <h3 id={headingId} className="text-sm font-semibold">
                Catatan sesi bimbingan
            </h3>
            {notes.entries.length > 0 ? (
                <div className="divide-y">
                    {notes.entries.map((entry) => (
                        <article
                            key={entry.authorId}
                            className="min-w-0 space-y-2 py-3 first:pt-0 last:pb-0"
                        >
                            <div className="space-y-1 [overflow-wrap:anywhere]">
                                <p className="text-sm font-medium">
                                    {entry.authorName}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {entry.authorRole}
                                </p>
                                {entry.updatedAt && (
                                    <p className="text-xs text-muted-foreground">
                                        Diperbarui{' '}
                                        <time dateTime={entry.updatedAt}>
                                            {formatNoteDate(entry.updatedAt)}
                                        </time>
                                    </p>
                                )}
                            </div>
                            <p className="text-sm leading-relaxed [overflow-wrap:anywhere] whitespace-pre-wrap">
                                {entry.body}
                            </p>
                        </article>
                    ))}
                </div>
            ) : (
                <p className="text-sm text-muted-foreground">
                    Belum ada catatan sesi bimbingan.
                </p>
            )}

            {notes.updateUrl ? (
                <Form
                    key={`${sessionId}-${ownEntry?.updatedAt ?? ''}`}
                    action={notes.updateUrl}
                    method="patch"
                    options={{ preserveScroll: true }}
                    className="space-y-3 border-t pt-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="space-y-2">
                                <Label htmlFor={fieldId}>
                                    Catatan sesi Anda
                                </Label>
                                <Textarea
                                    id={fieldId}
                                    name="body"
                                    defaultValue={ownEntry?.body ?? ''}
                                    rows={6}
                                    aria-invalid={!!errors.body}
                                    aria-describedby={
                                        errors.body ? errorId : undefined
                                    }
                                    className="min-h-36 resize-y [overflow-wrap:anywhere]"
                                />
                                {errors.body && (
                                    <p
                                        id={errorId}
                                        role="alert"
                                        className="text-sm text-destructive"
                                    >
                                        {errors.body}
                                    </p>
                                )}
                            </div>
                            <div className="flex flex-wrap items-center gap-3">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="max-md:h-11"
                                >
                                    {processing
                                        ? 'Menyimpan...'
                                        : 'Simpan catatan sesi'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            ) : notes.readOnlyReason ? (
                <p className="text-sm [overflow-wrap:anywhere] text-muted-foreground">
                    {notes.readOnlyReason}
                </p>
            ) : null}
        </section>
    );
}

export function ArchivedMentorshipSessionNotes({
    sessions,
}: {
    sessions: MentorshipSessionNotesContext[];
}) {
    if (sessions.length === 0) return null;

    return (
        <DataSection
            title="Arsip catatan sesi bimbingan"
            description="Catatan sesi terdahulu tetap dapat dibaca, tetapi tidak dapat diubah."
        >
            <div className="divide-y rounded-xl border">
                {sessions.map((session) => (
                    <article
                        key={session.id}
                        data-archived-session={session.id}
                        className="min-w-0 space-y-4 p-4 md:p-6"
                    >
                        <div className="space-y-2 [overflow-wrap:anywhere]">
                            <h3 className="font-medium">{session.topic}</h3>
                            <StatusBadge
                                domain="schedule"
                                status={session.status}
                            >
                                {scheduleStatusLabels[session.status] ??
                                    session.status}
                            </StatusBadge>
                            <p className="text-sm text-muted-foreground">
                                {session.student} · {session.lecturer}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {session.scheduledAt
                                    ? formatNoteDate(session.scheduledAt)
                                    : 'Waktu sesi tidak tercatat'}
                            </p>
                        </div>
                        <MentorshipSessionNotes
                            sessionId={session.id}
                            notes={{ ...session.sessionNotes, updateUrl: null }}
                        />
                    </article>
                ))}
            </div>
        </DataSection>
    );
}
