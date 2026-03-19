import { Head, Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowUpRight,
    CalendarClock,
    ChevronRight,
    FileStack,
    Layers3,
    MessageSquareText,
    Presentation,
    ShieldCheck,
    Users,
} from 'lucide-react';

import { EmptyState } from '@/components/empty-state';
import { PersonCardLink } from '@/components/profile/person-card-link';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import DosenLayout from '@/layouts/dosen-layout';
import { cn } from '@/lib/utils';
import {
    type BreadcrumbItem,
    type SharedData,
    type UserProfileSummary,
} from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dosen/dashboard' },
];

type DashboardProps = {
    summary: {
        lecturerName: string;
        programStudi: string | null;
        concentration: string | null;
        quotaLabel: string;
        status: {
            label: string;
            description: string;
        };
        metrics: Array<{
            label: string;
            value: string;
        }>;
    };
    upcomingActivities: Array<{
        id: string;
        badge: string;
        title: string;
        subtitle: string;
        date: string;
        href: string;
    }>;
    activeStudents: UserProfileSummary[];
};

export default function DosenDashboardPage() {
    const { summary, upcomingActivities, activeStudents } = usePage<
        SharedData & DashboardProps
    >().props;

    const quickActions = [
        {
            title: 'Mahasiswa Bimbingan',
            description:
                'Lihat mahasiswa aktif, tahap mereka, dan buka chat dengan cepat.',
            href: '/dosen/mahasiswa-bimbingan',
            icon: Users,
            primary: true,
        },
        {
            title: 'Jadwal Bimbingan',
            description:
                'Kelola permintaan jadwal dan pantau kalender bimbingan.',
            href: '/dosen/jadwal-bimbingan',
            icon: CalendarClock,
            primary: false,
        },
        {
            title: 'Sempro & Sidang',
            description:
                'Pantau agenda sempro dan sidang yang perlu Anda nilai.',
            href: '/dosen/seminar-proposal',
            icon: Presentation,
            primary: false,
        },
        {
            title: 'Dokumen Revisi',
            description:
                'Review dokumen mahasiswa yang menunggu tindak lanjut.',
            href: '/dosen/dokumen-revisi',
            icon: FileStack,
            primary: false,
        },
        {
            title: 'Pesan Bimbingan',
            description: 'Buka percakapan mahasiswa dan cek pesan terbaru.',
            href: '/dosen/pesan-bimbingan',
            icon: MessageSquareText,
            primary: false,
        },
    ];

    const spotlightCards: Array<{
        title: string;
        description: string;
        icon: LucideIcon;
    }> = [
        {
            title: 'Mahasiswa tetap terpantau',
            description:
                'Pantau progres, dokumen, dan komunikasi tanpa berpindah konteks terlalu jauh.',
            icon: Users,
        },
        {
            title: 'Agenda lebih terstruktur',
            description:
                'Kalender bimbingan, sempro, dan sidang tetap berada dalam workspace yang sama.',
            icon: Layers3,
        },
        {
            title: 'Respons lebih terjaga',
            description:
                'Gunakan ringkasan status untuk menangkap hal yang perlu segera ditindaklanjuti.',
            icon: ShieldCheck,
        },
    ];

    return (
        <DosenLayout
            breadcrumbs={breadcrumbs}
            title="Dashboard Dosen"
            subtitle="Ringkasan bimbingan, ujian, dan aktivitas mahasiswa"
        >
            <Head title="Dashboard Dosen" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-6 lg:gap-8 lg:py-8">
                <Card className="overflow-hidden border-border/70 p-0 shadow-sm">
                    <CardContent className="bg-gradient-to-br from-white/8 via-background to-primary/5 p-6 lg:p-8">
                        <div className="grid gap-6 lg:grid-cols-[1.25fr_0.75fr] lg:items-center">
                            <div className="space-y-6">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge className="bg-primary text-primary-foreground hover:bg-primary/90">
                                        {summary.status.label}
                                    </Badge>
                                    {summary.programStudi ? (
                                        <Badge variant="outline">
                                            {summary.programStudi}
                                        </Badge>
                                    ) : null}
                                    {summary.concentration ? (
                                        <Badge variant="outline">
                                            {summary.concentration}
                                        </Badge>
                                    ) : null}
                                </div>

                                <div className="space-y-4">
                                    <p className="text-sm text-muted-foreground">
                                        Halo, {summary.lecturerName}
                                    </p>
                                    <h1 className="max-w-2xl text-2xl font-semibold tracking-tight text-foreground lg:text-3xl">
                                        Pantau bimbingan dan ujian dengan lebih
                                        tenang.
                                    </h1>
                                    <p className="max-w-2xl text-sm leading-6 text-muted-foreground lg:text-base">
                                        Ringkasan ini membantu Anda melihat
                                        mahasiswa aktif, agenda terdekat, dan
                                        tindak lanjut yang masih menunggu
                                        respons.
                                    </p>
                                </div>

                                <div className="grid gap-3 md:grid-cols-3">
                                    {spotlightCards.map((item) => {
                                        const Icon = item.icon;

                                        return (
                                            <div
                                                key={item.title}
                                                className="rounded-2xl border bg-background/80 p-4 shadow-sm backdrop-blur"
                                            >
                                                <span className="inline-flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                                    <Icon className="size-4" />
                                                </span>
                                                <div className="mt-3 space-y-1.5">
                                                    <p className="text-sm font-semibold text-foreground">
                                                        {item.title}
                                                    </p>
                                                    <p className="text-xs leading-5 text-muted-foreground">
                                                        {item.description}
                                                    </p>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="rounded-3xl border bg-white/90 p-5 shadow-sm backdrop-blur dark:bg-black/90">
                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        <p className="text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase">
                                            Ringkasan Kerja
                                        </p>
                                        <p className="mt-3 text-3xl font-semibold tracking-tight text-foreground">
                                            {summary.quotaLabel}
                                        </p>
                                    </div>
                                    <Badge variant="outline">
                                        {summary.status.label}
                                    </Badge>
                                </div>

                                <p className="mt-4 text-sm leading-6 text-muted-foreground">
                                    {summary.status.description}
                                </p>

                                <div className="mt-5 grid gap-3 sm:grid-cols-2">
                                    {summary.metrics.map((metric) => (
                                        <div
                                            key={metric.label}
                                            className="rounded-2xl border bg-muted/20 p-4"
                                        >
                                            <p className="text-xs tracking-wide text-muted-foreground uppercase">
                                                {metric.label}
                                            </p>
                                            <p className="mt-2 text-xl font-semibold text-foreground">
                                                {metric.value}
                                            </p>
                                        </div>
                                    ))}
                                </div>

                                <div className="mt-5 border-t pt-4">
                                    <Link
                                        href="/dosen/jadwal-bimbingan"
                                        className="group inline-flex items-center gap-2 text-sm font-medium text-primary"
                                    >
                                        Buka workspace jadwal
                                        <ArrowUpRight className="size-4 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid items-start gap-6 xl:grid-cols-[2fr_1fr]">
                    <div className="grid content-start gap-6">
                        <Card className="overflow-hidden py-0 shadow-sm">
                            <CardHeader className="border-b bg-muted/20 px-6 py-4">
                                <CardTitle>Aksi Cepat</CardTitle>
                                <CardDescription>
                                    Akses halaman kerja utama untuk mengelola
                                    mahasiswa, jadwal, ujian, dan dokumen.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="pb-6">
                                <div className="grid gap-3 md:grid-cols-2">
                                    {quickActions.map((action) => {
                                        const Icon = action.icon;

                                        return (
                                            <Link
                                                key={action.title}
                                                href={action.href}
                                                className={cn(
                                                    'grid min-h-24 grid-cols-[36px_minmax(0,1fr)] items-start gap-3 rounded-xl border p-4 transition',
                                                    action.primary
                                                        ? 'border-primary/20 bg-primary text-primary-foreground hover:bg-primary/92'
                                                        : 'border-border bg-background hover:border-primary/20 hover:bg-muted/20',
                                                )}
                                            >
                                                <span
                                                    className={cn(
                                                        'inline-flex size-9 items-center justify-center rounded-lg',
                                                        action.primary
                                                            ? 'bg-primary-foreground/15 text-primary-foreground'
                                                            : 'bg-muted text-muted-foreground',
                                                    )}
                                                >
                                                    <Icon className="size-4" />
                                                </span>
                                                <span className="grid min-w-0 gap-1">
                                                    <span className="text-sm leading-tight font-medium">
                                                        {action.title}
                                                    </span>
                                                    <span
                                                        className={cn(
                                                            'line-clamp-2 text-xs leading-5',
                                                            action.primary
                                                                ? 'text-primary-foreground/80'
                                                                : 'text-muted-foreground',
                                                        )}
                                                    >
                                                        {action.description}
                                                    </span>
                                                </span>
                                            </Link>
                                        );
                                    })}
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="overflow-hidden py-0 shadow-sm">
                            <CardHeader className="border-b bg-muted/20 px-6 py-4">
                                <CardTitle>Agenda Mendatang</CardTitle>
                                <CardDescription>
                                    Bimbingan dan agenda ujian terdekat agar
                                    prioritas harian lebih mudah dipantau.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="pb-6">
                                {upcomingActivities.length > 0 ? (
                                    <div className="grid gap-3">
                                        {upcomingActivities.map((activity) => (
                                            <Link
                                                key={activity.id}
                                                href={activity.href}
                                                className="group rounded-xl border bg-background p-4 transition hover:border-primary/30 hover:bg-muted/30"
                                            >
                                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                                    <div className="min-w-0 space-y-2">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <Badge variant="outline">
                                                                {activity.badge}
                                                            </Badge>
                                                        </div>
                                                        <div>
                                                            <p className="text-sm font-semibold text-foreground group-hover:text-primary">
                                                                {activity.title}
                                                            </p>
                                                            <p className="text-sm text-muted-foreground">
                                                                {
                                                                    activity.subtitle
                                                                }
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div className="flex items-center gap-2 text-sm text-muted-foreground sm:shrink-0">
                                                        <span>
                                                            {activity.date}
                                                        </span>
                                                        <ChevronRight className="size-4 transition group-hover:translate-x-0.5" />
                                                    </div>
                                                </div>
                                            </Link>
                                        ))}
                                    </div>
                                ) : (
                                    <EmptyState
                                        icon={CalendarClock}
                                        title="Belum ada agenda mendatang"
                                        description="Saat ada bimbingan atau jadwal ujian baru, semuanya akan muncul di sini."
                                    />
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="grid content-start gap-6">
                        <Card className="overflow-hidden py-0 shadow-sm">
                            <CardHeader className="border-b bg-muted/20 px-6 py-4">
                                <CardTitle>Mahasiswa Aktif</CardTitle>
                                <CardDescription>
                                    Profil singkat mahasiswa yang sedang aktif
                                    dibimbing.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="pb-6">
                                {activeStudents.length > 0 ? (
                                    <div className="grid gap-3">
                                        {activeStudents.map((student) => (
                                            <PersonCardLink
                                                key={student.id}
                                                person={student}
                                            />
                                        ))}
                                    </div>
                                ) : (
                                    <EmptyState
                                        icon={Users}
                                        title="Belum ada mahasiswa aktif"
                                        description="Mahasiswa aktif akan tampil di sini setelah penugasan pembimbing berjalan."
                                    />
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </DosenLayout>
    );
}
