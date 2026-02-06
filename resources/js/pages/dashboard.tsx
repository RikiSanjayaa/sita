import { Head, usePage } from '@inertiajs/react';
import {
    Calendar,
    CalendarClock,
    Check,
    Clock,
    FileText,
    MessageSquareText,
    Upload,
    User,
} from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem, type SharedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

export default function Dashboard() {
    const page = usePage<SharedData>();
    const { auth } = page.props;

    const tugasAkhir = {
        status: 'Sedang Berjalan',
        progress: 65,
        judul: 'Implementasi Machine Learning untuk Prediksi Cuaca',
        dosen: 'Dr. Budi Santoso, M.Kom',
        mulai: '15 Agustus 2025',
        target: '15 Februari 2026',
    };

    const quickActions = [
        {
            title: 'Upload Dokumen',
            description: 'Unggah dokumen bimbingan',
            icon: Upload,
            variant: 'primary' as const,
        },
        {
            title: 'Jadwalkan Bimbingan',
            description: 'Buat janji dengan pembimbing',
            icon: CalendarClock,
            variant: 'outline' as const,
        },
        {
            title: 'Kirim Pesan',
            description: 'Hubungi dosen pembimbing',
            icon: MessageSquareText,
            variant: 'outline' as const,
        },
    ];

    const timeline = [
        {
            title: 'Pengajuan Judul',
            description: 'Judul disetujui oleh koordinator',
            date: '15 Agt 2025',
            status: 'done' as const,
        },
        {
            title: 'Penentuan Pembimbing',
            description: 'Dosen pembimbing telah ditentukan',
            date: '20 Agt 2025',
            status: 'done' as const,
        },
        {
            title: 'Bimbingan',
            description: 'Sedang dalam proses bimbingan (8/12 pertemuan)',
            date: null,
            status: 'current' as const,
        },
        {
            title: 'Seminar Proposal',
            description: 'Menunggu jadwal seminar proposal',
            date: null,
            status: 'todo' as const,
        },
        {
            title: 'Penelitian & Penulisan',
            description: 'Melakukan penelitian dan menulis skripsi',
            date: null,
            status: 'todo' as const,
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            subtitle={`Selamat datang kembali, ${auth.user.name}`}
        >
            <Head title="Dashboard" />
            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col px-4 py-6 md:px-6">
                <div className="grid gap-6 lg:grid-cols-[1fr_360px]">
                    <div className="grid gap-6">
                        <Card>
                            <CardHeader className="gap-3">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <CardTitle>
                                            Status Tugas Akhir
                                        </CardTitle>
                                        <CardDescription>
                                            Informasi terkini tentang tugas
                                            akhir Anda
                                        </CardDescription>
                                    </div>
                                    <Badge className="bg-primary text-primary-foreground">
                                        {tugasAkhir.status}
                                    </Badge>
                                </div>

                                <div className="space-y-2">
                                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                                        <span>Progress Keseluruhan</span>
                                        <span className="text-foreground">
                                            {tugasAkhir.progress}%
                                        </span>
                                    </div>
                                    <div className="h-2 w-full rounded-full bg-muted">
                                        <div
                                            className="h-full rounded-full bg-primary"
                                            style={{
                                                width: `${tugasAkhir.progress}%`,
                                            }}
                                        />
                                    </div>
                                </div>
                            </CardHeader>

                            <Separator />

                            <CardContent className="pt-6">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="flex items-start gap-3 rounded-lg border bg-background p-4">
                                        <span className="mt-0.5 inline-flex size-9 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                            <FileText className="size-4" />
                                        </span>
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium">
                                                Judul
                                            </p>
                                            <p className="truncate text-sm text-muted-foreground">
                                                {tugasAkhir.judul}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex items-start gap-3 rounded-lg border bg-background p-4">
                                        <span className="mt-0.5 inline-flex size-9 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                            <User className="size-4" />
                                        </span>
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium">
                                                Dosen Pembimbing
                                            </p>
                                            <p className="truncate text-sm text-muted-foreground">
                                                {tugasAkhir.dosen}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex items-start gap-3 rounded-lg border bg-background p-4">
                                        <span className="mt-0.5 inline-flex size-9 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                            <Calendar className="size-4" />
                                        </span>
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium">
                                                Tanggal Mulai
                                            </p>
                                            <p className="truncate text-sm text-muted-foreground">
                                                {tugasAkhir.mulai}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex items-start gap-3 rounded-lg border bg-background p-4">
                                        <span className="mt-0.5 inline-flex size-9 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                            <Clock className="size-4" />
                                        </span>
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium">
                                                Target Selesai
                                            </p>
                                            <p className="truncate text-sm text-muted-foreground">
                                                {tugasAkhir.target}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Aksi Cepat</CardTitle>
                                <CardDescription>
                                    Akses fitur yang sering digunakan
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid gap-3 md:grid-cols-3">
                                    {quickActions.map((action) => {
                                        const Icon = action.icon;
                                        const isPrimary =
                                            action.variant === 'primary';
                                        return (
                                            <Button
                                                key={action.title}
                                                type="button"
                                                variant={
                                                    isPrimary
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                className={
                                                    isPrimary
                                                        ? 'h-auto justify-start gap-3 bg-primary p-4 text-left text-primary-foreground hover:bg-primary/90'
                                                        : 'h-auto justify-start gap-3 p-4 text-left'
                                                }
                                            >
                                                <span
                                                    className={
                                                        isPrimary
                                                            ? 'inline-flex size-9 items-center justify-center rounded-md bg-primary-foreground/15'
                                                            : 'inline-flex size-9 items-center justify-center rounded-md bg-muted text-muted-foreground'
                                                    }
                                                >
                                                    <Icon className="size-4" />
                                                </span>
                                                <span className="grid gap-0.5">
                                                    <span className="text-sm leading-tight font-medium">
                                                        {action.title}
                                                    </span>
                                                    <span
                                                        className={
                                                            isPrimary
                                                                ? 'text-xs text-primary-foreground/70'
                                                                : 'text-xs text-muted-foreground'
                                                        }
                                                    >
                                                        {action.description}
                                                    </span>
                                                </span>
                                            </Button>
                                        );
                                    })}
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle>Timeline Progres</CardTitle>
                            <CardDescription>
                                Tahapan penyelesaian tugas akhir Anda
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="relative">
                                <div className="absolute top-2 left-3.75 h-full w-px bg-border" />
                                <div className="grid gap-6">
                                    {timeline.map((step) => {
                                        const isDone = step.status === 'done';
                                        const isCurrent =
                                            step.status === 'current';

                                        return (
                                            <div
                                                key={step.title}
                                                className="relative grid grid-cols-[32px_1fr_auto] items-start gap-4"
                                            >
                                                <div
                                                    className={
                                                        isDone
                                                            ? 'mt-0.5 flex size-8 items-center justify-center rounded-full bg-primary text-primary-foreground'
                                                            : isCurrent
                                                                ? 'mt-0.5 flex size-8 items-center justify-center rounded-full border-2 border-primary bg-background'
                                                                : 'mt-0.5 flex size-8 items-center justify-center rounded-full border bg-background'
                                                    }
                                                >
                                                    {isDone ? (
                                                        <Check className="size-4" />
                                                    ) : (
                                                        <span
                                                            className={
                                                                isCurrent
                                                                    ? 'size-2 rounded-full bg-primary'
                                                                    : 'size-2 rounded-full bg-muted-foreground/40'
                                                            }
                                                        />
                                                    )}
                                                </div>
                                                <div className="min-w-0">
                                                    <p className="text-sm font-medium">
                                                        {step.title}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {step.description}
                                                    </p>
                                                </div>
                                                <div className="pt-0.5 text-xs text-muted-foreground">
                                                    {step.date ?? ''}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

