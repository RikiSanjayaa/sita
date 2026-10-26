import {
    Calendar,
    CheckCircle2,
    Clock,
    MapPin,
    User,
    XCircle,
} from 'lucide-react';

import {
    MentorshipSessionNotes,
    type MentorshipSessionNotesData,
} from '@/components/mentorship-session-notes';
import { StatusBadge } from '@/components/patterns/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type ScheduleDetail = {
    id: number | string;
    topic: string;
    person: string;
    personRole: 'lecturer' | 'student';
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
    notes?: string | null;
    sessionNotes?: MentorshipSessionNotesData;
    requestedBy?: string;
};

type ScheduleDetailModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    schedule: ScheduleDetail | null;
    onReschedule?: () => void;
    onCancel?: () => void;
    currentUserRole?: 'mahasiswa' | 'dosen';
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
    return new Date(dateStr).toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function ScheduleDetailModal({
    open,
    onOpenChange,
    schedule,
    onReschedule,
    onCancel,
    currentUserRole,
}: ScheduleDetailModalProps) {
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
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[calc(100dvh-2rem)] max-w-md overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="min-w-0 pr-6 leading-snug [overflow-wrap:anywhere]">
                        {schedule.topic}
                    </DialogTitle>
                    <DialogDescription className="sr-only">
                        Detail jadwal
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
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

                    <div className="space-y-3">
                        <div className="flex items-start gap-3">
                            <User className="mt-0.5 size-4 text-muted-foreground" />
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

                        <div className="flex items-start gap-3">
                            <Calendar className="mt-0.5 size-4 text-muted-foreground" />
                            <div>
                                <p className="text-sm">
                                    {formatDate(schedule.start)}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    sampai {formatDate(schedule.end)}
                                </p>
                            </div>
                        </div>

                        <div className="flex items-start gap-3">
                            <MapPin className="mt-0.5 size-4 text-muted-foreground" />
                            <p className="min-w-0 text-sm [overflow-wrap:anywhere]">
                                {schedule.location}
                            </p>
                        </div>

                        {schedule.notes && (
                            <div className="border-t pt-3">
                                <p className="text-xs font-medium text-muted-foreground">
                                    Catatan penjadwalan
                                </p>
                                <p className="mt-1 text-sm [overflow-wrap:anywhere] whitespace-pre-wrap">
                                    {schedule.notes}
                                </p>
                            </div>
                        )}
                    </div>

                    {schedule.sessionNotes && (
                        <div className="min-w-0 border-t pt-4">
                            <MentorshipSessionNotes
                                sessionId={Number(schedule.id)}
                                notes={schedule.sessionNotes}
                            />
                        </div>
                    )}
                </div>

                {canModify && (
                    <DialogFooter className="gap-2 sm:gap-0">
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
                    </DialogFooter>
                )}
            </DialogContent>
        </Dialog>
    );
}
