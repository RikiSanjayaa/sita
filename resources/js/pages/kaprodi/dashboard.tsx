import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowUpRight,
    CalendarClock,
    ChevronRight,
    ClipboardCheck,
    FileStack,
    FileArchive,
    GraduationCap,
    UsersRound,
} from 'lucide-react';

import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import KaprodiLayout from '@/layouts/kaprodi-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/kaprodi/dashboard' },
];

type ProgramStudi = {
    id: number;
    name: string;
    slug: string;
};

type WorkSummary = {
    label: string;
    headline: string;
    description: string;
    metrics: Array<{
        label: string;
        value: string;
    }>;
};

type AttentionItem = {
    id: string;
    label: string;
    value: number;
    description: string;
    href: string;
};

type AgendaItem = {
    id: string;
    badge: string;
    title: string;
    subtitle: string;
    date: string;
};

type ArchiveItem = {
    id: number;
    student: string;
    title: string;
    state: string;
    completedAt: string;
    profileUrl: string;
};

type DistributionItem = {
    key: string;
    label: string;
    count: number;
};

type DefenseProgressItem = {
    type: string;
    status: string;
    label: string;
    count: number;
};

type DashboardProps = {
    programStudi: ProgramStudi;
    workSummary: WorkSummary;
    attentionItems: AttentionItem[];
    upcomingAgenda: AgendaItem[];
    recentArchives: ArchiveItem[];
    phaseDistribution: DistributionItem[];
    defenseProgress: DefenseProgressItem[];
};

const sectionCardClass = 'overflow-hidden gap-0 py-0 shadow-sm';
const sectionCardHeaderClass = 'border-b bg-muted/20 px-6 py-4';

