import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    CheckCircle2,
    Clock,
    Inbox,
    MapPin,
    XCircle,
} from 'lucide-react';
import { useEffect, useState } from 'react';

import { EmptyState } from '@/components/empty-state';
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
    requestedAt: string;
    requestedForInput: string | null;
    studentNote: string | null;
    location: string | null;
    status: string;
};

type UpcomingSchedule = {
    id: number;
    mahasiswa: string;
    topic: string;
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
                Menunggu Konfirmasi
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
        const payload = {
            decision,
            scheduled_for: '',
            location,
            lecturer_note:
                decision === 'complete'
                    ? 'Sesi bimbingan telah selesai.'
                    : 'Sesi dibatalkan oleh dosen.',
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
                        lecturer_note:
                            errors.lecturer_note ??
                            current[scheduleId]?.lecturer_note,
                    },
                }));
            },
        });
    }

    return (
        <DosenLayout
            breadcrumbs={breadcrumbs}
            title="Jadwal Bimbingan"
            subtitle="Kelola permintaan jadwal dan agenda bimbingan"
        >
            <Head title="Jadwal Bimbingan Dosen" />

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
                            pendingRequests.map((item) => (
                                <div
                                    key={`${item.id}-${item.mahasiswa}`}
                                    className="rounded-xl border bg-background p-5 shadow-sm"
                                >
                                    <p className="text-base font-semibold">
                                        {item.mahasiswa}
                                    </p>
                                    <p className="mt-0.5 text-sm text-muted-foreground">
                                        {item.topic}
                                    </p>
                                    <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                                        <Badge
                                            variant="soft"
                                            className="bg-muted text-muted-foreground hover:bg-muted"
                                        >
                                            {item.requestedAt}
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
                                                Tanggal/Jam Konfirmasi atau
                                                Jadwal Ulang
                                            </Label>
                                            <Input
                                                id={`scheduled-${item.id}`}
                                                type="datetime-local"
                                                value={
                                                    decisionFormById[item.id]
                                                        ?.scheduled_for ??
                                                    item.requestedForInput ??
                                                    ''
                                                }
                                                onChange={(event) => {
                                                    setDecisionFormById(
                                                        (current) => ({
                                                            ...current,
                                                            [item.id]: {
                                                                scheduled_for:
                                                                    event.target
                                                                        .value,
                                                                location:
                                                                    current[
                                                                        item.id
                                                                    ]
                                                                        ?.location ??
                                                                    item.location ??
                                                                    'Google Meet',
                                                                lecturer_note:
                                                                    current[
                                                                        item.id
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
                                                                item.id
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
                                                    decisionFormById[item.id]
                                                        ?.location ??
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
                                                                        item.id
                                                                    ]
                                                                        ?.scheduled_for ??
                                                                    item.requestedForInput ??
                                                                    '',
                                                                location:
                                                                    event.target
                                                                        .value,
                                                                lecturer_note:
                                                                    current[
                                                                        item.id
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
                                            <Label htmlFor={`note-${item.id}`}>
                                                Feedback ke Mahasiswa{' '}
                                            </Label>
                                            <Textarea
                                                id={`note-${item.id}`}
                                                value={
                                                    decisionFormById[item.id]
                                                        ?.lecturer_note ?? ''
                                                }
                                                onChange={(event) => {
                                                    setDecisionFormById(
                                                        (current) => ({
                                                            ...current,
                                                            [item.id]: {
                                                                scheduled_for:
                                                                    current[
                                                                        item.id
                                                                    ]
                                                                        ?.scheduled_for ??
                                                                    item.requestedForInput ??
                                                                    '',
                                                                location:
                                                                    current[
                                                                        item.id
                                                                    ]
                                                                        ?.location ??
                                                                    item.location ??
                                                                    'Google Meet',
                                                                lecturer_note:
                                                                    event.target
                                                                        .value,
                                                            },
                                                        }),
                                                    );
                                                    setDecisionErrorsById(
                                                        (current) => ({
                                                            ...current,
                                                            [item.id]: {
                                                                ...current[
                                                                item.id
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
                                                disabled={form.processing}
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
                                                disabled={form.processing}
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
                                                disabled={form.processing}
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
                            ))
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
                                        <p className="text-base font-semibold">
                                            {item.mahasiswa}
                                        </p>
                                        <StatusBadge status={item.status} />
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {item.topic}
                                    </p>
                                    <div className="mt-3 grid gap-2 text-sm text-muted-foreground">
                                        <div className="inline-flex items-center gap-2">
                                            <CalendarClock className="size-4" />
                                            <span>
                                                {item.date} - {item.time}
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
                                        <p className="text-base font-semibold">
                                            {item.mahasiswa}
                                        </p>
                                        <StatusBadge status={item.status} />
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {item.topic}
                                    </p>
                                    <div className="mt-3 grid gap-2 text-sm text-muted-foreground">
                                        <div className="inline-flex items-center gap-2">
                                            <CalendarClock className="size-4" />
                                            <span>
                                                {item.date} - {item.time}
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
            </div>
        </DosenLayout>
    );
}
