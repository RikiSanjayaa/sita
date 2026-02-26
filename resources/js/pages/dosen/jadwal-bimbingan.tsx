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
            <Badge className="gap-1 rounded-full bg-emerald-600 text-white hover:bg-emerald-600/90 dark:bg-emerald-500 dark:hover:bg-emerald-500/90">
                <CheckCircle2 className="size-3" />
                Terjadwal
            </Badge>
        );
    }

    if (normalizedStatus === 'completed') {
        return (
            <Badge className="gap-1 rounded-full bg-blue-600 text-white hover:bg-blue-600/90 dark:bg-blue-500 dark:hover:bg-blue-500/90">
                <CheckCircle2 className="size-3" />
                Selesai
            </Badge>
        );
    }

    if (normalizedStatus === 'pending') {
        return (
            <Badge variant="secondary" className="gap-1 rounded-full">
                <Clock className="size-3" />
                Menunggu Konfirmasi
            </Badge>
        );
    }

    if (normalizedStatus === 'rejected') {
        return (
            <Badge variant="destructive" className="gap-1 rounded-full">
                <XCircle className="size-3" />
                Ditolak
            </Badge>
        );
    }

    return (
        <Badge variant="outline" className="gap-1 rounded-full">
            <XCircle className="size-3" />
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

            <div className="mx-auto grid w-full max-w-7xl flex-1 gap-6 px-4 py-6 md:px-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Permintaan Menunggu Konfirmasi</CardTitle>
                        <CardDescription>
                            Konfirmasi, jadwalkan ulang, atau tolak permintaan
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
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
                                    className="rounded-lg border bg-background p-4"
                                >
                                    <p className="text-sm font-semibold">
                                        {item.mahasiswa}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {item.topic}
                                    </p>
                                    <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
                                        <Badge variant="outline">
                                            {item.requestedAt}
                                        </Badge>
                                    </div>
                                    {item.studentNote && (
                                        <p className="mt-2 rounded-md border bg-muted/30 p-2 text-xs text-muted-foreground">
                                            Catatan mahasiswa:{' '}
                                            {item.studentNote}
                                        </p>
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
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
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
                                                variant="outline"
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
                                                className="bg-primary text-primary-foreground hover:bg-primary/90"
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
                            <div className="rounded-xl border border-dashed bg-muted/20 p-6 text-center">
                                <span className="mx-auto mb-3 inline-flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                    <Inbox className="size-5" />
                                </span>
                                <p className="text-sm font-medium">
                                    Belum ada permintaan baru
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Permintaan bimbingan dari mahasiswa akan
                                    tampil di sini.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Jadwal Mendatang</CardTitle>
                        <CardDescription>
                            Agenda bimbingan terkonfirmasi minggu ini
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {upcomingSchedules.length > 0 ? (
                            upcomingSchedules.map((item) => (
                                <div
                                    key={`${item.id}-${item.mahasiswa}`}
                                    className="rounded-lg border bg-background p-4"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <p className="text-sm font-semibold">
                                            {item.mahasiswa}
                                        </p>
                                        <StatusBadge status={item.status} />
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {item.topic}
                                    </p>
                                    <div className="mt-2 grid gap-1 text-sm text-muted-foreground">
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
                                            <p className="rounded-md border bg-muted/30 p-2 text-xs">
                                                Catatan dosen:{' '}
                                                {item.lecturerNote}
                                            </p>
                                        )}
                                    </div>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        <Button
                                            size="sm"
                                            variant="outline"
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
                                            className="bg-primary text-primary-foreground hover:bg-primary/90"
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
                            <div className="rounded-xl border border-dashed bg-muted/20 p-6 text-center">
                                <span className="mx-auto mb-3 inline-flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                    <CalendarClock className="size-5" />
                                </span>
                                <p className="text-sm font-medium">
                                    Belum ada jadwal mendatang
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Agenda yang sudah dikonfirmasi akan muncul
                                    di bagian ini.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Riwayat Jadwal</CardTitle>
                        <CardDescription>
                            Daftar jadwal yang ditolak, dibatalkan, atau telah
                            selesai
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {historySchedules.length > 0 ? (
                            historySchedules.map((item) => (
                                <div
                                    key={`${item.id}-${item.status}`}
                                    className="rounded-lg border bg-background p-4"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <p className="text-sm font-semibold">
                                            {item.mahasiswa}
                                        </p>
                                        <StatusBadge status={item.status} />
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {item.topic}
                                    </p>
                                    <div className="mt-2 grid gap-1 text-sm text-muted-foreground">
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
                                            <p className="rounded-md border bg-muted/30 p-2 text-xs">
                                                Catatan dosen:{' '}
                                                {item.lecturerNote}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            ))
                        ) : (
                            <div className="rounded-xl border border-dashed bg-muted/20 p-6 text-center">
                                <span className="mx-auto mb-3 inline-flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                    <CalendarClock className="size-5" />
                                </span>
                                <p className="text-sm font-medium">
                                    Belum ada riwayat jadwal
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Riwayat sesi selesai, ditolak, atau
                                    dibatalkan akan muncul di sini.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </DosenLayout>
    );
}
