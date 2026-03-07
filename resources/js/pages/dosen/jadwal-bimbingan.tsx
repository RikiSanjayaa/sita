import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    CheckCircle2,
    Clock,
    Inbox,
    MapPin,
    Repeat,
    XCircle,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import {
    BimbinganCalendar,
    type BimbinganEvent,
} from '@/components/bimbingan-calendar';
import { EmptyState } from '@/components/empty-state';
import { ScheduleDetailModal } from '@/components/schedule-detail-modal';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import DosenLayout from '@/layouts/dosen-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dosen/dashboard' },
    { title: 'Jadwal Bimbingan', href: '/dosen/jadwal-bimbingan' },
];

type PendingRequest = {
    id: number;
    mahasiswa: string;
    topic: string;
    relationType: 'pembimbing' | 'penguji';
    requestedAt: string;
    requestedForInput: string | null;
    studentNote: string | null;
    location: string | null;
    status: string;
    isRecurring: boolean;
    recurringGroupId: string | null;
    recurringIndex: number | null;
    recurringCount: number | null;
};

type UpcomingSchedule = {
    id: number;
    mahasiswa: string;
    topic: string;
    relationType: 'pembimbing' | 'penguji';
    date: string;
    time: string;
    location: string;
    status: string;
    lecturerNote: string | null;
};

type HistorySchedule = {
    id: number;
    mahasiswa: string;
    topic: string;
    relationType: 'pembimbing' | 'penguji';
    date: string;
    time: string;
    location: string;
    status: string;
    lecturerNote: string | null;
};

type JadwalBimbinganProps = {
    pendingRequests: PendingRequest[];
    upcomingSchedules: UpcomingSchedule[];
    historySchedules: HistorySchedule[];
    flashMessage?: string | null;
};

type DecisionFormState = {
    scheduled_for: string;
    location: string;
    lecturer_note: string;
};

type DecisionFormErrors = {
    scheduled_for?: string;
    lecturer_note?: string;
};

function StatusBadge({ status }: { status: string }) {
    const normalizedStatus = status.toLowerCase();

    if (normalizedStatus === 'approved' || normalizedStatus === 'rescheduled') {
        return (
            <Badge
                variant="soft"
                className="gap-1 rounded-full bg-emerald-600/10 text-emerald-600 hover:bg-emerald-600/20"
            >
                <CheckCircle2 className="size-3.5" />
                Terjadwal
            </Badge>
        );
    }

    if (normalizedStatus === 'completed') {
        return (
            <Badge
                variant="soft"
                className="gap-1 rounded-full bg-blue-600/10 text-blue-600 hover:bg-blue-600/20"
            >
                <CheckCircle2 className="size-3.5" />
                Selesai
            </Badge>
        );
    }

    if (normalizedStatus === 'pending') {
        return (
            <Badge
                variant="soft"
                className="gap-1 rounded-full bg-amber-600/10 text-amber-600 hover:bg-amber-600/20"
            >
                <Clock className="size-3.5" />
                Menunggu
            </Badge>
        );
    }

    if (normalizedStatus === 'rejected') {
        return (
            <Badge
                variant="soft"
                className="gap-1 rounded-full bg-destructive/10 text-destructive hover:bg-destructive/20"
            >
                <XCircle className="size-3.5" />
                Ditolak
            </Badge>
        );
    }

    return (
        <Badge
            variant="outline"
            className="gap-1 rounded-full text-muted-foreground"
        >
            <XCircle className="size-3.5" />
            Dibatalkan
        </Badge>
    );
}

function RelationTypeBadge({
    relationType,
}: {
    relationType: 'pembimbing' | 'penguji';
}) {
    if (relationType === 'penguji') {
        return (
            <Badge
                variant="outline"
                className="gap-1 rounded-full border-purple-500/50 bg-purple-500/10 text-purple-600 hover:bg-purple-500/20 dark:text-purple-400"
            >
                Sebagai Penguji
            </Badge>
        );
    }

    return (
        <Badge
            variant="outline"
            className="gap-1 rounded-full border-cyan-500/50 bg-cyan-500/10 text-cyan-600 hover:bg-cyan-500/20 dark:text-cyan-400"
        >
            Sebagai Pembimbing
        </Badge>
    );
}

