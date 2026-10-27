import { Form, usePage } from '@inertiajs/react';
import { useId } from 'react';

import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useInitials } from '@/hooks/use-initials';
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

export function MentorshipSessionNotes({
    sessionId,
    notes,
}: {
    sessionId: number;
    notes: MentorshipSessionNotesData;
}) {
    const { auth } = usePage<SharedData>().props;
    const id = useId();
    const getInitials = useInitials();
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
            className="min-w-0 space-y-5"
        >
            <h3 id={headingId} className="text-base font-semibold">
                Catatan sesi bimbingan
            </h3>
            {notes.entries.length > 0 ? (
                <div className="divide-y">
                    {notes.entries.map((entry) => (
                        <article
                            key={entry.authorId}
                            className="min-w-0 space-y-4 py-5 first:pt-0 last:pb-0"
                        >
                            <header className="flex items-start gap-3">
                                <Avatar className="size-9 shrink-0">
                                    <AvatarFallback className="bg-primary-muted text-xs font-semibold text-primary-muted-foreground">
                                        {getInitials(entry.authorName)}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="min-w-0 flex-1 space-y-1 [overflow-wrap:anywhere]">
                                    <div className="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                        <h4 className="text-sm font-semibold">
                                            {entry.authorName}
                                        </h4>
                                        <span className="text-xs text-muted-foreground">
                                            {entry.authorRole}
                                        </span>
                                    </div>
                                    {entry.updatedAt && (
                                        <time
                                            dateTime={entry.updatedAt}
                                            className="block text-xs text-muted-foreground tabular-nums"
                                        >
                                            {formatNoteDate(entry.updatedAt)}
                                        </time>
                                    )}
                                </div>
                            </header>
                            <div className="rounded-lg bg-muted/50 px-4 py-3">
                                <p className="max-w-prose text-sm leading-7 [overflow-wrap:anywhere] whitespace-pre-wrap">
                                    {entry.body}
                                </p>
                            </div>
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
