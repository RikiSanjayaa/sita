import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Calendar,
    CheckCircle2,
    Clock,
    Inbox,
    MapPin,
    Plus,
    Repeat,
    Send,
    Users,
    XCircle,
} from 'lucide-react';
import { useEffect, useState } from 'react';

import {
    BimbinganCalendar,
    type BimbinganEvent,
} from '@/components/bimbingan-calendar';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { dashboard, jadwalBimbingan } from '@/routes';
import { type BreadcrumbItem, type SharedData } from '@/types';

type ScheduleStatus =
    | 'pending'
    | 'approved'
    | 'rescheduled'
    | 'rejected'
    | 'completed'
    | 'cancelled';

type UpcomingMeeting = {
    id: number;
    topic: string;
    lecturer: string;
    relationType: 'pembimbing' | 'penguji';
    requestedAt: string;
    scheduledAt: string | null;
    location: string;
    status: ScheduleStatus;
    lecturerNote: string | null;
};

type HistoryMeeting = {
    id: number;
    topic: string;
    lecturer: string;
    relationType: 'pembimbing' | 'penguji';
    scheduledAt: string;
    location: string;
    status: ScheduleStatus;
    lecturerNote: string | null;
};

type JadwalPageProps = {
    hasDosbing: boolean;
    advisors: Array<{
        assignmentId: number;
        lecturerUserId: number;
        lecturerName: string;
        advisorType: string;
    }>;
    upcomingMeetings: UpcomingMeeting[];
    historyMeetings: HistoryMeeting[];
    flashMessage?: string | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Jadwal Bimbingan',
        href: jadwalBimbingan().url,
    },
];

function StatusBadge({ status }: { status: ScheduleStatus }) {
    if (status === 'completed') {
        return (
            <Badge className="gap-1 rounded-full bg-blue-600 text-white hover:bg-blue-600/90 dark:bg-blue-500 dark:hover:bg-blue-500/90">
                <CheckCircle2 className="size-3" />
                Selesai
            </Badge>
        );
    }

    if (status === 'cancelled') {
        return (
            <Badge variant="outline" className="gap-1 rounded-full">
                <XCircle className="size-3" />
                Dibatalkan
            </Badge>
        );
    }

    if (status === 'approved' || status === 'rescheduled') {
        return (
            <Badge className="gap-1 rounded-full bg-emerald-600 text-white hover:bg-emerald-600/90 dark:bg-emerald-500 dark:hover:bg-emerald-500/90">
                <CheckCircle2 className="size-3" />
                Terjadwal
            </Badge>
        );
    }

    if (status === 'pending') {
        return (
            <Badge variant="secondary" className="gap-1 rounded-full">
                <Clock className="size-3" />
                Menunggu Konfirmasi
            </Badge>
        );
    }

    return (
        <Badge variant="destructive" className="gap-1 rounded-full">
            <XCircle className="size-3" />
            Ditolak
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
                Penguji
            </Badge>
        );
    }

    return (
        <Badge
            variant="outline"
            className="gap-1 rounded-full border-cyan-500/50 bg-cyan-500/10 text-cyan-600 hover:bg-cyan-500/20 dark:text-cyan-400"
        >
            Pembimbing
        </Badge>
    );
}

