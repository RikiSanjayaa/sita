import { Head, Link, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    Check,
    ChevronRight,
    FileText,
    MessageSquareText,
    Upload,
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
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import {
    jadwalBimbingan,
    pesan,
    tugasAkhir,
    uploadDokumen,
} from '@/routes/mahasiswa';
import {
    type BreadcrumbItem,
    type SharedData,
    type UserProfileSummary,
} from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

type DashboardSummary = {
    studentName: string;
    programStudi: string | null;
    projectTitle: string | null;
    workflow: {
        key: string;
        label: string;
        description: string;
    };
    progress: number;
    startedAt: string | null;
    advisors: UserProfileSummary[];
    hasProject: boolean;
};

type QuickActionState = {
    canSubmitTitle: boolean;
    canScheduleMeeting: boolean;
    canUploadDocument: boolean;
    hasThreads: boolean;
};

type UpcomingActivity = {
    id: string;
    type: string;
    badge: string;
    title: string;
    subtitle: string;
    date: string | null;
    status: string;
    href: string;
};

type TimelineStep = {
    title: string;
    description: string;
    date: string | null;
    status: 'done' | 'current' | 'upcoming';
};

type DashboardProps = {
    summary: DashboardSummary;
    quickActionState: QuickActionState;
    upcomingActivities: UpcomingActivity[];
    timeline: TimelineStep[];
};

export default function DashboardPage() {
    const { summary, quickActionState, upcomingActivities, timeline } = usePage<
        SharedData & DashboardProps
    >().props;

    const quickActions = [
        {
            title: summary.hasProject ? 'Buka Tugas Akhir' : 'Ajukan Judul',
            description: summary.hasProject
                ? 'Lihat status proposal, dosen, dan detail tugas akhir.'
                : 'Mulai pengajuan judul dan proposal pertama Anda.',
            href: tugasAkhir().url,
            icon: FileText,
            enabled: true,
            variant: 'default' as const,
        },
        {
            title: 'Ajukan Bimbingan',
            description: quickActionState.canScheduleMeeting
                ? 'Buat janji dengan pembimbing atau penguji yang tersedia.'
                : 'Akan aktif setelah pembimbing atau penguji terhubung.',
            href: jadwalBimbingan({ query: { open: 'ajukan' } }).url,
            icon: CalendarClock,
            enabled: quickActionState.canScheduleMeeting,
            variant: 'outline' as const,
        },
        {
            title: 'Upload Dokumen',
            description: quickActionState.canUploadDocument
                ? 'Unggah proposal, revisi, atau dokumen bimbingan terbaru.'
                : 'Akan aktif saat ada pembimbing atau sempro aktif.',
            href: uploadDokumen({ query: { open: 'unggah' } }).url,
            icon: Upload,
            enabled: quickActionState.canUploadDocument,
            variant: 'outline' as const,
        },
        {
            title: 'Buka Pesan',
            description: quickActionState.hasThreads
                ? 'Lanjutkan percakapan dengan pembimbing atau penguji.'
                : 'Ruang pesan akan aktif saat thread akademik tersedia.',
            href: pesan().url,
            icon: MessageSquareText,
            enabled: true,
            variant: 'outline' as const,
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Dashboard Mahasiswa"
            subtitle="Ringkasan tugas akhir, aksi cepat, dan agenda akademik terdekat"
        >
            <Head title="Dashboard Mahasiswa" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-6 lg:gap-8">
                <Card className="overflow-hidden border-border/70 p-0 shadow-sm">
                    <CardContent className="bg-gradient-to-br from-white/8 via-background to-accent/20 p-6 lg:p-8">
                        <div className="grid gap-8 lg:grid-cols-[1.3fr_0.7fr] lg:items-start">
                            <div className="space-y-5">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge className="bg-primary text-primary-foreground hover:bg-primary/90">
                                        {summary.workflow.label}
                                    </Badge>
                                    {summary.programStudi ? (
                                        <Badge variant="outline">
                                            {summary.programStudi}
                                        </Badge>
                                    ) : null}
                                    {summary.startedAt ? (
                                        <Badge variant="outline">
                                            Mulai {summary.startedAt}
                                        </Badge>
                                    ) : null}
                                </div>

                                <div className="space-y-4">
                                    <p className="text-sm text-muted-foreground">
                                        Judul tugas akhir {summary.studentName}:
                                    </p>
                                    <h1 className="max-w-3xl text-2xl font-semibold tracking-tight text-foreground lg:text-3xl">
                                        {summary.projectTitle ??
                                            'Siapkan pengajuan judul dan mulai perjalanan tugas akhir Anda.'}
                                    </h1>
                                    <p className="max-w-3xl text-sm leading-6 text-muted-foreground lg:text-base">
                                        {summary.workflow.description}
                                    </p>
                                </div>
                            </div>

                            <div className="rounded-2xl border bg-white/90 p-5 shadow-sm backdrop-blur dark:bg-black/90">
                                <p className="text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase">
                                    Status Saat Ini
                                </p>
                                <div className="mt-3 space-y-3">
                                    <div>
                                        <p className="text-2xl font-semibold tracking-tight text-foreground">
                                            {summary.workflow.label}
                                        </p>
                                        <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                            {summary.workflow.description}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid items-start gap-6 xl:grid-cols-[2fr_1fr]">
                    <div className="grid content-start gap-6">
                        <Card className="shadow-sm">
                            <CardHeader>
                                <CardTitle>Aksi Cepat</CardTitle>
                                <CardDescription>
                                    Semua tombol langsung menuju halaman kerja
                                    yang relevan untuk tahap Anda sekarang.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid gap-3 md:grid-cols-2">
                                    {quickActions.map((action) => {
                                        const Icon = action.icon;
                                        const isPrimary =
                                            action.variant === 'default';

                                        if (!action.enabled) {
                                            return (
                                                <div
                                                    key={action.title}
                                                    className="grid min-h-24 grid-cols-[36px_minmax(0,1fr)] items-start gap-3 rounded-xl border border-dashed bg-muted/20 p-4 opacity-70"
                                                >
                                                    <span className="inline-flex size-9 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                                        <Icon className="size-4" />
                                                    </span>
                                                    <span className="grid min-w-0 gap-1">
                                                        <span className="text-sm font-medium text-foreground">
                                                            {action.title}
                                                        </span>
                                                        <span className="line-clamp-2 text-xs leading-5 text-muted-foreground">
                                                            {action.description}
                                                        </span>
                                                    </span>
                                                </div>
                                            );
                                        }

                                        return (
                                            <Link
                                                key={action.title}
                                                href={action.href}
                                                className={cn(
                                                    'grid min-h-24 grid-cols-[36px_minmax(0,1fr)] items-start gap-3 rounded-xl border p-4 transition',
                                                    isPrimary
                                                        ? 'border-primary/20 bg-primary text-primary-foreground hover:bg-primary/92'
                                                        : 'border-border bg-background hover:border-primary/20 hover:bg-muted/20',
                                                )}
                                            >
                                                <span
                                                    className={cn(
                                                        'inline-flex size-9 items-center justify-center rounded-lg',
                                                        isPrimary
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
                                                            isPrimary
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

                        <Card className="shadow-sm">
                            <CardHeader>
                                <CardTitle>Kegiatan Mendatang</CardTitle>
                                <CardDescription>
                                    Daftar terdekat lebih hemat ruang daripada
                                    kalender penuh, dan lebih cepat dibaca di
                                    dashboard.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
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
                                                            <span className="text-xs text-muted-foreground">
                                                                {
                                                                    activity.status
                                                                }
                                                            </span>
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
                                                            {activity.date ??
                                                                '-'}
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
                                        description="Saat ada bimbingan, sempro, atau sidang terjadwal, semuanya akan muncul di sini."
                                    />
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="grid content-start gap-6">
                        <Card className="shadow-sm">
                            <CardHeader>
                                <CardTitle>Dosen Pembimbing</CardTitle>
                                <CardDescription>
                                    Klik kartu untuk membuka profil dosen yang
                                    sedang terhubung dengan proyek Anda.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {summary.advisors.length > 0 ? (
                                    <div className="grid gap-3">
                                        {summary.advisors.map(
                                            (advisor, index) => (
                                                <PersonCardLink
                                                    key={advisor.id}
                                                    person={advisor}
                                                    label={`Pembimbing ${index + 1}`}
                                                />
                                            ),
                                        )}
                                    </div>
                                ) : (
                                    <EmptyState
                                        icon={Users}
                                        title="Belum ada pembimbing aktif"
                                        description="Pembimbing akan tampil di sini setelah ditetapkan admin."
                                    />
                                )}
                            </CardContent>
                        </Card>

                        <Card className="shadow-sm">
                            <CardHeader>
                                <CardTitle>Timeline Progres</CardTitle>
                                <CardDescription>
                                    Tahapan utama disusun dari data asli tugas
                                    akhir dan ujian Anda.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="relative">
                                    <div className="absolute top-2 left-3.5 h-[calc(100%-1rem)] w-px bg-border" />
                                    <div className="grid gap-5">
                                        {timeline.map((step) => {
                                            const isDone =
                                                step.status === 'done';
                                            const isCurrent =
                                                step.status === 'current';

                                            return (
                                                <div
                                                    key={step.title}
                                                    className="relative grid grid-cols-[28px_1fr] gap-4"
                                                >
                                                    <div
                                                        className={cn(
                                                            'mt-0.5 flex size-7 items-center justify-center rounded-full border bg-background',
                                                            isDone
                                                                ? 'border-primary bg-primary text-primary-foreground'
                                                                : '',
                                                            isCurrent
                                                                ? 'border-primary'
                                                                : '',
                                                        )}
                                                    >
                                                        {isDone ? (
                                                            <Check className="size-4" />
                                                        ) : (
                                                            <span
                                                                className={cn(
                                                                    'size-2 rounded-full bg-muted-foreground/40',
                                                                    isCurrent
                                                                        ? 'bg-primary'
                                                                        : '',
                                                                )}
                                                            />
                                                        )}
                                                    </div>

                                                    <div className="space-y-1">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <p className="text-sm font-semibold text-foreground">
                                                                {step.title}
                                                            </p>
                                                            {step.date ? (
                                                                <span className="text-xs text-muted-foreground">
                                                                    {step.date}
                                                                </span>
                                                            ) : null}
                                                        </div>
                                                        <p className="text-sm leading-6 text-muted-foreground">
                                                            {step.description}
                                                        </p>
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
            </div>
        </AppLayout>
    );
}
