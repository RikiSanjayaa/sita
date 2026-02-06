import { Head, usePage } from '@inertiajs/react';
import {
    Calendar,
    CheckCircle2,
    Clock,
    MapPin,
    Plus,
    Send,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { dashboard, jadwalBimbingan } from '@/routes';
import { type BreadcrumbItem } from '@/types';

type MeetingType = 'Online' | 'Offline';
type MeetingStatus = 'Terjadwal' | 'Selesai' | 'Dibatalkan';

type UpcomingMeeting = {
    id: string;
    topik: string;
    pembimbing: {
        nama: string;
        avatar?: string | null;
    };
    tanggal: string;
    waktu: string;
    lokasi: string;
    tipe: MeetingType;
    status: Extract<MeetingStatus, 'Terjadwal'>;
};

type HistoryMeeting = {
    id: string;
    tanggal: string;
    waktu: string;
    topik: string;
    pembimbing: string;
    tipe: MeetingType;
    lokasi: string;
    status: Exclude<MeetingStatus, 'Terjadwal'>;
    catatan: string;
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

const upcomingMeetings: UpcomingMeeting[] = [
    {
        id: 'up-1',
        topik: 'Review Bab 3 - Metodologi Penelitian',
        pembimbing: {
            nama: 'Dr. Budi Santoso, M.Kom.',
            avatar: null,
        },
        tanggal: '30 Januari 2026',
        waktu: '10:00 - 11:00',
        lokasi: 'Ruang Dosen 301',
        tipe: 'Offline',
        status: 'Terjadwal',
    },
    {
        id: 'up-2',
        topik: 'Pembahasan Dataset dan Preprocessing',
        pembimbing: {
            nama: 'Dr. Budi Santoso, M.Kom.',
            avatar: null,
        },
        tanggal: '5 Februari 2026',
        waktu: '14:00 - 15:00',
        lokasi: 'Google Meet',
        tipe: 'Online',
        status: 'Terjadwal',
    },
];

const historyMeetings: HistoryMeeting[] = [
    {
        id: 'h-1',
        tanggal: '23 Januari 2026',
        waktu: '10:00 - 11:00',
        topik: 'Review Proposal dan Pembahasan Metode',
        pembimbing: 'Dr. Budi Santoso, M.Kom.',
        tipe: 'Offline',
        lokasi: 'Ruang Dosen 301',
        status: 'Selesai',
        catatan:
            'Proposal disetujui. Lanjutkan ke implementasi. Perbaiki bagian evaluasi dan tuliskan metrik yang digunakan.',
    },
    {
        id: 'h-2',
        tanggal: '16 Januari 2026',
        waktu: '14:00 - 15:00',
        topik: 'Konsultasi Judul dan Outline Proposal',
        pembimbing: 'Dr. Budi Santoso, M.Kom.',
        tipe: 'Online',
        lokasi: 'Zoom Meeting',
        status: 'Selesai',
        catatan:
            'Judul disetujui. Segera lengkapi proposal dengan tinjauan pustaka dan rencana eksperimen.',
    },
    {
        id: 'h-3',
        tanggal: '10 Januari 2026',
        waktu: '10:00 - 11:00',
        topik: 'Diskusi Awal Topik Penelitian',
        pembimbing: 'Dr. Budi Santoso, M.Kom.',
        tipe: 'Offline',
        lokasi: 'Ruang Dosen 301',
        status: 'Selesai',
        catatan:
            'Topik IoT dan Machine Learning menarik. Fokuskan ruang lingkup dan tentukan dataset yang relevan.',
    },
    {
        id: 'h-4',
        tanggal: '8 Januari 2026',
        waktu: '09:00 - 10:00',
        topik: 'Bimbingan Pemilihan Topik',
        pembimbing: 'Dr. Budi Santoso, M.Kom.',
        tipe: 'Offline',
        lokasi: 'Ruang Dosen 301',
        status: 'Dibatalkan',
        catatan: 'Dibatalkan karena dosen berhalangan.',
    },
];

function MeetingTypeBadge({ tipe }: { tipe: MeetingType }) {
    return (
        <Badge
            variant="outline"
            className="rounded-full bg-background text-foreground"
        >
            {tipe}
        </Badge>
    );
}

function StatusBadge({ status }: { status: MeetingStatus }) {
    if (status === 'Terjadwal') {
        return (
            <Badge className="bg-emerald-600 text-white dark:bg-emerald-500">
                Terjadwal
            </Badge>
        );
    }

    if (status === 'Selesai') {
        return (
            <Badge className="gap-1 rounded-full bg-emerald-600 text-white hover:bg-emerald-600/90 dark:bg-emerald-500 dark:hover:bg-emerald-500/90">
                <CheckCircle2 className="size-3" />
                Selesai
            </Badge>
        );
    }

    return (
        <Badge variant="destructive" className="gap-1 rounded-full">
            <XCircle className="size-3" />
            Dibatalkan
        </Badge>
    );
}

function PembimbingLine({
    nama,
    avatar,
}: {
    nama: string;
    avatar?: string | null;
}) {
    return (
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <Avatar className="h-6 w-6">
                <AvatarImage src={avatar ?? undefined} alt={nama} />
                <AvatarFallback>
                    {nama
                        .split(' ')
                        .slice(0, 2)
                        .map((p) => p[0])
                        .join('')}
                </AvatarFallback>
            </Avatar>
            <span className="truncate">{nama}</span>
        </div>
    );
}

function EmptyState() {
    return (
        <div className="rounded-lg border bg-muted/40 p-6">
            <div className="text-sm font-medium">Belum ada jadwal.</div>
            <div className="mt-1 text-sm text-muted-foreground">
                Ajukan bimbingan untuk membuat jadwal pertemuan dengan
                pembimbing.
            </div>
        </div>
    );
}

export default function JadwalBimbingan() {
    const page = usePage();
    const query = page.url.split('?')[1] ?? '';
    const [isAjukanOpen, setIsAjukanOpen] = useState(
        new URLSearchParams(query).get('open') === 'ajukan',
    );

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
                            Isi formulir untuk mengajukan jadwal bimbingan dengan dosen pembimbing
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        className="grid gap-5"
                        onSubmit={(e) => {
                            e.preventDefault();
                            setIsAjukanOpen(false);
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="topik">Topik Bimbingan</Label>
                            <Input
                                id="topik"
                                name="topik"
                                required
                                placeholder="Contoh: Review Bab 2 - Tinjauan Pustaka"
                            />
                            <p className="text-xs text-muted-foreground">
                                Tulis fokus diskusi agar pembimbing bisa menyiapkan arahan.
                            </p>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="tanggal">Tanggal Preferensi</Label>
                                <Input id="tanggal" name="tanggal" type="date" required />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="waktu">Waktu Preferensi</Label>
                                <Input id="waktu" name="waktu" type="time" required />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="tipe">Tipe Bimbingan</Label>
                            <Select defaultValue="offline">
                                <SelectTrigger id="tipe">
                                    <SelectValue placeholder="Pilih tipe bimbingan" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="offline">
                                        Offline (Tatap Muka)
                                    </SelectItem>
                                    <SelectItem value="online">Online</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="catatan">
                                Catatan Tambahan{' '}
                                <span className="font-normal text-muted-foreground">
                                    (Opsional)
                                </span>
                            </Label>
                            <Textarea
                                id="catatan"
                                name="catatan"
                                placeholder="Jelaskan hal-hal yang ingin didiskusikan..."
                            />
                        </div>

                        <Alert className="border-sky-200 bg-sky-50 text-sky-950 dark:border-sky-400/30 dark:bg-sky-500/10 dark:text-sky-200">
                            <AlertDescription className="text-sky-900 dark:text-sky-200">
                                <span className="font-medium">Catatan:</span> Jadwal akan dikonfirmasi oleh dosen pembimbing dalam 1-2 hari kerja.
                            </AlertDescription>
                        </Alert>

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

                <Card>
                    <CardHeader className="gap-1">
                        <CardTitle>Bimbingan Akan Datang</CardTitle>
                        <CardDescription>
                            Jadwal bimbingan yang telah dikonfirmasi
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {upcomingMeetings.length === 0 ? (
                            <EmptyState />
                        ) : (
                            <div className="grid gap-3">
                                {upcomingMeetings.map((m) => (
                                    <div
                                        key={m.id}
                                        className="rounded-xl border bg-background p-4"
                                    >
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="min-w-0">
                                                <div className="truncate text-sm font-semibold">
                                                    {m.topik}
                                                </div>
                                                <div className="mt-1">
                                                    <PembimbingLine
                                                        nama={m.pembimbing.nama}
                                                        avatar={
                                                            m.pembimbing.avatar
                                                        }
                                                    />
                                                </div>
                                            </div>
                                            <StatusBadge status={m.status} />
                                        </div>

                                        <div className="mt-4 grid gap-3 md:grid-cols-2">
                                            <div className="grid gap-2 text-sm text-muted-foreground">
                                                <div className="flex items-center gap-2">
                                                    <Calendar className="size-4" />
                                                    <span>{m.tanggal}</span>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <MapPin className="size-4" />
                                                    <span className="truncate">
                                                        {m.lokasi}
                                                    </span>
                                                </div>
                                            </div>

                                            <div className="grid gap-2 text-sm text-muted-foreground md:justify-items-end">
                                                <div className="flex items-center gap-2 md:justify-end">
                                                    <Clock className="size-4" />
                                                    <span>{m.waktu}</span>
                                                </div>
                                                <div className="md:justify-self-end">
                                                    <MeetingTypeBadge
                                                        tipe={m.tipe}
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="gap-1">
                        <CardTitle>Riwayat Bimbingan</CardTitle>
                        <CardDescription>
                            Catatan semua sesi bimbingan yang telah dilakukan
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {historyMeetings.length === 0 ? (
                            <EmptyState />
                        ) : (
                            <>
                                <div className="grid gap-3 md:hidden">
                                    {historyMeetings.map((row) => (
                                        <div
                                            key={row.id}
                                            className="rounded-xl border bg-background p-4"
                                        >
                                            <div className="flex items-start justify-between gap-4">
                                                <div className="min-w-0">
                                                    <div className="text-sm font-semibold">
                                                        {row.topik}
                                                    </div>
                                                    <div className="mt-1 text-sm text-muted-foreground">
                                                        {row.pembimbing}
                                                    </div>
                                                </div>
                                                <StatusBadge
                                                    status={row.status}
                                                />
                                            </div>

                                            <Separator className="my-4" />

                                            <div className="grid gap-2 text-sm text-muted-foreground">
                                                <div className="flex items-center gap-2">
                                                    <Calendar className="size-4" />
                                                    <span>{row.tanggal}</span>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Clock className="size-4" />
                                                    <span>{row.waktu}</span>
                                                </div>
                                                <div className="flex items-center justify-between gap-3">
                                                    <MeetingTypeBadge
                                                        tipe={row.tipe}
                                                    />
                                                    <div className="flex items-center gap-2 truncate">
                                                        <MapPin className="size-4" />
                                                        <span className="truncate">
                                                            {row.lokasi}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div className="rounded-lg border bg-muted/30 p-3">
                                                    <div className="text-xs text-muted-foreground">
                                                        Catatan
                                                    </div>
                                                    <div className="mt-1 text-sm text-foreground">
                                                        {row.catatan}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>

                                <div className="hidden overflow-hidden rounded-lg border md:block">
                                    <table className="w-full text-left text-sm">
                                        <thead className="bg-background">
                                            <tr className="border-b">
                                                <th className="px-4 py-3 font-medium">
                                                    Tanggal & Waktu
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Topik
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Tipe
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Status
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Catatan
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {historyMeetings.map((row) => (
                                                <tr
                                                    key={row.id}
                                                    className="border-b last:border-b-0"
                                                >
                                                    <td className="px-4 py-3 align-top">
                                                        <div className="grid gap-2 text-sm text-muted-foreground">
                                                            <div className="flex items-center gap-2">
                                                                <Calendar className="size-4" />
                                                                <span>
                                                                    {
                                                                        row.tanggal
                                                                    }
                                                                </span>
                                                            </div>
                                                            <div className="flex items-center gap-2">
                                                                <Clock className="size-4" />
                                                                <span>
                                                                    {row.waktu}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3 align-top">
                                                        <div className="min-w-0">
                                                            <div className="text-sm font-medium">
                                                                {row.topik}
                                                            </div>
                                                            <div className="mt-1 text-xs text-muted-foreground">
                                                                {row.pembimbing}
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3 align-top">
                                                        <div className="grid gap-2">
                                                            <div>
                                                                <MeetingTypeBadge
                                                                    tipe={
                                                                        row.tipe
                                                                    }
                                                                />
                                                            </div>
                                                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                                <MapPin className="size-3.5" />
                                                                <span className="truncate">
                                                                    {row.lokasi}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3 align-top">
                                                        <StatusBadge
                                                            status={row.status}
                                                        />
                                                    </td>
                                                    <td className="px-4 py-3 align-top">
                                                        <div className="max-w-[360px] text-sm text-muted-foreground">
                                                            {row.catatan}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

