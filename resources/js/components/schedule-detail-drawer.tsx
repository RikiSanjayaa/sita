import {
    Calendar,
    CheckCircle2,
    Clock,
    MapPin,
    User,
    XCircle,
} from 'lucide-react';
import type { ReactNode } from 'react';

import {
    MentorshipSessionNotes,
    type MentorshipSessionNotesData,
} from '@/components/mentorship-session-notes';
import { StatusBadge } from '@/components/patterns/status-badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

export type ScheduleDetail = {
    id: number | string;
    topic: string;
    person: string;
    personRole: 'lecturer' | 'student';
    start: string | null;
    end: string | null;
    location: string | null;
    status:
        | 'scheduled'
        | 'pending'
        | 'approved'
        | 'rescheduled'
        | 'rejected'
        | 'completed'
        | 'cancelled';
    notes?: string | null;
    sessionNotes?: MentorshipSessionNotesData;
    requestedBy?: string;
};

type ScheduleDetailDrawerProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    schedule: ScheduleDetail | null;
    onReschedule?: () => void;
    onCancel?: () => void;
    currentUserRole?: 'mahasiswa' | 'dosen';
    controls?: ReactNode;
};

const statusConfig: Record<
    ScheduleDetail['status'],
    { label: string; icon: typeof CheckCircle2 }
> = {
    scheduled: { label: 'Terjadwal', icon: Calendar },
    pending: { label: 'Menunggu Konfirmasi', icon: Clock },
    approved: { label: 'Disetujui', icon: CheckCircle2 },
    rescheduled: { label: 'Dijadwalkan Ulang', icon: Calendar },
    rejected: { label: 'Ditolak', icon: XCircle },
    completed: { label: 'Selesai', icon: CheckCircle2 },
    cancelled: { label: 'Dibatalkan', icon: XCircle },
};

function formatDate(dateStr: string): string {
    return `${new Date(dateStr).toLocaleString('id-ID', {
        timeZone: 'Asia/Makassar',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })} WITA`;
}

export function ScheduleDetailDrawer({
    open,
    onOpenChange,
    schedule,
    onReschedule,
    onCancel,
    currentUserRole,
    controls,
}: ScheduleDetailDrawerProps) {
    if (!schedule) return null;

    const config = statusConfig[schedule.status];
    const StatusIcon = config.icon;

    const canModify =
        (currentUserRole === 'dosen' ||
            (currentUserRole === 'mahasiswa' &&
                schedule.status === 'pending')) &&
        schedule.status !== 'completed' &&
        schedule.status !== 'cancelled';

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full gap-0 overflow-hidden sm:max-w-2xl [&>button]:flex [&>button]:size-9 [&>button]:items-center [&>button]:justify-center max-md:[&>button]:size-11">
                <SheetHeader className="shrink-0 border-b px-6 py-6 pr-16">
                    <SheetTitle className="min-w-0 text-xl leading-snug [overflow-wrap:anywhere]">
                        {schedule.topic}
                    </SheetTitle>
                    <SheetDescription>
                        {schedule.sessionNotes
                            ? 'Jadwal dan hasil pembahasan bimbingan.'
                            : 'Informasi jadwal.'}
                    </SheetDescription>
                </SheetHeader>

                <div className="min-h-0 flex-1 space-y-6 overflow-y-auto px-6 py-6">
                    <div className="flex items-center gap-2">
                        <StatusBadge
                            domain="schedule"
                            status={schedule.status}
                            className="gap-1"
                        >
                            <StatusIcon className="size-3" />
                            {config.label}
                        </StatusBadge>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="flex items-start gap-3">
                            <User
                                aria-hidden="true"
                                className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                            />
                            <div className="min-w-0 [overflow-wrap:anywhere]">
                                <p className="text-sm font-medium">
                                    {schedule.person}
                                </p>
                                <p className="text-xs text-muted-foreground capitalize">
                                    {schedule.personRole === 'lecturer'
                                        ? 'Dosen'
                                        : 'Mahasiswa'}
                                </p>
                            </div>
                        </div>

                        {schedule.start && (
                            <div className="flex items-start gap-3">
                                <Calendar
                                    aria-hidden="true"
                                    className="mt-0.5 size-4 text-muted-foreground"
                                />
                                <div>
                                    <p className="text-sm tabular-nums">
                                        {formatDate(schedule.start)}
                                    </p>
                                    {schedule.end && (
                                        <p className="text-xs text-muted-foreground">
                                            sampai {formatDate(schedule.end)}
                                        </p>
                                    )}
                                </div>
                            </div>
                        )}
                        {schedule.location && (
                            <div className="flex items-start gap-3">
                                <MapPin
                                    aria-hidden="true"
                                    className="mt-0.5 size-4 text-muted-foreground"
                                />
                                <p className="min-w-0 text-sm [overflow-wrap:anywhere]">
                                    {schedule.location}
                                </p>
                            </div>
                        )}
                        {schedule.notes && (
                            <div className="space-y-1 border-t pt-4 sm:col-span-2">
                                <p className="text-xs font-medium text-muted-foreground">
                                    Catatan penjadwalan
                                </p>
                                <p className="mt-1 text-sm [overflow-wrap:anywhere] whitespace-pre-wrap">
                                    {schedule.notes}
                                </p>
                            </div>
                        )}
                    </div>
                    {controls}

                    {schedule.sessionNotes && (
                        <div className="min-w-0 border-t pt-6">
                            <MentorshipSessionNotes
                                sessionId={Number(schedule.id)}
                                notes={schedule.sessionNotes}
                            />
                        </div>
                    )}
                </div>

                {canModify && (
                    <SheetFooter className="shrink-0 border-t px-6 py-4 sm:flex-row sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            className="max-md:h-11"
                            onClick={() => onOpenChange(false)}
                        >
                            Tutup
                        </Button>
                        {onReschedule && (
                            <Button
                                type="button"
                                variant="secondary"
                                className="max-md:h-11"
                                onClick={() => {
                                    onOpenChange(false);
                                    onReschedule();
                                }}
                            >
                                Jadwalkan Ulang
                            </Button>
                        )}
                        {onCancel && (
                            <Button
                                type="button"
                                variant="destructive"
                                className="max-md:h-11"
                                onClick={() => {
                                    onOpenChange(false);
                                    onCancel();
                                }}
                            >
                                Batalkan
                            </Button>
                        )}
                    </SheetFooter>
                )}
            </SheetContent>
        </Sheet>
    );
}
