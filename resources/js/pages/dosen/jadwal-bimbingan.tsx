import { Head, useForm, usePage } from '@inertiajs/react';
import { CalendarClock, MapPin } from 'lucide-react';

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
};

type JadwalBimbinganProps = {
    pendingRequests: PendingRequest[];
    upcomingSchedules: UpcomingSchedule[];
    flashMessage?: string | null;
};

export default function DosenJadwalBimbinganPage() {
    const { pendingRequests, upcomingSchedules, flashMessage } = usePage<
        SharedData & JadwalBimbinganProps
    >().props;

    const form = useForm({
        decision: 'approve' as 'approve' | 'reject' | 'reschedule',
        scheduled_for: '',
        location: '',
        lecturer_note: '',
    });

    function decide(
        scheduleId: number,
        decision: 'approve' | 'reject' | 'reschedule',
        item: PendingRequest,
    ) {
        form.setData({
            decision,
            scheduled_for:
                decision === 'reject'
                    ? ''
                    : (item.requestedForInput ?? form.data.scheduled_for),
            location: item.location ?? 'Google Meet',
            lecturer_note:
                decision === 'reject'
                    ? 'Permintaan jadwal ditolak.'
                    : decision === 'reschedule'
                      ? 'Jadwal disesuaikan mengikuti slot dosen.'
                      : 'Permintaan jadwal disetujui.',
        });

        form.transform((data) => ({
            ...data,
            scheduled_for: data.scheduled_for || null,
            location: data.location || null,
            lecturer_note: data.lecturer_note || null,
        }));

        form.post(`/dosen/jadwal-bimbingan/${scheduleId}/decision`, {
            preserveScroll: true,
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

                        {pendingRequests.map((item) => (
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
                                            Jadwal Ulang
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            disabled={form.processing}
                                            onClick={() =>
                                                decide(item.id, 'reject', item)
                                            }
                                        >
                                            Tolak
                                        </Button>
                                        <Button
                                            size="sm"
                                            className="bg-primary text-primary-foreground hover:bg-primary/90"
                                            disabled={form.processing}
                                            onClick={() =>
                                                decide(item.id, 'approve', item)
                                            }
                                        >
                                            Konfirmasi
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        ))}
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
                        {upcomingSchedules.map((item) => (
                            <div
                                key={`${item.id}-${item.mahasiswa}`}
                                className="rounded-lg border bg-background p-4"
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <p className="text-sm font-semibold">
                                        {item.mahasiswa}
                                    </p>
                                    <Badge
                                        variant={
                                            item.status === 'approved'
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                    >
                                        {item.status}
                                    </Badge>
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
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </DosenLayout>
    );
}