export default function KaprodiDashboardPage() {
    const {
        auth,
        programStudi,
        workSummary,
        attentionItems,
        upcomingAgenda,
        recentArchives,
        phaseDistribution,
        defenseProgress,
    } = usePage<SharedData & DashboardProps>().props;

    const quickActions = [
        {
            title: 'Mahasiswa Prodi',
            description:
                'Cari mahasiswa, lihat fase, pembimbing, dan detail arsip.',
            href: '/kaprodi/mahasiswa',
            icon: UsersRound,
            primary: true,
        },
        {
            title: 'Sempro & Sidang',
            description:
                'Pantau jadwal, status ujian, hasil, dan revisi terbuka.',
            href: '/kaprodi/sempro-sidang',
            icon: ClipboardCheck,
            primary: false,
        },
        {
            title: 'Dokumen',
            description: 'Pantau upload mahasiswa dan status review dosen.',
            href: '/kaprodi/dokumen',
            icon: FileStack,
            primary: false,
        },
        {
            title: 'Dosen Prodi',
            description:
                'Lihat beban dosen sebagai pembimbing dan penguji prodi.',
            href: '/kaprodi/dosen-prodi',
            icon: GraduationCap,
            primary: false,
        },
    ];

    return (
        <KaprodiLayout
            breadcrumbs={breadcrumbs}
            title="Dashboard Kaprodi"
            subtitle={`Monitoring program studi ${programStudi.name}`}
        >
            <Head title="Dashboard Kaprodi" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-6 lg:gap-8 lg:py-8">
                <Card className="relative overflow-hidden border-border/60 p-0 shadow-sm">
                    <div className="pointer-events-none absolute inset-0 overflow-hidden">
                        <div className="absolute -top-20 -right-20 size-72 rounded-full bg-primary/5 blur-3xl" />
                        <div className="absolute -bottom-16 left-1/3 size-56 rounded-full bg-primary/3 blur-2xl" />
                    </div>

                    <CardContent className="relative bg-gradient-to-br from-background via-background to-accent/12 p-0">
                        <div className="grid lg:grid-cols-[1.3fr_0.7fr]">
                            <div className="flex flex-col justify-center gap-6 p-6 lg:p-8">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge className="bg-primary text-primary-foreground hover:bg-primary/90">
                                        {programStudi.name}
                                    </Badge>
                                </div>

                                <div>
                                    <p className="mb-2 text-xs font-semibold tracking-[0.15em] text-muted-foreground uppercase">
                                        Selamat datang
                                    </p>
                                    <h1 className="max-w-2xl text-2xl font-bold tracking-tight text-foreground lg:text-[1.85rem] lg:leading-tight">
                                        {auth.user.name}
                                    </h1>
                                </div>

                                <div className="flex gap-4">
                                    <div className="w-0.5 shrink-0 rounded-full bg-primary/30" />
                                    <p className="text-sm leading-7 text-muted-foreground">
                                        Portal kaprodi membantu memantau progres
                                        tugas akhir seluruh mahasiswa prodi,
                                        agenda sempro dan sidang, beban dosen,
                                        serta arsip proyek tanpa mengubah data
                                        operasional.
                                    </p>
                                </div>

                                <div className="flex flex-wrap gap-2">
                                    {[
                                        {
                                            icon: UsersRound,
                                            text: 'Mahasiswa terpantau',
                                        },
                                        {
                                            icon: CalendarClock,
                                            text: 'Agenda terbaca',
                                        },
                                        {
                                            icon: FileArchive,
                                            text: 'Arsip tersedia',
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

                            <div className="flex items-stretch lg:border-l lg:border-border/60">
                                <div className="flex w-full flex-col justify-between bg-card/95 p-6 backdrop-blur lg:rounded-none">
                                    <div>
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="text-[10px] font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                                    Ringkasan Prodi
                                                </p>
                                                <p className="mt-2 text-3xl font-bold tracking-tight text-foreground">
                                                    {workSummary.headline}
                                                </p>
                                            </div>
                                            <span className="flex items-center gap-1.5 rounded-full border bg-background/80 px-2.5 py-1 text-[10px] font-semibold text-foreground shadow-sm">
                                                <span className="size-1.5 rounded-full bg-primary" />
                                                {workSummary.label}
                                            </span>
                                        </div>

                                        <p className="mt-3 text-xs leading-5 text-muted-foreground">
                                            {workSummary.description}
                                        </p>
                                    </div>

                                    <div className="mt-5 grid grid-cols-2 gap-2">
                                        {workSummary.metrics.map((metric) => (
                                            <div
                                                key={metric.label}
                                                className="rounded-xl bg-muted/55 px-3 py-2.5"
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

                                    <div className="mt-5 border-t pt-4">
                                        <Link
                                            href="/kaprodi/mahasiswa"
                                            className="group inline-flex items-center gap-1.5 text-sm font-semibold text-primary"
                                        >
                                            Buka mahasiswa prodi
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
                        <Card className={sectionCardClass}>
                            <CardHeader className={sectionCardHeaderClass}>
                                <CardTitle>Akses Monitoring</CardTitle>
                                <CardDescription>
                                    Buka halaman khusus untuk membaca data prodi
                                    tanpa menumpuk semuanya di dashboard.
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

                        <Card className={sectionCardClass}>
                            <CardHeader className={sectionCardHeaderClass}>
                                <CardTitle>Fase Mahasiswa</CardTitle>
                                <CardDescription>
                                    Snapshot cepat progres proyek di prodi.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3 py-5 sm:grid-cols-2">
                                {phaseDistribution.map((item) => (
                                    <div
                                        key={item.key}
                                        className="flex items-center justify-between rounded-lg border px-4 py-3"
                                    >
                                        <span className="text-sm font-medium">
                                            {item.label}
                                        </span>
                                        <Badge variant="secondary">
                                            {item.count}
                                        </Badge>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="grid content-start gap-6">
                        <Card className={sectionCardClass}>
                            <CardHeader className={sectionCardHeaderClass}>
                                <CardTitle>Perlu Perhatian</CardTitle>
                                <CardDescription>
                                    Sinyal monitoring yang paling cepat perlu
                                    dibuka.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="py-6">
                                {attentionItems.length > 0 ? (
                                    <div className="grid gap-3">
                                        {attentionItems.map((item) => (
                                            <Link
                                                key={item.id}
                                                href={item.href}
                                                className="group flex items-start gap-3 rounded-xl border px-4 py-3 transition-colors hover:border-primary/30 hover:bg-primary/5"
                                            >
                                                <span className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                    <AlertCircle className="size-4" />
                                                </span>
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center justify-between gap-3">
                                                        <p className="text-sm font-semibold group-hover:text-primary">
                                                            {item.label}
                                                        </p>
                                                        <Badge>
                                                            {item.value}
                                                        </Badge>
                                                    </div>
                                                    <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
                                                        {item.description}
                                                    </p>
                                                </div>
                                            </Link>
                                        ))}
                                    </div>
                                ) : (
                                    <EmptyState
                                        icon={AlertCircle}
                                        title="Belum ada perhatian mendesak"
                                        description="Indikator penting akan muncul saat ada revisi, ujian, atau penugasan yang perlu dipantau."
                                    />
                                )}
                            </CardContent>
                        </Card>

                        <Card className={sectionCardClass}>
                            <CardHeader className={sectionCardHeaderClass}>
                                <CardTitle>Sempro & Sidang</CardTitle>
                                <CardDescription>
                                    Ringkasan status attempt ujian.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3 py-5">
                                {defenseProgress.length > 0 ? (
                                    defenseProgress.map((item) => (
                                        <div
                                            key={`${item.type}-${item.status}`}
                                            className="flex items-center justify-between gap-3"
                                        >
                                            <span className="text-sm">
                                                {item.label}
                                            </span>
                                            <Badge variant="outline">
                                                {item.count}
                                            </Badge>
                                        </div>
                                    ))
                                ) : (
                                    <EmptyState
                                        icon={ClipboardCheck}
                                        title="Belum ada sempro atau sidang"
                                        description="Attempt ujian akan tampil saat data tersedia."
                                    />
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <div className="grid items-start gap-6 xl:grid-cols-2">
                    <Card className={sectionCardClass}>
                        <CardHeader className={sectionCardHeaderClass}>
                            <CardTitle>Agenda Terdekat</CardTitle>
                            <CardDescription>
                                Jadwal ujian dan revisi yang masih terbuka.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="divide-y p-0">
                            {upcomingAgenda.length > 0 ? (
                                upcomingAgenda.map((agenda) => (
                                    <div
                                        key={agenda.id}
                                        className="flex items-center gap-3 px-5 py-3.5"
                                    >
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                            <CalendarClock className="size-4" />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="text-sm font-medium">
                                                    {agenda.title}
                                                </p>
                                                <Badge variant="outline">
                                                    {agenda.badge}
                                                </Badge>
                                            </div>
                                            <p className="mt-0.5 line-clamp-1 text-xs text-muted-foreground">
                                                {agenda.subtitle}
                                            </p>
                                        </div>
                                        <p className="hidden text-right text-xs font-medium sm:block">
                                            {agenda.date}
                                        </p>
                                    </div>
                                ))
                            ) : (
                                <div className="p-6">
                                    <EmptyState
                                        icon={CalendarClock}
                                        title="Belum ada agenda terdekat"
                                        description="Jadwal ujian dan deadline revisi akan muncul di sini."
                                    />
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card className={sectionCardClass}>
                        <CardHeader className={sectionCardHeaderClass}>
                            <CardTitle>Arsip Terbaru</CardTitle>
                            <CardDescription>
                                Proyek selesai atau dibatalkan paling baru.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="divide-y p-0">
                            {recentArchives.length > 0 ? (
                                recentArchives.map((archive) => (
                                    <Link
                                        key={archive.id}
                                        href={archive.profileUrl}
                                        className="group flex items-center gap-3 px-5 py-3.5 transition-colors hover:bg-muted/20"
                                    >
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                            <FileArchive className="size-4" />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="text-sm font-medium group-hover:text-primary">
                                                    {archive.student}
                                                </p>
                                                <Badge variant="outline">
                                                    {archive.state}
                                                </Badge>
                                            </div>
                                            <p className="mt-0.5 line-clamp-1 text-xs text-muted-foreground">
                                                {archive.title}
                                            </p>
                                        </div>
                                        <p className="hidden text-right text-xs font-medium sm:block">
                                            {archive.completedAt}
                                        </p>
                                    </Link>
                                ))
                            ) : (
                                <div className="p-6">
                                    <EmptyState
                                        icon={FileArchive}
                                        title="Belum ada arsip"
                                        description="Proyek selesai atau dibatalkan akan muncul di sini."
                                    />
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </KaprodiLayout>
    );
}
