import { Head, Link } from '@inertiajs/react';
import { Calendar, Clock, Send } from 'lucide-react';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { dashboard, jadwalBimbingan } from '@/routes';
import { create as jadwalBimbinganCreate } from '@/routes/jadwal-bimbingan';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Jadwal Bimbingan',
        href: jadwalBimbingan().url,
    },
    {
        title: 'Ajukan Bimbingan',
        href: jadwalBimbinganCreate().url,
    },
];

export default function JadwalBimbinganForm() {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Ajukan Jadwal Bimbingan"
            subtitle="Isi formulir untuk mengajukan jadwal bimbingan dengan dosen pembimbing"
        >
            <Head title="Ajukan Jadwal Bimbingan" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col px-4 py-6 md:px-6">
                <div className="mx-auto w-full max-w-[560px]">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                        }}
                    >
                        <Card className="shadow-sm">
                            <CardHeader className="gap-1">
                                <CardTitle>Ajukan Jadwal Bimbingan</CardTitle>
                                <CardDescription>
                                    Isi formulir untuk mengajukan jadwal
                                    bimbingan dengan dosen pembimbing
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="grid gap-6">
                                <div className="grid gap-2">
                                    <Label htmlFor="topik">
                                        Topik Bimbingan
                                    </Label>
                                    <Input
                                        id="topik"
                                        name="topik"
                                        required
                                        placeholder="Contoh: Review Bab 2 - Tinjauan Pustaka"
                                        aria-describedby="topik-help"
                                    />
                                    <p
                                        id="topik-help"
                                        className="text-xs text-muted-foreground"
                                    >
                                        Tulis fokus diskusi agar pembimbing bisa
                                        menyiapkan arahan.
                                    </p>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <div className="flex items-center justify-between gap-3">
                                            <Label htmlFor="tanggal">
                                                Tanggal Preferensi
                                            </Label>
                                            <Calendar className="size-4 text-muted-foreground" />
                                        </div>
                                        <Input
                                            id="tanggal"
                                            name="tanggal"
                                            type="date"
                                            required
                                            aria-describedby="tanggal-help"
                                        />
                                        <p
                                            id="tanggal-help"
                                            className="text-xs text-muted-foreground"
                                        >
                                            Gunakan format YYYY-MM-DD.
                                        </p>
                                    </div>

                                    <div className="grid gap-2">
                                        <div className="flex items-center justify-between gap-3">
                                            <Label htmlFor="waktu">
                                                Waktu Preferensi
                                            </Label>
                                            <Clock className="size-4 text-muted-foreground" />
                                        </div>
                                        <Input
                                            id="waktu"
                                            name="waktu"
                                            type="time"
                                            required
                                            aria-describedby="waktu-help"
                                        />
                                        <p
                                            id="waktu-help"
                                            className="text-xs text-muted-foreground"
                                        >
                                            Gunakan format 24 jam (HH:MM).
                                        </p>
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="tipe">Tipe Bimbingan</Label>
                                    <Select defaultValue="offline">
                                        <SelectTrigger
                                            id="tipe"
                                            aria-describedby="tipe-help"
                                        >
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
                                    <p
                                        id="tipe-help"
                                        className="text-xs text-muted-foreground"
                                    >
                                        Pilih offline untuk pertemuan di kampus
                                        atau online untuk pertemuan via platform
                                        meeting.
                                    </p>
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
                                        aria-describedby="catatan-help"
                                    />
                                    <p
                                        id="catatan-help"
                                        className="text-xs text-muted-foreground"
                                    >
                                        Misal: lampirkan poin pertanyaan, tautan
                                        dokumen, atau konteks kendala.
                                    </p>
                                </div>

                                <Alert className="border-sky-200 bg-sky-50 text-sky-950">
                                    <AlertDescription className="text-sky-900">
                                        <span className="font-medium">
                                            Catatan:
                                        </span>{' '}
                                        Jadwal akan dikonfirmasi oleh dosen
                                        pembimbing dalam 1-2 hari kerja.
                                    </AlertDescription>
                                </Alert>
                            </CardContent>

                            <CardFooter className="flex items-center justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link href={jadwalBimbingan().url}>
                                        Batal
                                    </Link>
                                </Button>
                                <Button
                                    type="submit"
                                    className="bg-slate-900 text-white hover:bg-slate-900/90"
                                >
                                    <Send className="size-4" />
                                    Kirim Permintaan
                                </Button>
                            </CardFooter>
                        </Card>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