export default function JadwalBimbinganPage() {
    const page = usePage<SharedData & JadwalPageProps>();
    const query = page.url.split('?')[1] ?? '';
    const [isAjukanOpen, setIsAjukanOpen] = useState(
        new URLSearchParams(query).get('open') === 'ajukan',
    );

    const form = useForm({
        topic: '',
        lecturer_user_id: '',
        requested_for: '',
        meeting_type: 'offline',
        student_note: '',
        is_recurring: false,
        recurring_pattern: 'weekly',
        recurring_count: 4,
    });

    useEffect(() => {
        if (
            form.data.lecturer_user_id === '' &&
            page.props.advisors.length > 0
        ) {
            form.setData(
                'lecturer_user_id',
                String(page.props.advisors[0].lecturerUserId),
            );
        }
    }, [form, form.data.lecturer_user_id, page.props.advisors]);

    useEffect(() => {
        const userId = page.props.auth.user?.id;
        if (typeof window === 'undefined' || !window.Echo || !userId) {
            return;
        }

        const channelName = `schedule.user.${userId}`;
        const channel = window.Echo.private(channelName).listen(
            '.schedule.updated',
            () => {
                router.reload({
                    only: ['upcomingMeetings', 'historyMeetings'],
                });
            },
        );

        return () => {
            channel.stopListening('.schedule.updated');
            window.Echo.leaveChannel(`private-${channelName}`);
        };
    }, [page.props.auth.user?.id]);

    function submitRequest() {
        form.post('/mahasiswa/jadwal-bimbingan', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setIsAjukanOpen(false);
            },
        });
    }

    const hasUpcomingMeetings = page.props.upcomingMeetings.length > 0;
    const hasHistoryMeetings = page.props.historyMeetings.length > 0;

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

    const formatDate = (dateStr: string | null) => {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    function formatDateForCalendar(dateInput: string | Date): string {
        if (typeof dateInput === 'string') {
            return dateInput;
        }

        if (isNaN(dateInput.getTime())) {
            return '';
        }

        return dateInput.toISOString();
    }

    const calendarEvents: BimbinganEvent[] = [
        ...page.props.upcomingMeetings.flatMap((meeting) => {
            const startDate = meeting.scheduledAt || meeting.requestedAt;

            if (!startDate) {
                return [];
            }

            const start = formatDateForCalendar(startDate);

            if (!start) {
                return [];
            }

            const endDate = new Date(
                new Date(startDate).getTime() + 60 * 60 * 1000,
            );

            return [
                {
                    id: meeting.id,
                    title: meeting.topic,
                    topic: meeting.topic,
                    person: meeting.lecturer,
                    start,
                    end: formatDateForCalendar(endDate),
                    location: meeting.location,
                    status: meeting.status as BimbinganEvent['status'],
                    personRole: 'lecturer' as const,
                },
            ];
        }),
        ...page.props.historyMeetings.flatMap((meeting) => {
            if (!meeting.scheduledAt) {
                return [];
            }

            const start = formatDateForCalendar(meeting.scheduledAt);

            if (!start) {
                return [];
            }

            const endDate = new Date(
                new Date(meeting.scheduledAt).getTime() + 60 * 60 * 1000,
            );

            return [
                {
                    id: meeting.id,
                    title: meeting.topic,
                    topic: meeting.topic,
                    person: meeting.lecturer,
                    start,
                    end: formatDateForCalendar(endDate),
                    location: meeting.location,
                    status: meeting.status as BimbinganEvent['status'],
                    personRole: 'lecturer' as const,
                },
            ];
        }),
    ];

    function handleEventClick(event: BimbinganEvent) {
        const fullMeeting = [
            ...page.props.upcomingMeetings,
            ...page.props.historyMeetings,
        ].find((meeting) => meeting.id === event.id);

        if (!fullMeeting) {
            return;
        }

        setSelectedEvent({
            id: fullMeeting.id,
            topic: fullMeeting.topic,
            person: fullMeeting.lecturer,
            personRole: 'lecturer',
            start: event.start,
            end: event.end,
            location: fullMeeting.location,
            status: fullMeeting.status as
                | 'pending'
                | 'approved'
                | 'rejected'
                | 'completed'
                | 'cancelled',
            notes: fullMeeting.lecturerNote,
        });
        setIsDetailModalOpen(true);
    }

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Jadwal Bimbingan"
            subtitle="Kelola jadwal bimbingan skripsi dengan dosen pembimbing"
        >
            <Head title="Jadwal Bimbingan" />

            <Dialog open={isAjukanOpen} onOpenChange={setIsAjukanOpen}>
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Ajukan Jadwal Bimbingan</DialogTitle>
                        <DialogDescription>
                            Isi formulir untuk mengajukan jadwal bimbingan
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        className="grid gap-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            submitRequest();
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="topic">Topik Bimbingan</Label>
                            <Input
                                id="topic"
                                value={form.data.topic}
                                onChange={(event) =>
                                    form.setData('topic', event.target.value)
                                }
                                required
                                placeholder="Contoh: Review Bab 3 - Metodologi Penelitian"
                            />
                            {form.errors.topic && (
                                <p className="text-xs text-destructive">
                                    {form.errors.topic}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="lecturer_user_id">
                                Dosen Pembimbing Tujuan
                            </Label>
                            <Select
                                value={form.data.lecturer_user_id}
                                onValueChange={(value) =>
                                    form.setData('lecturer_user_id', value)
                                }
                            >
                                <SelectTrigger id="lecturer_user_id">
                                    <SelectValue placeholder="Pilih dosen pembimbing" />
                                </SelectTrigger>
                                <SelectContent>
                                    {page.props.advisors.map((advisor) => (
                                        <SelectItem
                                            key={`${advisor.assignmentId}-${advisor.lecturerUserId}`}
                                            value={String(
                                                advisor.lecturerUserId,
                                            )}
                                        >
                                            {advisor.lecturerName} (
                                            {advisor.advisorType ===
                                                'primary' && 'Pembimbing 1'}
                                            {advisor.advisorType ===
                                                'secondary' && 'Pembimbing 2'}
                                            {advisor.advisorType ===
                                                'penguji' && 'Penguji'}
                                            )
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.lecturer_user_id && (
                                <p className="text-xs text-destructive">
                                    {form.errors.lecturer_user_id}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="requested_for">
                                Tanggal & Waktu Preferensi
                            </Label>
                            <Input
                                id="requested_for"
                                type="datetime-local"
                                value={form.data.requested_for}
                                onChange={(event) =>
                                    form.setData(
                                        'requested_for',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                            {form.errors.requested_for && (
                                <p className="text-xs text-destructive">
                                    {form.errors.requested_for}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="meeting_type">Tipe Bimbingan</Label>
                            <Select
                                value={form.data.meeting_type}
                                onValueChange={(value) =>
                                    form.setData('meeting_type', value)
                                }
                            >
                                <SelectTrigger id="meeting_type">
                                    <SelectValue placeholder="Pilih tipe bimbingan" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="offline">
                                        Offline (Tatap Muka)
                                    </SelectItem>
                                    <SelectItem value="online">
                                        Online
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            {form.errors.meeting_type && (
                                <p className="text-xs text-destructive">
                                    {form.errors.meeting_type}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-3 rounded-lg border p-4">
                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="is_recurring"
                                    checked={form.data.is_recurring}
                                    onChange={(event) =>
                                        form.setData(
                                            'is_recurring',
                                            event.target.checked,
                                        )
                                    }
                                    className="size-4 rounded border-input"
                                />
                                <Label
                                    htmlFor="is_recurring"
                                    className="flex items-center gap-2 font-medium"
                                >
                                    <Repeat className="size-4" />
                                    Jadwalkan Berulang
                                </Label>
                            </div>

                            {form.data.is_recurring && (
                                <div className="grid gap-3 pl-6">
                                    <div className="grid gap-2">
                                        <Label htmlFor="recurring_pattern">
                                            Pola Pengulangan
                                        </Label>
                                        <Select
                                            value={form.data.recurring_pattern}
                                            onValueChange={(value) =>
                                                form.setData(
                                                    'recurring_pattern',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger id="recurring_pattern">
                                                <SelectValue placeholder="Pilih pola" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="weekly">
                                                    Mingguan (setiap minggu)
                                                </SelectItem>
                                                <SelectItem value="biweekly">
                                                    2 Mingguan (setiap 2 minggu)
                                                </SelectItem>
                                                <SelectItem value="monthly">
                                                    Bulanan (setiap bulan)
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="recurring_count">
                                            Jumlah Pertemuan
                                        </Label>
                                        <Select
                                            value={String(
                                                form.data.recurring_count,
                                            )}
                                            onValueChange={(value) =>
                                                form.setData(
                                                    'recurring_count',
                                                    parseInt(value, 10),
                                                )
                                            }
                                        >
                                            <SelectTrigger id="recurring_count">
                                                <SelectValue placeholder="Pilih jumlah" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {[2, 3, 4, 5, 6, 7, 8].map(
                                                    (num) => (
                                                        <SelectItem
                                                            key={num}
                                                            value={String(num)}
                                                        >
                                                            {num} pertemuan
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <p className="text-xs text-muted-foreground">
                                        Akan dibuat {form.data.recurring_count}{' '}
                                        jadwal dengan pola{' '}
                                        {form.data.recurring_pattern ===
                                            'weekly' && 'mingguan'}
                                        {form.data.recurring_pattern ===
                                            'biweekly' && '2 mingguan'}
                                        {form.data.recurring_pattern ===
                                            'monthly' && 'bulanan'}
                                        .
                                    </p>
                                </div>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="student_note">
                                Catatan Tambahan (Opsional)
                            </Label>
                            <Textarea
                                id="student_note"
                                value={form.data.student_note}
                                onChange={(event) =>
                                    form.setData(
                                        'student_note',
                                        event.target.value,
                                    )
                                }
                                placeholder="Jelaskan hal-hal yang ingin didiskusikan..."
                            />
                            {form.errors.student_note && (
                                <p className="text-xs text-destructive">
                                    {form.errors.student_note}
                                </p>
                            )}
                        </div>

                        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full sm:w-auto"
                                onClick={() => setIsAjukanOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                className="bg-primary text-primary-foreground hover:bg-primary/90"
                                disabled={form.processing}
                            >
                                <Send className="size-4" />
                                Kirim Permintaan
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <div className="mx-auto grid w-full max-w-7xl gap-6 px-4 py-6 md:px-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Jadwal Bimbingan
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Kelola jadwal bimbingan skripsi dengan dosen
                            pembimbing
                        </p>
                    </div>
                    {page.props.hasDosbing && (
                        <Button
                            type="button"
                            className="h-10 w-full gap-2 bg-primary text-primary-foreground hover:bg-primary/90 sm:w-auto"
                            onClick={() => setIsAjukanOpen(true)}
                        >
                            <Plus className="size-4" />
                            Ajukan Bimbingan
                        </Button>
                    )}
                </div>

                {!page.props.auth.activeRole || !page.props.hasDosbing ? (
                    <Card className="mt-4 flex flex-1 flex-col items-center justify-center p-8 text-center text-muted-foreground">
                        <Users className="mb-4 size-12 opacity-20" />
                        <h2 className="mb-2 text-xl font-semibold text-foreground">
                            Fitur Bimbingan Belum Aktif
                        </h2>
                        <p className="max-w-md">
                            Anda belum memiliki Dosen Pembimbing yang
                            ditugaskan. Fitur jadwal bimbingan akan otomatis
                            aktif setelah admin menetapkan dosen pembimbing
                            untuk Anda.
                        </p>
                    </Card>
                ) : (
                    <>
                        {page.props.flashMessage && (
                            <Alert>
                                <AlertTitle>Berhasil</AlertTitle>
                                <AlertDescription>
                                    {page.props.flashMessage}
                                </AlertDescription>
                            </Alert>
                        )}

                        <Card className="shadow-sm">
                            <CardHeader className="gap-1">
                                <CardTitle>Tampilan Jadwal</CardTitle>
                                <CardDescription>
                                    Pilih tampilan daftar untuk layar kecil,
                                    atau kalender jika ingin melihat sebaran
                                    jadwal secara menyeluruh.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <BimbinganCalendar
                                    events={calendarEvents}
                                    onEventClick={handleEventClick}
                                    defaultView="list"
                                />
                            </CardContent>
                        </Card>

                        <Card className="py-0 shadow-sm">
                            <CardHeader className="border-b bg-muted/20 px-6 py-4">
                                <CardTitle className="text-lg font-semibold">
                                    Bimbingan Akan Datang
                                </CardTitle>
                                <CardDescription>
                                    Jadwal bimbingan yang diajukan atau telah
                                    dikonfirmasi
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4 pb-6">
                                <div className="grid gap-3">
                                    {hasUpcomingMeetings ? (
                                        page.props.upcomingMeetings.map(
                                            (meeting) => (
                                                <div
                                                    key={meeting.id}
                                                    className="rounded-xl border bg-background p-4"
                                                >
                                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                                        <div className="min-w-0">
                                                            <div className="text-sm font-semibold break-words">
                                                                {meeting.topic}
                                                            </div>
                                                            <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                                                <span className="break-words">
                                                                    {
                                                                        meeting.lecturer
                                                                    }
                                                                </span>
                                                                <RelationTypeBadge
                                                                    relationType={
                                                                        meeting.relationType
                                                                    }
                                                                />
                                                            </div>
                                                        </div>
                                                        <div className="sm:shrink-0">
                                                            <StatusBadge
                                                                status={
                                                                    meeting.status
                                                                }
                                                            />
                                                        </div>
                                                    </div>

                                                    <div className="mt-4 grid gap-2 text-sm text-muted-foreground">
                                                        <div className="flex items-start gap-2">
                                                            <Calendar className="mt-0.5 size-4 shrink-0" />
                                                            <span className="break-words">
                                                                Preferensi:{' '}
                                                                {formatDate(
                                                                    meeting.requestedAt,
                                                                )}
                                                            </span>
                                                        </div>
                                                        <div className="flex items-start gap-2">
                                                            <Clock className="mt-0.5 size-4 shrink-0" />
                                                            <span className="break-words">
                                                                Terkonfirmasi:{' '}
                                                                {meeting.scheduledAt
                                                                    ? formatDate(
                                                                          meeting.scheduledAt,
                                                                      )
                                                                    : 'Menunggu konfirmasi dosen'}
                                                            </span>
                                                        </div>
                                                        <div className="flex items-start gap-2">
                                                            <MapPin className="mt-0.5 size-4 shrink-0" />
                                                            <span className="break-words">
                                                                {
                                                                    meeting.location
                                                                }
                                                            </span>
                                                        </div>
                                                        {meeting.lecturerNote && (
                                                            <div className="rounded-lg border bg-muted/30 p-3 text-xs">
                                                                Catatan dosen:{' '}
                                                                {
                                                                    meeting.lecturerNote
                                                                }
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            ),
                                        )
                                    ) : (
                                        <div className="rounded-xl border border-dashed bg-muted/20 p-6 text-center">
                                            <span className="mx-auto mb-3 inline-flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                                <Inbox className="size-5" />
                                            </span>
                                            <p className="text-sm font-medium">
                                                Belum ada jadwal mendatang
                                            </p>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                Ajukan jadwal baru untuk mulai
                                                sesi bimbingan berikutnya.
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="py-0 shadow-sm">
                            <CardHeader className="border-b bg-muted/20 px-6 py-4">
                                <CardTitle className="text-lg font-semibold">
                                    Riwayat Bimbingan
                                </CardTitle>
                                <CardDescription>
                                    Riwayat bimbingan yang sudah selesai /
                                    ditutup
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4 pb-6">
                                <div className="grid gap-3">
                                    {hasHistoryMeetings ? (
                                        page.props.historyMeetings.map(
                                            (row) => (
                                                <div
                                                    key={row.id}
                                                    className="rounded-xl border bg-background p-4"
                                                >
                                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                                        <div className="min-w-0">
                                                            <div className="text-sm font-semibold break-words">
                                                                {row.topic}
                                                            </div>
                                                            <div className="mt-1 text-sm break-words text-muted-foreground">
                                                                {row.lecturer}
                                                            </div>
                                                            <div className="mt-1">
                                                                <RelationTypeBadge
                                                                    relationType={
                                                                        row.relationType
                                                                    }
                                                                />
                                                            </div>
                                                        </div>
                                                        <div className="sm:shrink-0">
                                                            <StatusBadge
                                                                status={
                                                                    row.status
                                                                }
                                                            />
                                                        </div>
                                                    </div>

                                                    <Separator className="my-3" />

                                                    <div className="grid gap-2 text-sm text-muted-foreground">
                                                        <div className="flex items-start gap-2">
                                                            <Calendar className="mt-0.5 size-4 shrink-0" />
                                                            <span className="break-words">
                                                                {formatDate(
                                                                    row.scheduledAt,
                                                                )}
                                                            </span>
                                                        </div>
                                                        <div className="flex items-start gap-2">
                                                            <MapPin className="mt-0.5 size-4 shrink-0" />
                                                            <span className="break-words">
                                                                {row.location}
                                                            </span>
                                                        </div>
                                                        {row.lecturerNote && (
                                                            <div className="rounded-lg border bg-muted/30 p-3 text-xs">
                                                                Catatan dosen:{' '}
                                                                {
                                                                    row.lecturerNote
                                                                }
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            ),
                                        )
                                    ) : (
                                        <div className="rounded-xl border border-dashed bg-muted/20 p-6 text-center">
                                            <span className="mx-auto mb-3 inline-flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                                <Calendar className="size-5" />
                                            </span>
                                            <p className="text-sm font-medium">
                                                Belum ada riwayat bimbingan
                                            </p>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                Riwayat akan muncul setelah sesi
                                                pertama selesai.
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </>
                )}

                <ScheduleDetailModal
                    open={isDetailModalOpen}
                    onOpenChange={setIsDetailModalOpen}
                    schedule={selectedEvent}
                    currentUserRole="mahasiswa"
                />
            </div>
        </AppLayout>
    );
}
