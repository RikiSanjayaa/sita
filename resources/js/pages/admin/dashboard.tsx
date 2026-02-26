import { Head } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarClock,
    CheckCircle2,
    ClipboardList,
    FileStack,
    Users,
} from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
];

const kpis = [
    {
        title: 'Mahasiswa Aktif',
        value: '184',
        description: 'Terpantau pada siklus bimbingan berjalan',
        icon: Users,
    },
    {
        title: 'Utilisasi Kapasitas',
        value: '78%',
        description: 'Rata-rata beban dosen pembimbing',
        icon: ClipboardList,
    },
    {
        title: 'Pending Assignment',
        value: '17',
        description: 'Mahasiswa belum mendapat pembimbing',
        icon: AlertTriangle,
    },
    {
        title: 'Pending Review',
        value: '49',
        description: 'Dokumen menunggu tindak lanjut dosen',
        icon: FileStack,
    },
];

const operationalQueue = [
    {
        type: 'Penugasan',
        item: '12 mahasiswa belum memiliki pembimbing kedua',
        owner: 'Admin Prodi',
        due: 'Hari ini',
    },
    {
        type: 'Jadwal',
        item: '7 pengajuan jadwal belum dikonfirmasi dosen',
        owner: 'Koordinator TA',
        due: 'Hari ini',
    },
    {
        type: 'Dokumen',
        item: '10 dokumen revisi belum mendapatkan feedback',
        owner: 'Dosen Pembimbing',
        due: 'Besok',
    },
];

const recentActivities = [
    {
        label: 'Assignment dibuat untuk Muhammad Akbar',
        time: '10 menit lalu',
        tone: 'success',
    },
    {
        label: 'Kuota Dr. Budi mencapai 14/14',
        time: '42 menit lalu',
        tone: 'warning',
    },
    {
        label: 'Eskalasi chat thread #GRP-102 tercatat',
        time: '1 jam lalu',
        tone: 'info',
    },
];

export default function AdminDashboardPage() {
    return (
        <AdminLayout
            breadcrumbs={breadcrumbs}
            title="Dashboard Admin"
            subtitle="Monitoring operasional bimbingan tugas akhir"
        >
            <Head title="Dashboard Admin" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6">
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {kpis.map((kpi) => {
                        const Icon = kpi.icon;

                        return (
                            <Card key={kpi.title}>
                                <CardHeader className="pb-2">
                                    <CardDescription>{kpi.title}</CardDescription>
                                    <CardTitle className="text-2xl">
                                        {kpi.value}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="flex items-start gap-3">
                                    <span className="inline-flex size-9 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                        <Icon className="size-4" />
                                    </span>
                                    <p className="text-sm text-muted-foreground">
                                        {kpi.description}
                                    </p>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>

                <div className="grid gap-6 lg:grid-cols-[1.3fr_1fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Operational Queue</CardTitle>
                            <CardDescription>
                                Daftar item prioritas lintas modul
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {operationalQueue.map((item) => (
                                <div
                                    key={item.item}
                                    className="rounded-lg border bg-background p-4"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <Badge variant="outline">
                                            {item.type}
                                        </Badge>
                                        <div className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                                            <CalendarClock className="size-3.5" />
                                            {item.due}
                                        </div>
                                    </div>
                                    <p className="mt-2 text-sm font-medium">
                                        {item.item}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        PIC: {item.owner}
                                    </p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Recent Activity</CardTitle>
                            <CardDescription>
                                Event sistem terbaru (placeholder)
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {recentActivities.map((event) => (
                                <div
                                    key={event.label}
                                    className="rounded-lg border bg-background p-3"
                                >
                                    <div className="flex items-start gap-2">
                                        {event.tone === 'success' ? (
                                            <CheckCircle2 className="mt-0.5 size-4 text-emerald-600 dark:text-emerald-400" />
                                        ) : event.tone === 'warning' ? (
                                            <AlertTriangle className="mt-0.5 size-4 text-amber-500" />
                                        ) : (
                                            <ClipboardList className="mt-0.5 size-4 text-primary" />
                                        )}
                                        <div>
                                            <p className="text-sm">
                                                {event.label}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {event.time}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
