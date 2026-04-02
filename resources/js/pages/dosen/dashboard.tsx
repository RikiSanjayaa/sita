import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    CalendarClock,
    ChevronRight,
    FileStack,
    MessageSquareText,
    Presentation,
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

const sectionCardClass = 'overflow-hidden gap-0 py-0 shadow-sm';
const sectionCardHeaderClass = 'border-b bg-muted/20 px-6 py-4';

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

    return (
        <DosenLayout
            breadcrumbs={breadcrumbs}
            title="Dashboard Dosen"
            subtitle="Ringkasan bimbingan, ujian, dan aktivitas mahasiswa"
        >
            <Head title="Dashboard Dosen" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-6 lg:gap-8 lg:py-8">
                {/* ── Hero Card ── */}
                <Card className="relative overflow-hidden border-border/60 p-0 shadow-sm">
                    {/* Decorative background blobs */}
                    <div className="pointer-events-none absolute inset-0 overflow-hidden">
                        <div className="absolute -top-20 -right-20 size-72 rounded-full bg-primary/8 blur-3xl" />
                        <div className="absolute -bottom-16 left-1/3 size-56 rounded-full bg-primary/5 blur-2xl" />
                    </div>

                    <CardContent className="relative bg-gradient-to-br from-primary/5 via-background to-accent/20 p-0">
                        <div className="grid lg:grid-cols-[1.3fr_0.7fr]">
                            {/* Left: greeting + description */}
                            <div className="flex flex-col justify-center gap-6 p-6 lg:p-8">
                                {/* Badges */}
                                <div className="flex flex-wrap items-center gap-2">
                                    {summary.programStudi ? (
                                        <Badge className="bg-primary text-primary-foreground hover:bg-primary/90">
                                            {summary.programStudi}
                                        </Badge>
                                    ) : null}
                                    {summary.concentration ? (
                                        <Badge variant="outline">
                                            {summary.concentration}
                                        </Badge>
                                    ) : null}
                                </div>

                                {/* Name + headline */}
                                <div>
                                    <p className="mb-2 text-xs font-semibold tracking-[0.15em] text-muted-foreground uppercase">
                                        Selamat datang
                                    </p>
                                    <h1 className="max-w-xl text-2xl font-bold tracking-tight text-foreground lg:text-[1.85rem] lg:leading-tight">
                                        {summary.lecturerName}
                                    </h1>
                                </div>

                                {/* Divider + description */}
                                <div className="flex gap-4">
                                    <div className="w-0.5 shrink-0 rounded-full bg-primary/30" />
                                    <p className="text-sm leading-7 text-muted-foreground">
                                        SITA menghadirkan ruang kerja terpadu
                                        untuk dosen pembimbing — mulai dari
                                        pemantauan progres mahasiswa,
                                        pengelolaan jadwal bimbingan, hingga
                                        penilaian sempro dan sidang, semuanya
                                        dalam satu platform tanpa perlu
                                        berpindah sistem.
                                    </p>
                                </div>

                                {/* Feature highlights */}
                                <div className="flex flex-wrap gap-2">
                                    {[
                                        {
                                            icon: Users,
                                            text: 'Mahasiswa terpantau',
                                        },
                                        {
                                            icon: CalendarClock,
                                            text: 'Jadwal terstruktur',
                                        },
                                        {
                                            icon: FileStack,
                                            text: 'Dokumen tercatat',
                                        },
                                    ].map(({ icon: Icon, text }) => (
                                        <span
                                            key={text}
                                            className="inline-flex items-center gap-1.5 rounded-full border bg-background/50 px-3 py-1.5 text-xs font-medium text-muted-foreground backdrop-blur"
                                        >
                                            <Icon className="size-3 text-primary" />
                                            {text}
                                        </span>
                                    ))}
                                </div>
                            </div>

                            {/* Right: summary mini-card — pinned flush inside the hero */}
                            <div className="flex items-stretch lg:border-l lg:border-border/60">
                                <div className="flex w-full flex-col justify-between bg-white/80 p-6 backdrop-blur lg:rounded-none dark:bg-black/70">
                                    {/* Header */}
                                    <div>
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="text-[10px] font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                                    Ringkasan Kerja
                                                </p>
                                                <p className="mt-2 text-3xl font-bold tracking-tight text-foreground">
                                                    {summary.quotaLabel}
                                                </p>
                                            </div>
                                            {/* Status dot + label */}
                                            <span className="flex items-center gap-1.5 rounded-full border bg-background/80 px-2.5 py-1 text-[10px] font-semibold text-foreground shadow-sm">
                                                <span className="size-1.5 rounded-full bg-primary" />
                                                {summary.status.label}
                                            </span>
                                        </div>

                                        <p className="mt-3 text-xs leading-5 text-muted-foreground">
                                            {summary.status.description}
                                        </p>
                                    </div>

                                    {/* Metrics */}
                                    {summary.metrics.length > 0 && (
                                        <div className="mt-5 grid grid-cols-2 gap-2">
                                            {summary.metrics.map((metric) => (
                                                <div
                                                    key={metric.label}
                                                    className="rounded-xl bg-primary/8 px-3 py-2.5"
                                                >
                                                    <p className="text-[10px] font-semibold tracking-wide text-primary/70 uppercase">
                                                        {metric.label}
                                                    </p>
                                                    <p className="mt-1 text-xl font-bold text-foreground">
                                                        {metric.value}
                                                    </p>
                                                </div>
                                            ))}
                                        </div>
                                    )}

                                    {/* CTA */}
                                    <div className="mt-5 border-t pt-4">
                                        <Link
                                            href="/dosen/jadwal-bimbingan"
                                            className="group inline-flex items-center gap-1.5 text-sm font-semibold text-primary"
                                        >
                                            Buka workspace jadwal
                                            <ArrowUpRight className="size-4 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid items-start gap-6 xl:grid-cols-[2fr_1fr]">
                    <div className="grid content-start gap-6">
                        {/* ── Aksi Cepat ── */}
                        <Card className={sectionCardClass}>
                            <CardHeader className={sectionCardHeaderClass}>
                                <CardTitle>Aksi Cepat</CardTitle>
                                <CardDescription>
                                    Akses halaman kerja utama untuk mengelola
                                    mahasiswa, jadwal, ujian, dan dokumen.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="py-6">
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {quickActions.map((action) => {
                                        const Icon = action.icon;

                                        return (
                                            <Link
                                                key={action.title}
                                                href={action.href}
                                                className={cn(
                                                    'group flex items-center gap-4 rounded-xl border px-4 py-4 transition-all',
                                                    action.primary
                                                        ? 'border-primary/20 bg-primary hover:bg-primary/92'
                                                        : 'border-border bg-card hover:border-primary/30 hover:bg-primary/5',
                                                )}
                                            >
                                                <span
                                                    className={cn(
                                                        'flex size-10 shrink-0 items-center justify-center rounded-xl transition-transform group-hover:scale-105',
                                                        action.primary
                                                            ? 'bg-primary-foreground/15 text-primary-foreground'
                                                            : 'bg-primary/10 text-primary',
                                                    )}
                                                >
                                                    <Icon className="size-5" />
                                                </span>
                                                <div className="min-w-0 flex-1">
                                                    <p
                                                        className={cn(
                                                            'text-sm font-semibold transition-colors',
                                                            action.primary
                                                                ? 'text-primary-foreground'
                                                                : 'text-foreground group-hover:text-primary',
                                                        )}
                                                    >
                                                        {action.title}
                                                    </p>
                                                    <p
                                                        className={cn(
                                                            'mt-0.5 line-clamp-2 text-xs leading-relaxed',
                                                            action.primary
                                                                ? 'text-primary-foreground/75'
                                                                : 'text-muted-foreground',
                                                        )}
                                                    >
                                                        {action.description}
                                                    </p>
                                                </div>
                                                <ChevronRight
                                                    className={cn(
                                                        'size-4 shrink-0 transition-all group-hover:translate-x-0.5',
                                                        action.primary
                                                            ? 'text-primary-foreground/50 group-hover:text-primary-foreground/80'
                                                            : 'text-muted-foreground/30 group-hover:text-primary',
                                                    )}
                                                />
                                            </Link>
                                        );
                                    })}
                                </div>
                            </CardContent>
                        </Card>

                        {/* ── Agenda Mendatang ── */}
                        <Card className={sectionCardClass}>
                            <CardHeader className={sectionCardHeaderClass}>
                                <CardTitle>Agenda Mendatang</CardTitle>
                                <CardDescription>
                                    Bimbingan dan agenda ujian terdekat agar
                                    prioritas harian lebih mudah dipantau.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="p-0">
                                {upcomingActivities.length > 0 ? (
                                    <div className="divide-y">
                                        {upcomingActivities.map((activity) => (
                                            <Link
                                                key={activity.id}
                                                href={activity.href}
                                                className="group flex items-center gap-4 px-5 py-3.5 transition-colors hover:bg-muted/20"
                                            >
                                                <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                    <CalendarClock className="size-4" />
                                                </span>

                                                <div className="min-w-0 flex-1">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <p className="text-sm font-medium text-foreground transition-colors group-hover:text-primary">
                                                            {activity.title}
                                                        </p>
                                                        <Badge
                                                            variant="outline"
                                                            className="rounded-full text-[10px] font-semibold"
                                                        >
                                                            {activity.badge}
                                                        </Badge>
                                                    </div>
                                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                                        {activity.subtitle}
                                                    </p>
                                                </div>

                                                <div className="hidden shrink-0 items-center gap-3 sm:flex">
                                                    <p className="text-xs font-medium text-foreground">
                                                        {activity.date}
                                                    </p>
                                                    <ChevronRight className="size-4 text-muted-foreground/30 transition-all group-hover:translate-x-0.5 group-hover:text-primary" />
                                                </div>
                                            </Link>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="px-6 pb-6">
                                        <EmptyState
                                            icon={CalendarClock}
                                            title="Belum ada agenda mendatang"
                                            description="Saat ada bimbingan atau jadwal ujian baru, semuanya akan muncul di sini."
                                        />
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* ── Mahasiswa Aktif ── */}
                    <div className="grid content-start gap-6">
                        <Card className={sectionCardClass}>
                            <CardHeader className={sectionCardHeaderClass}>
                                <CardTitle>Mahasiswa Aktif</CardTitle>
                                <CardDescription>
                                    Profil singkat mahasiswa yang sedang aktif
                                    dibimbing.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="py-6">
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
