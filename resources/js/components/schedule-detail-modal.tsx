import {
    Calendar,
    CheckCircle2,
    Clock,
    MapPin,
    User,
    XCircle,
} from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

type ScheduleDetail = {
    id: number;
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
    { label: string; color: string; icon: typeof CheckCircle2 }
> = {
    scheduled: {
        label: 'Terjadwal',
        color: 'bg-blue-500 text-white',
        icon: Calendar,
    },
    pending: {
        label: 'Menunggu Konfirmasi',
        color: 'bg-orange-500 text-white',
        icon: Clock,
    },
    approved: {
        label: 'Disetujui',
        color: 'bg-green-500 text-white',
        icon: CheckCircle2,
    },
    rescheduled: {
        label: 'Dijadwalkan Ulang',
        color: 'bg-sky-500 text-white',
        icon: Calendar,
    },
    rejected: {
        label: 'Ditolak',
        color: 'bg-red-500 text-white',
        icon: XCircle,
    },
    completed: {
        label: 'Selesai',
        color: 'bg-gray-500 text-white',
        icon: CheckCircle2,
    },
    cancelled: {
        label: 'Dibatalkan',
        color: 'bg-gray-400 text-white',
        icon: XCircle,
    },
};

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleString('en-GB', {
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
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <span className="truncate">{schedule.topic}</span>
                    </DialogTitle>
                    <DialogDescription className="sr-only">
                        Schedule details
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="flex items-center gap-2">
                        <Badge className={cn('gap-1', config.color)}>
                            <StatusIcon className="size-3" />
                            {config.label}
                        </Badge>
                    </div>

                    <div className="space-y-3">
                        <div className="flex items-start gap-3">
                            <User className="mt-0.5 size-4 text-muted-foreground" />
                            <div>
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
                                    to {formatDate(schedule.end)}
                                </p>
                            </div>
                        </div>

                        <div className="flex items-start gap-3">
                            <MapPin className="mt-0.5 size-4 text-muted-foreground" />
                            <p className="text-sm">{schedule.location}</p>
                        </div>

                        {schedule.notes && (
                            <div className="rounded-lg bg-muted/30 p-3">
                                <p className="text-xs font-medium text-muted-foreground">
                                    Notes
                                </p>
                                <p className="mt-1 text-sm">{schedule.notes}</p>
                            </div>
                        )}
                    </div>
                </div>

                {canModify && (
                    <DialogFooter className="gap-2 sm:gap-0">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Close
                        </Button>
                        {onReschedule && (
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => {
                                    onOpenChange(false);
                                    onReschedule();
                                }}
                            >
                                Reschedule
                            </Button>
                        )}
                        {onCancel && (
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={() => {
                                    onOpenChange(false);
                                    onCancel();
                                }}
                            >
                                Cancel
                            </Button>
                        )}
                    </DialogFooter>
                )}
            </DialogContent>
        </Dialog>
    );
}
