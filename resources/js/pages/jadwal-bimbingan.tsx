import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Calendar,
    CheckCircle2,
    Clock,
    Inbox,
    MapPin,
    Plus,
    Send,
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
    scheduledAt: string;
    location: string;
    status: ScheduleStatus;
    lecturerNote: string | null;
};

type JadwalPageProps = {
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

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Jadwal Bimbingan"
            subtitle="Kelola jadwal bimbingan tugas akhir dengan dosen pembimbing"
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
                                            {advisor.advisorType === 'primary'
                                                ? 'Pembimbing 1'
                                                : 'Pembimbing 2'}
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

                        <div className="flex items-center justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
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

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Jadwal Bimbingan
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Kelola jadwal bimbingan tugas akhir dengan dosen
                            pembimbing
                        </p>
                    </div>
                    <Button
                        type="button"
                        className="h-9 bg-primary text-primary-foreground hover:bg-primary/90"
                        onClick={() => setIsAjukanOpen(true)}
                    >
                        <Plus className="size-4" />
                        Ajukan Bimbingan
                    </Button>
                </div>

                {page.props.flashMessage && (
                    <Alert>
                        <AlertTitle>Berhasil</AlertTitle>
                        <AlertDescription>
                            {page.props.flashMessage}
                        </AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader className="gap-1">
                        <CardTitle>Bimbingan Akan Datang</CardTitle>
                        <CardDescription>
                            Jadwal bimbingan yang diajukan atau telah
                            dikonfirmasi
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-3">
                            {hasUpcomingMeetings ? (
                                page.props.upcomingMeetings.map((meeting) => (
                                    <div
                                        key={meeting.id}
                                        className="rounded-xl border bg-background p-4"
                                    >
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="min-w-0">
                                                <div className="truncate text-sm font-semibold">
                                                    {meeting.topic}
                                                </div>
                                                <div className="mt-1 text-sm text-muted-foreground">
                                                    {meeting.lecturer}
                                                </div>
                                            </div>
                                            <StatusBadge
                                                status={meeting.status}
                                            />
                                        </div>

                                        <div className="mt-4 grid gap-2 text-sm text-muted-foreground">
                                            <div className="flex items-center gap-2">
                                                <Calendar className="size-4" />
                                                <span>
                                                    Preferensi:{' '}
                                                    {meeting.requestedAt}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Clock className="size-4" />
                                                <span>
                                                    Terkonfirmasi:{' '}
                                                    {meeting.scheduledAt ??
                                                        'Menunggu konfirmasi dosen'}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <MapPin className="size-4" />
                                                <span>{meeting.location}</span>
                                            </div>
                                            {meeting.lecturerNote && (
                                                <div className="rounded-lg border bg-muted/30 p-3 text-xs">
                                                    Catatan dosen:{' '}
                                                    {meeting.lecturerNote}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="rounded-xl border border-dashed bg-muted/20 p-6 text-center">
                                    <span className="mx-auto mb-3 inline-flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                        <Inbox className="size-5" />
                                    </span>
                                    <p className="text-sm font-medium">
                                        Belum ada jadwal mendatang
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Ajukan jadwal baru untuk mulai sesi
                                        bimbingan berikutnya.
                                    </p>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="gap-1">
                        <CardTitle>Riwayat Bimbingan</CardTitle>
                        <CardDescription>
                            Riwayat bimbingan yang sudah selesai / ditutup
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-3">
                            {hasHistoryMeetings ? (
                                page.props.historyMeetings.map((row) => (
                                    <div
                                        key={row.id}
                                        className="rounded-xl border bg-background p-4"
                                    >
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="min-w-0">
                                                <div className="text-sm font-semibold">
                                                    {row.topic}
                                                </div>
                                                <div className="mt-1 text-sm text-muted-foreground">
                                                    {row.lecturer}
                                                </div>
                                            </div>
                                            <StatusBadge status={row.status} />
                                        </div>

                                        <Separator className="my-3" />

                                        <div className="grid gap-2 text-sm text-muted-foreground">
                                            <div className="flex items-center gap-2">
                                                <Calendar className="size-4" />
                                                <span>{row.scheduledAt}</span>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <MapPin className="size-4" />
                                                <span>{row.location}</span>
                                            </div>
                                            {row.lecturerNote && (
                                                <div className="rounded-lg border bg-muted/30 p-3 text-xs">
                                                    Catatan dosen:{' '}
                                                    {row.lecturerNote}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="rounded-xl border border-dashed bg-muted/20 p-6 text-center">
                                    <span className="mx-auto mb-3 inline-flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                        <Calendar className="size-5" />
                                    </span>
                                    <p className="text-sm font-medium">
                                        Belum ada riwayat bimbingan
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Riwayat akan muncul setelah sesi pertama
                                        selesai.
                                    </p>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