export default function DosenJadwalBimbinganPage() {
    const {
        pendingRequests,
        upcomingSchedules,
        historySchedules,
        flashMessage,
        auth,
    } = usePage<SharedData & JadwalBimbinganProps>().props;

    const form = useForm({
        decision: 'approve' as
            | 'approve'
            | 'reject'
            | 'reschedule'
            | 'complete'
            | 'cancel',
        scheduled_for: '',
        location: '',
        lecturer_note: '',
    });
    const [decisionFormById, setDecisionFormById] = useState<
        Record<number, DecisionFormState>
    >({});
    const [decisionErrorsById, setDecisionErrorsById] = useState<
        Record<number, DecisionFormErrors>
    >({});

    const [recurringFormById, setRecurringFormById] = useState<
        Record<string, { location: string; lecturer_note: string }>
    >({});
    const [recurringErrorsById, setRecurringErrorsById] = useState<
        Record<string, { lecturer_note?: string }>
    >({});

    const [selectedEvent, setSelectedEvent] = useState<{
        id: number;
        topic: string;
        person: string;
        personRole: 'lecturer' | 'student';
        start: string;
        end: string;
        location: string;
        status: 'pending' | 'approved' | 'rejected' | 'completed' | 'cancelled';
        notes?: string | null;
    } | null>(null);

    const [isDetailModalOpen, setIsDetailModalOpen] = useState(false);

    useEffect(() => {
        const userId = auth.user?.id;
        if (typeof window === 'undefined' || !window.Echo || !userId) {
            return;
        }

        const channelName = `schedule.user.${userId}`;
        const channel = window.Echo.private(channelName).listen(
            '.schedule.updated',
            () => {
                router.reload({
                    only: [
                        'pendingRequests',
                        'upcomingSchedules',
                        'historySchedules',
                    ],
                });
            },
        );

        return () => {
            channel.stopListening('.schedule.updated');
            window.Echo.leaveChannel(`private-${channelName}`);
        };
    }, [auth.user?.id]);

    function decide(
        scheduleId: number,
        decision: 'approve' | 'reject' | 'reschedule' | 'complete' | 'cancel',
        item: PendingRequest,
    ) {
        const input = decisionFormById[scheduleId] ?? {
            scheduled_for: item.requestedForInput ?? '',
            location: item.location ?? 'Google Meet',
            lecturer_note: '',
        };

        const nextErrors: DecisionFormErrors = {};
        if (
            (decision === 'approve' ||
                decision === 'reject' ||
                decision === 'reschedule') &&
            input.lecturer_note.trim() === ''
        ) {
            nextErrors.lecturer_note = 'Feedback wajib diisi';
        }
        if (decision === 'reschedule' && input.scheduled_for.trim() === '') {
            nextErrors.scheduled_for =
                'Tanggal/jam baru wajib diisi untuk jadwal ulang.';
        }
        if (Object.keys(nextErrors).length > 0) {
            setDecisionErrorsById((current) => ({
                ...current,
                [scheduleId]: nextErrors,
            }));
            return;
        }

        setDecisionErrorsById((current) => {
            const next = { ...current };
            delete next[scheduleId];
            return next;
        });

        const payload = {
            decision,
            scheduled_for:
                decision === 'reject'
                    ? ''
                    : input.scheduled_for || item.requestedForInput || '',
            location: input.location || item.location || 'Google Meet',
            lecturer_note:
                input.lecturer_note ??
                (decision === 'reject'
                    ? ''
                    : decision === 'reschedule'
                      ? ''
                      : 'Permintaan jadwal disetujui.'),
        };

        form.transform(() => ({
            ...payload,
            scheduled_for: payload.scheduled_for || null,
            location: payload.location || null,
            lecturer_note: payload.lecturer_note || null,
        }));

        form.post(`/dosen/jadwal-bimbingan/${scheduleId}/decision`, {
            preserveScroll: true,
            onError: (errors) => {
                setDecisionErrorsById((current) => ({
                    ...current,
                    [scheduleId]: {
                        ...current[scheduleId],
                        scheduled_for:
                            errors.scheduled_for ??
                            current[scheduleId]?.scheduled_for,
                        lecturer_note:
                            errors.lecturer_note ??
                            current[scheduleId]?.lecturer_note,
                    },
                }));
            },
            onSuccess: () => {
                setDecisionErrorsById((current) => {
                    const next = { ...current };
                    delete next[scheduleId];
                    return next;
                });
            },
        });
    }

    function closeSchedule(
        scheduleId: number,
        decision: 'complete' | 'cancel',
        location: string,
    ) {
        form.transform(() => ({
            decision,
            scheduled_for: null,
            location,
            lecturer_note: null,
        }));

        form.post(`/dosen/jadwal-bimbingan/${scheduleId}/decision`, {
            preserveScroll: true,
        });
    }

    function decideRecurringGroup(
        groupId: string,
        decision: 'approve' | 'reject',
        items: PendingRequest[],
    ) {
        const input = recurringFormById[groupId] ?? {
            location: items[0]?.location ?? 'Google Meet',
            lecturer_note: '',
        };

        if (input.lecturer_note.trim() === '') {
            setRecurringErrorsById((current) => ({
                ...current,
                [groupId]: { lecturer_note: 'Feedback wajib diisi' },
            }));
            return;
        }

        setRecurringErrorsById((current) => {
            const next = { ...current };
            delete next[groupId];
            return next;
        });

        form.transform(() => ({
            decision,
            scheduled_for: null,
            location: input.location,
            lecturer_note: input.lecturer_note,
        }));

        form.post(`/dosen/jadwal-bimbingan/recurring/${groupId}/decision`, {
            preserveScroll: true,
            onError: (errors) => {
                setRecurringErrorsById((current) => ({
                    ...current,
                    [groupId]: {
                        lecturer_note:
                            errors.lecturer_note ??
                            current[groupId]?.lecturer_note,
                    },
                }));
            },
            onSuccess: () => {
                setRecurringErrorsById((current) => {
                    const next = { ...current };
                    delete next[groupId];
                    return next;
                });
            },
        });
    }

    const groupedPendingRequests = useMemo(() => {
        const groups: Record<
            string,
            { isRecurringGroup: boolean; items: PendingRequest[] }
        > = {};

        pendingRequests.forEach((item) => {
            if (item.isRecurring && item.recurringGroupId) {
                if (!groups[item.recurringGroupId]) {
                    groups[item.recurringGroupId] = {
                        isRecurringGroup: true,
                        items: [],
                    };
                }
                groups[item.recurringGroupId].items.push(item);
            } else {
                groups[`single-${item.id}`] = {
                    isRecurringGroup: false,
                    items: [item],
                };
            }
        });

        return groups;
    }, [pendingRequests]);

    function formatDateForCalendar(dateInput: string | Date): string {
        if (typeof dateInput === 'string') {
            return dateInput;
        }

        const date = dateInput;
        if (isNaN(date.getTime())) return '';

        return date.toISOString();
    }

    function formatDateInIndonesian(dateInput: string | Date): string {
        const date =
            typeof dateInput === 'string' ? new Date(dateInput) : dateInput;
        if (isNaN(date.getTime())) return '';

        return date.toLocaleDateString('id-ID', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function formatDateTime(date: string, time: string): string {
        const parsedDate = new Date(`${date} ${time}`);
        if (isNaN(parsedDate.getTime())) return `${date} pukul ${time}`;

        return (
            parsedDate.toLocaleDateString('id-ID', {
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            }) + ` pukul ${time}`
        );
    }

    function parseDateTime(dateStr: string, timeStr: string): string {
        const combined = `${dateStr} ${timeStr}`;
        const date = new Date(combined);
        return formatDateForCalendar(date);
    }

    const calendarEvents: BimbinganEvent[] = [
        ...upcomingSchedules.map((schedule) => {
            const start = parseDateTime(schedule.date, schedule.time);
            const endDate = new Date(
                new Date(`${schedule.date} ${schedule.time}`).getTime() +
                    60 * 60 * 1000,
            );
            const end = formatDateForCalendar(endDate);

            return {
                id: schedule.id,
                title: schedule.topic,
                topic: schedule.topic,
                person: schedule.mahasiswa,
                start,
                end,
                location: schedule.location,
                status: schedule.status.toLowerCase() as BimbinganEvent['status'],
                personRole: 'student' as const,
            };
        }),
        ...historySchedules.map((schedule) => {
            const start = parseDateTime(schedule.date, schedule.time);
            const endDate = new Date(
                new Date(`${schedule.date} ${schedule.time}`).getTime() +
                    60 * 60 * 1000,
            );
            const end = formatDateForCalendar(endDate);

            return {
                id: schedule.id,
                title: schedule.topic,
                topic: schedule.topic,
                person: schedule.mahasiswa,
                start,
                end,
                location: schedule.location,
                status: schedule.status.toLowerCase() as BimbinganEvent['status'],
                personRole: 'student' as const,
            };
        }),
    ];

    function handleEventClick(event: BimbinganEvent) {
        const fullSchedule = [...upcomingSchedules, ...historySchedules].find(
            (s) => s.id === event.id,
        );

        if (fullSchedule) {
            setSelectedEvent({
                id: fullSchedule.id,
                topic: fullSchedule.topic,
                person: fullSchedule.mahasiswa,
                personRole: 'student',
                start: event.start,
                end: event.end,
                location: fullSchedule.location,
                status: fullSchedule.status.toLowerCase() as
                    | 'pending'
                    | 'approved'
                    | 'rejected'
                    | 'completed'
                    | 'cancelled',
                notes: fullSchedule.lecturerNote,
            });
            setIsDetailModalOpen(true);
        }
    }

    return (
        <DosenLayout
            breadcrumbs={breadcrumbs}
            title="Jadwal Bimbingan"
            subtitle="Kelola permintaan jadwal dan agenda bimbingan"
        >
            <Head title="Jadwal Bimbingan Dosen" />

            <div className="mx-auto grid w-full max-w-7xl gap-6 px-4 py-6 md:px-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Jadwal Bimbingan
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Kelola jadwal bimbingan skripsi mahasiswa bimbingan
                        </p>
                    </div>
                </div>
                <BimbinganCalendar
                    events={calendarEvents}
                    onEventClick={handleEventClick}
                    defaultView="calendar"
                    showLegend={false}
                />
            </div>

            <div className="mx-auto grid w-full max-w-7xl gap-6 px-4 py-6 md:px-6 lg:grid-cols-2 lg:gap-8 lg:py-8">
                <Card className="py-0 shadow-sm">
                    <CardHeader className="border-b bg-muted/20 px-6 py-4">
                        <CardTitle className="text-lg font-semibold">
                            Permintaan Menunggu Konfirmasi
                        </CardTitle>
                        <CardDescription>
                            Konfirmasi, jadwalkan ulang, atau tolak permintaan
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4 pb-6">
                        {flashMessage && (
                            <Alert>
                                <AlertTitle>Berhasil</AlertTitle>
                                <AlertDescription>
                                    {flashMessage}
                                </AlertDescription>
                            </Alert>
                        )}

                        {pendingRequests.length > 0 ? (
                            Object.entries(groupedPendingRequests).map(
                                ([groupKey, group]) => {
                                    const firstItem = group.items[0];

                                    if (group.isRecurringGroup) {
                                        return (
                                            <div
                                                key={groupKey}
                                                className="rounded-xl border bg-background p-5 shadow-sm"
                                            >
                                                <div className="flex items-center gap-2">
                                                    <p className="text-base font-semibold">
                                                        {firstItem.mahasiswa}
                                                    </p>
                                                    <Badge
                                                        variant="soft"
                                                        className="gap-1 rounded-full bg-blue-600/10 text-blue-600"
                                                    >
                                                        <Repeat className="size-3.5" />
                                                        {group.items.length}{' '}
                                                        Pertemuan
                                                    </Badge>
                                                    <RelationTypeBadge
                                                        relationType={
                                                            firstItem.relationType
                                                        }
                                                    />
                                                </div>
                                                <p className="mt-0.5 text-sm text-muted-foreground">
                                                    {firstItem.topic}
                                                </p>

                                                <div className="mt-3 rounded-lg border bg-muted/50 p-3">
                                                    <p className="mb-2 text-xs font-medium text-muted-foreground">
                                                        Jadwal yang diajukan:
                                                    </p>
                                                    <div className="space-y-1">
                                                        {group.items.map(
                                                            (item) => (
                                                                <div
                                                                    key={
                                                                        item.id
                                                                    }
                                                                    className="text-sm"
                                                                >
                                                                    {formatDateInIndonesian(
                                                                        item.requestedAt,
                                                                    )}
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                </div>

                                                {firstItem.studentNote && (
                                                    <div className="mt-3 rounded-lg border bg-muted/30 p-3 text-sm text-muted-foreground">
                                                        <span className="font-medium text-foreground">
                                                            Catatan mahasiswa:
                                                        </span>{' '}
                                                        {firstItem.studentNote}
                                                    </div>
                                                )}

                                                <div className="mt-3 grid gap-2">
                                                    <div className="grid gap-1">
                                                        <Label
                                                            htmlFor={`recurring-location-${groupKey}`}
                                                        >
                                                            Lokasi untuk Semua
                                                            Jadwal
                                                        </Label>
                                                        <Input
                                                            id={`recurring-location-${groupKey}`}
                                                            value={
                                                                recurringFormById[
                                                                    groupKey
                                                                ]?.location ??
                                                                firstItem.location ??
                                                                'Google Meet'
                                                            }
                                                            onChange={(event) =>
                                                                setRecurringFormById(
                                                                    (
                                                                        current,
                                                                    ) => ({
                                                                        ...current,
                                                                        [groupKey]:
                                                                            {
                                                                                location:
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                                lecturer_note:
                                                                                    current[
                                                                                        groupKey
                                                                                    ]
                                                                                        ?.lecturer_note ??
                                                                                    '',
                                                                            },
                                                                    }),
                                                                )
                                                            }
                                                        />
                                                    </div>

                                                    <div className="grid gap-1">
                                                        <Label
                                                            htmlFor={`recurring-note-${groupKey}`}
                                                        >
                                                            Feedback ke
                                                            Mahasiswa
                                                        </Label>
                                                        <Textarea
                                                            id={`recurring-note-${groupKey}`}
                                                            value={
                                                                recurringFormById[
                                                                    groupKey
                                                                ]
                                                                    ?.lecturer_note ??
                                                                ''
                                                            }
                                                            onChange={(
                                                                event,
                                                            ) => {
                                                                setRecurringFormById(
                                                                    (
                                                                        current,
                                                                    ) => ({
                                                                        ...current,
                                                                        [groupKey]:
                                                                            {
                                                                                location:
                                                                                    current[
                                                                                        groupKey
                                                                                    ]
                                                                                        ?.location ??
                                                                                    firstItem.location ??
                                                                                    'Google Meet',
                                                                                lecturer_note:
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                            },
                                                                    }),
                                                                );
                                                                setRecurringErrorsById(
                                                                    (
                                                                        current,
                                                                    ) => ({
                                                                        ...current,
                                                                        [groupKey]:
                                                                            {
                                                                                lecturer_note:
                                                                                    undefined,
                                                                            },
                                                                    }),
                                                                );
                                                            }}
                                                            placeholder="Contoh: Semua jadwal disetujui, tolong siapkan materi untuk setiap pertemuan."
                                                        />
                                                        {recurringErrorsById[
                                                            groupKey
                                                        ]?.lecturer_note && (
                                                            <p className="text-xs text-destructive">
                                                                {
                                                                    recurringErrorsById[
                                                                        groupKey
                                                                    ]
                                                                        ?.lecturer_note
                                                                }
                                                            </p>
                                                        )}
                                                    </div>

                                                    <div className="flex flex-wrap gap-2 pt-2">
                                                        <Button
                                                            size="sm"
                                                            variant="soft"
                                                            className="bg-destructive/10 font-semibold text-destructive hover:bg-destructive/20"
                                                            disabled={
                                                                form.processing
                                                            }
                                                            onClick={() =>
                                                                decideRecurringGroup(
                                                                    groupKey,
                                                                    'reject',
                                                                    group.items,
                                                                )
                                                            }
                                                        >
                                                            Tolak Semua
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            className="bg-primary font-semibold text-primary-foreground hover:bg-primary/90"
                                                            disabled={
                                                                form.processing
                                                            }
                                                            onClick={() =>
                                                                decideRecurringGroup(
                                                                    groupKey,
                                                                    'approve',
                                                                    group.items,
                                                                )
                                                            }
                                                        >
                                                            Konfirmasi Semua
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        );
                                    }

                                    const item = firstItem;
                                    return (
                                        <div
                                            key={`${item.id}-${item.mahasiswa}`}
                                            className="rounded-xl border bg-background p-5 shadow-sm"
                                        >
                                            <div className="flex items-center gap-2">
                                                <p className="text-base font-semibold">
                                                    {item.mahasiswa}
                                                </p>
                                                <RelationTypeBadge
                                                    relationType={
                                                        item.relationType
                                                    }
                                                />
                                            </div>
                                            <p className="mt-0.5 text-sm text-muted-foreground">
                                                {item.topic}
                                            </p>
                                            <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                                                <Badge
                                                    variant="soft"
                                                    className="bg-muted text-muted-foreground hover:bg-muted"
                                                >
                                                    {formatDateInIndonesian(
                                                        item.requestedAt,
                                                    )}
                                                </Badge>
                                            </div>
                                            {item.studentNote && (
                                                <div className="mt-3 rounded-lg border bg-muted/30 p-3 text-sm text-muted-foreground">
                                                    <span className="font-medium text-foreground">
                                                        Catatan mahasiswa:
                                                    </span>{' '}
                                                    {item.studentNote}
                                                </div>
                                            )}
                                            <div className="mt-3 grid gap-2">
                                                <div className="grid gap-1">
                                                    <Label
                                                        htmlFor={`scheduled-${item.id}`}
                                                    >
                                                        Tanggal/Jam Konfirmasi
                                                        atau Jadwal Ulang
                                                    </Label>
                                                    <Input
                                                        id={`scheduled-${item.id}`}
                                                        type="datetime-local"
                                                        value={
                                                            decisionFormById[
                                                                item.id
                                                            ]?.scheduled_for ??
                                                            item.requestedForInput ??
                                                            ''
                                                        }
                                                        onChange={(event) => {
                                                            setDecisionFormById(
                                                                (current) => ({
                                                                    ...current,
                                                                    [item.id]: {
                                                                        scheduled_for:
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        location:
                                                                            current[
                                                                                item
                                                                                    .id
                                                                            ]
                                                                                ?.location ??
                                                                            item.location ??
                                                                            'Google Meet',
                                                                        lecturer_note:
                                                                            current[
                                                                                item
                                                                                    .id
                                                                            ]
                                                                                ?.lecturer_note ??
                                                                            '',
                                                                    },
                                                                }),
                                                            );
                                                            setDecisionErrorsById(
                                                                (current) => ({
                                                                    ...current,
                                                                    [item.id]: {
                                                                        ...current[
                                                                            item
                                                                                .id
                                                                        ],
                                                                        scheduled_for:
                                                                            undefined,
                                                                    },
                                                                }),
                                                            );
                                                        }}
                                                    />
                                                    {decisionErrorsById[item.id]
                                                        ?.scheduled_for && (
                                                        <p className="text-xs text-destructive">
                                                            {
                                                                decisionErrorsById[
                                                                    item.id
                                                                ]?.scheduled_for
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                                <div className="grid gap-1">
                                                    <Label
                                                        htmlFor={`location-${item.id}`}
                                                    >
                                                        Lokasi
                                                    </Label>
                                                    <Input
                                                        id={`location-${item.id}`}
                                                        value={
                                                            decisionFormById[
                                                                item.id
                                                            ]?.location ??
                                                            item.location ??
                                                            'Google Meet'
                                                        }
                                                        onChange={(event) =>
                                                            setDecisionFormById(
                                                                (current) => ({
                                                                    ...current,
                                                                    [item.id]: {
                                                                        scheduled_for:
                                                                            current[
                                                                                item
                                                                                    .id
                                                                            ]
                                                                                ?.scheduled_for ??
                                                                            item.requestedForInput ??
                                                                            '',
                                                                        location:
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        lecturer_note:
                                                                            current[
                                                                                item
                                                                                    .id
                                                                            ]
                                                                                ?.lecturer_note ??
                                                                            '',
                                                                    },
                                                                }),
                                                            )
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-1">
                                                    <Label
                                                        htmlFor={`note-${item.id}`}
                                                    >
                                                        Feedback ke Mahasiswa
                                                    </Label>
                                                    <Textarea
                                                        id={`note-${item.id}`}
                                                        value={
                                                            decisionFormById[
                                                                item.id
                                                            ]?.lecturer_note ??
                                                            ''
                                                        }
                                                        onChange={(event) => {
                                                            setDecisionFormById(
                                                                (current) => ({
                                                                    ...current,
                                                                    [item.id]: {
                                                                        scheduled_for:
                                                                            current[
                                                                                item
                                                                                    .id
                                                                            ]
                                                                                ?.scheduled_for ??
                                                                            item.requestedForInput ??
                                                                            '',
                                                                        location:
                                                                            current[
                                                                                item
                                                                                    .id
                                                                            ]
                                                                                ?.location ??
                                                                            item.location ??
                                                                            'Google Meet',
                                                                        lecturer_note:
                                                                            event
                                                                                .target
                                                                                .value,
                                                                    },
                                                                }),
                                                            );
                                                            setDecisionErrorsById(
                                                                (current) => ({
                                                                    ...current,
                                                                    [item.id]: {
                                                                        ...current[
                                                                            item
                                                                                .id
                                                                        ],
                                                                        lecturer_note:
                                                                            undefined,
                                                                    },
                                                                }),
                                                            );
                                                        }}
                                                        placeholder="Contoh: Tolong lengkapi data pada Bab 3 sebelum bimbingan berikutnya."
                                                    />
                                                    {decisionErrorsById[item.id]
                                                        ?.lecturer_note && (
                                                        <p className="text-xs text-destructive">
                                                            {
                                                                decisionErrorsById[
                                                                    item.id
                                                                ]?.lecturer_note
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                                <div className="flex flex-wrap gap-2 pt-2">
                                                    <Button
                                                        size="sm"
                                                        variant="soft"
                                                        className="font-semibold"
                                                        disabled={
                                                            form.processing
                                                        }
                                                        onClick={() =>
                                                            decide(
                                                                item.id,
                                                                'reschedule',
                                                                item,
                                                            )
                                                        }
                                                    >
                                                        Jadwalkan Ulang
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="soft"
                                                        className="bg-destructive/10 font-semibold text-destructive hover:bg-destructive/20"
                                                        disabled={
                                                            form.processing
                                                        }
                                                        onClick={() =>
                                                            decide(
                                                                item.id,
                                                                'reject',
                                                                item,
                                                            )
                                                        }
                                                    >
                                                        Tolak
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        className="bg-primary font-semibold text-primary-foreground hover:bg-primary/90"
                                                        disabled={
                                                            form.processing
                                                        }
                                                        onClick={() =>
                                                            decide(
                                                                item.id,
                                                                'approve',
                                                                item,
                                                            )
                                                        }
                                                    >
                                                        Konfirmasi
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                },
                            )
                        ) : (
                            <EmptyState
                                icon={Inbox}
                                title="Belum ada permintaan baru"
                                description="Permintaan bimbingan dari mahasiswa akan tampil di sini."
                            />
                        )}
                    </CardContent>
                </Card>

                <Card className="py-0 shadow-sm">
                    <CardHeader className="border-b bg-muted/20 px-6 py-4">
                        <CardTitle className="text-lg font-semibold">
                            Jadwal Mendatang
                        </CardTitle>
                        <CardDescription>
                            Agenda bimbingan terkonfirmasi minggu ini
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4 pb-6">
                        {upcomingSchedules.length > 0 ? (
                            upcomingSchedules.map((item) => (
                                <div
                                    key={`${item.id}-${item.mahasiswa}`}
                                    className="rounded-xl border bg-background p-5 shadow-sm"
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="flex items-center gap-2">
                                            <p className="text-base font-semibold">
                                                {item.mahasiswa}
                                            </p>
                                            <RelationTypeBadge
                                                relationType={item.relationType}
                                            />
                                        </div>
                                        <StatusBadge status={item.status} />
                                    </div>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {item.topic}
                                    </p>
                                    <div className="mt-3 grid gap-2 text-sm text-muted-foreground">
                                        <div className="inline-flex items-center gap-2">
                                            <CalendarClock className="size-4" />
                                            <span>
                                                {formatDateTime(
                                                    item.date,
                                                    item.time,
                                                )}
                                            </span>
                                        </div>
                                        <div className="inline-flex items-center gap-2">
                                            <MapPin className="size-4" />
                                            <span>{item.location}</span>
                                        </div>
                                        {item.lecturerNote && (
                                            <div className="mt-1 rounded-lg border bg-muted/30 p-3 text-sm text-muted-foreground">
                                                <span className="font-medium text-foreground">
                                                    Catatan dosen:
                                                </span>{' '}
                                                {item.lecturerNote}
                                            </div>
                                        )}
                                    </div>
                                    <div className="mt-4 flex flex-wrap gap-2 border-t border-dashed pt-2">
                                        <Button
                                            size="sm"
                                            variant="soft"
                                            className="bg-destructive/10 font-semibold text-destructive hover:bg-destructive/20"
                                            disabled={form.processing}
                                            onClick={() =>
                                                closeSchedule(
                                                    item.id,
                                                    'cancel',
                                                    item.location,
                                                )
                                            }
                                        >
                                            Batalkan
                                        </Button>
                                        <Button
                                            size="sm"
                                            className="bg-primary font-semibold text-primary-foreground hover:bg-primary/90"
                                            disabled={form.processing}
                                            onClick={() =>
                                                closeSchedule(
                                                    item.id,
                                                    'complete',
                                                    item.location,
                                                )
                                            }
                                        >
                                            Tandai Selesai
                                        </Button>
                                    </div>
                                </div>
                            ))
                        ) : (
                            <EmptyState
                                icon={CalendarClock}
                                title="Belum ada jadwal mendatang"
                                description="Agenda yang sudah dikonfirmasi akan muncul di bagian ini."
                            />
                        )}
                    </CardContent>
                </Card>

                <Card className="py-0 shadow-sm lg:col-span-2">
                    <CardHeader className="border-b bg-muted/20 px-6 py-4">
                        <CardTitle className="text-lg font-semibold">
                            Riwayat Jadwal
                        </CardTitle>
                        <CardDescription>
                            Daftar jadwal yang ditolak, dibatalkan, atau telah
                            selesai
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 pb-6 sm:grid-cols-2">
                        {historySchedules.length > 0 ? (
                            historySchedules.map((item) => (
                                <div
                                    key={`${item.id}-${item.status}`}
                                    className="rounded-xl border bg-background p-5 shadow-sm"
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <div>
                                            <p className="text-base font-semibold">
                                                {item.mahasiswa}
                                            </p>
                                            <div className="mt-1">
                                                <RelationTypeBadge
                                                    relationType={
                                                        item.relationType
                                                    }
                                                />
                                            </div>
                                        </div>
                                        <StatusBadge status={item.status} />
                                    </div>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {item.topic}
                                    </p>
                                    <div className="mt-3 grid gap-2 text-sm text-muted-foreground">
                                        <div className="inline-flex items-center gap-2">
                                            <CalendarClock className="size-4" />
                                            <span>
                                                {formatDateTime(
                                                    item.date,
                                                    item.time,
                                                )}
                                            </span>
                                        </div>
                                        <div className="inline-flex items-center gap-2">
                                            <MapPin className="size-4" />
                                            <span>{item.location}</span>
                                        </div>
                                        {item.lecturerNote && (
                                            <div className="mt-1 rounded-lg border bg-muted/30 p-3 text-sm text-muted-foreground">
                                                <span className="font-medium text-foreground">
                                                    Catatan dosen:
                                                </span>{' '}
                                                {item.lecturerNote}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            ))
                        ) : (
                            <EmptyState
                                icon={CalendarClock}
                                title="Belum ada riwayat jadwal"
                                description="Riwayat sesi selesai, ditolak, atau dibatalkan akan muncul di sini."
                            />
                        )}
                    </CardContent>
                </Card>

                <ScheduleDetailModal
                    open={isDetailModalOpen}
                    onOpenChange={setIsDetailModalOpen}
                    schedule={selectedEvent}
                    currentUserRole="dosen"
                />
            </div>
        </DosenLayout>
    );
}
