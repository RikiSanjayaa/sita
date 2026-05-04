import { Head, Link, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    Check,
    ChevronRight,
    Circle,
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
    projectTitleEn: string | null;
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

const sectionCardClass = 'overflow-hidden gap-0 py-0 shadow-sm';
const sectionCardHeaderClass = 'border-b bg-muted/20 px-6 py-4';

/* ─── Quick Action config ──────────────────────────────────────── */
const quickActionColors = {
    primary: {
        bg: 'bg-primary',
        bgHover: 'hover:bg-primary/92',
        icon: 'bg-primary-foreground/15 text-primary-foreground',
        text: 'text-primary-foreground',
        desc: 'text-primary-foreground/75',
        border: 'border-primary/20',
        arrow: 'text-primary-foreground/50 group-hover:text-primary-foreground/80',
    },

    outline: {
        bg: 'bg-card',
        bgHover: 'hover:border-primary/30 hover:bg-primary/5',
        icon: 'bg-primary/10 text-primary dark:text-primary',
        text: 'text-foreground group-hover:text-primary dark:group-hover:text-primary',
        desc: 'text-muted-foreground',
        border: 'border-border',
        arrow: 'text-muted-foreground/30 group-hover:text-primary',
    },
} as const;

type ColorKey = keyof typeof quickActionColors;

/* ─── Upcoming activity type-to-color mapping ──────────────────── */
const activityTypeColor: Record<string, string> = {
    bimbingan:
        'border-cyan-500/50 bg-cyan-500/10 text-cyan-700 dark:text-cyan-400',
    sempro: 'border-purple-500/50 bg-purple-500/10 text-purple-700 dark:text-purple-400',
    sidang: 'border-amber-500/50 bg-amber-500/10 text-amber-700 dark:text-amber-400',
};

const activityTypeIcon: Record<string, string> = {
    bimbingan: 'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400',
    sempro: 'bg-purple-500/10 text-purple-600 dark:text-purple-400',
    sidang: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
};

export default function DashboardPage() {
    const { summary, quickActionState, upcomingActivities, timeline } = usePage<
        SharedData & DashboardProps
    >().props;

    const quickActions: Array<{
        title: string;
        description: string;
        href: string;
        icon: typeof FileText;
        enabled: boolean;
        color: ColorKey;
    }> = [
        {
            title: summary.hasProject ? 'Buka Tugas Akhir' : 'Ajukan Judul',
            description: summary.hasProject
                ? 'Lihat status proposal, dosen, dan detail tugas akhir.'
                : 'Mulai pengajuan judul dan proposal pertama Anda.',
            href: tugasAkhir().url,
            icon: FileText,
            enabled: true,
            color: 'primary',
        },
        {
            title: 'Ajukan Bimbingan',
            description: quickActionState.canScheduleMeeting
                ? 'Buat janji dengan pembimbing atau penguji.'
                : 'Aktif setelah pembimbing atau penguji terhubung.',
            href: jadwalBimbingan({ query: { open: 'ajukan' } }).url,
            icon: CalendarClock,
            enabled: quickActionState.canScheduleMeeting,
            color: 'outline',
        },
        {
            title: 'Upload Dokumen',
            description: quickActionState.canUploadDocument
                ? 'Unggah proposal, revisi, atau dokumen terbaru.'
                : 'Aktif saat ada pembimbing atau sempro aktif.',
            href: uploadDokumen({ query: { open: 'unggah' } }).url,
            icon: Upload,
            enabled: quickActionState.canUploadDocument,
            color: 'outline',
        },
        {
            title: 'Buka Pesan',
            description: quickActionState.hasThreads
                ? 'Lanjutkan percakapan dengan pembimbing.'
                : 'Aktif saat thread akademik tersedia.',
            href: pesan().url,
            icon: MessageSquareText,
            enabled: true,
            color: 'outline',
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
                {/* ── Hero Card ── (unchanged) */}
                <Card className="overflow-hidden border-border/70 p-0 shadow-sm">
                    <CardContent className="bg-gradient-to-br from-background via-background to-accent/12 p-6 lg:p-8">
                        <div className="grid gap-8 lg:grid-cols-[1.3fr_0.7fr] lg:items-center">
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
                                    {summary.projectTitleEn ? (
                                        <p className="max-w-3xl text-sm leading-6 text-muted-foreground italic lg:text-base">
                                            {summary.projectTitleEn}
                                        </p>
                                    ) : null}
                                </div>
                            </div>

                            <div className="rounded-2xl border border-border/80 bg-card/95 p-5 shadow-sm backdrop-blur">
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
                        {/* ── Aksi Cepat (redesigned) ── */}
                        <Card className={sectionCardClass}>
                            <CardHeader className={sectionCardHeaderClass}>
                                <CardTitle>Aksi Cepat</CardTitle>
                                <CardDescription>
                                    Pintasan untuk langkah yang paling sering
                                    Anda gunakan
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="py-6">
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {quickActions.map((action) => {
                                        const Icon = action.icon;
                                        const colors =
                                            quickActionColors[action.color];

                                        if (!action.enabled) {
                                            return (
                                                <div
                                                    key={action.title}
                                                    className="flex items-center gap-4 rounded-xl border border-dashed bg-muted/10 px-4 py-4 opacity-60"
                                                >
                                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                                                        <Icon className="size-5" />
                                                    </span>
                                                    <div className="min-w-0 flex-1">
                                                        <p className="text-sm font-semibold text-foreground">
                                                            {action.title}
                                                        </p>
                                                        <p className="mt-0.5 line-clamp-2 text-xs leading-relaxed text-muted-foreground">
                                                            {action.description}
                                                        </p>
                                                    </div>
                                                </div>
                                            );
                                        }

                                        return (
                                            <Link
                                                key={action.title}
                                                href={action.href}
                                                className={cn(
                                                    'group flex items-center gap-4 rounded-xl border px-4 py-4 transition-all',
                                                    colors.bg,
                                                    colors.bgHover,
                                                    colors.border,
                                                )}
                                            >
                                                <span
                                                    className={cn(
                                                        'flex size-10 shrink-0 items-center justify-center rounded-xl transition-transform group-hover:scale-105',
                                                        colors.icon,
                                                    )}
                                                >
                                                    <Icon className="size-5" />
                                                </span>
                                                <div className="min-w-0 flex-1">
                                                    <p
                                                        className={cn(
                                                            'text-sm font-semibold transition-colors',
                                                            colors.text,
                                                        )}
                                                    >
                                                        {action.title}
                                                    </p>
                                                    <p
                                                        className={cn(
                                                            'mt-0.5 line-clamp-2 text-xs leading-relaxed',
                                                            colors.desc,
                                                        )}
                                                    >
                                                        {action.description}
                                                    </p>
                                                </div>
                                                <ChevronRight
                                                    className={cn(
                                                        'size-4 shrink-0 transition-all group-hover:translate-x-0.5',
                                                        colors.arrow,
                                                    )}
                                                />
                                            </Link>
                                        );
                                    })}
                                </div>
                            </CardContent>
                        </Card>

                        {/* ── Kegiatan Mendatang (redesigned) ── */}
                        <Card className={sectionCardClass}>
                            <CardHeader className={sectionCardHeaderClass}>
                                <CardTitle>Kegiatan Mendatang</CardTitle>
                                <CardDescription>
                                    Jadwal bimbingan, sempro, dan sidang
                                    terdekat.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="p-0">
                                {upcomingActivities.length > 0 ? (
                                    <div className="divide-y">
                                        {upcomingActivities.map((activity) => {
                                            const typeColor =
                                                activityTypeColor[
                                                    activity.type
                                                ] ??
                                                'bg-muted text-muted-foreground';
                                            const iconColor =
                                                activityTypeIcon[
                                                    activity.type
                                                ] ??
                                                'bg-muted text-muted-foreground';

                                            return (
                                                <Link
                                                    key={activity.id}
                                                    href={activity.href}
                                                    className="group flex items-center gap-4 px-5 py-3.5 transition-colors hover:bg-muted/20"
                                                >
                                                    {/* Icon */}
                                                    <span
                                                        className={cn(
                                                            'flex size-8 shrink-0 items-center justify-center rounded-lg',
                                                            iconColor,
                                                        )}
                                                    >
                                                        <CalendarClock className="size-4" />
                                                    </span>

                                                    {/* Content */}
                                                    <div className="min-w-0 flex-1">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <p className="text-sm font-medium text-foreground transition-colors group-hover:text-primary">
                                                                {activity.title}
                                                            </p>
                                                            <Badge
                                                                variant="outline"
                                                                className={cn(
                                                                    'rounded-full text-[10px] font-semibold',
                                                                    typeColor,
                                                                )}
                                                            >
                                                                {activity.badge}
                                                            </Badge>
                                                        </div>
                                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                                            {activity.subtitle}
                                                        </p>
                                                    </div>

                                                    {/* Date + status + arrow */}
                                                    <div className="hidden shrink-0 items-center gap-3 sm:flex">
                                                        <div className="text-right">
                                                            <p className="text-xs font-medium text-foreground">
                                                                {activity.date ??
                                                                    '-'}
                                                            </p>
                                                            <p className="text-[10px] text-muted-foreground">
                                                                {
                                                                    activity.status
                                                                }
                                                            </p>
                                                        </div>
                                                        <ChevronRight className="size-4 text-muted-foreground/30 transition-all group-hover:translate-x-0.5 group-hover:text-primary" />
                                                    </div>
                                                </Link>
                                            );
                                        })}
                                    </div>
                                ) : (
                                    <div className="px-6 pb-6">
                                        <EmptyState
                                            icon={CalendarClock}
                                            title="Belum ada agenda mendatang"
                                            description="Saat ada bimbingan, sempro, atau sidang terjadwal, semuanya akan muncul di sini."
                                        />
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="grid content-start gap-6">
                        {/* ── Dosen Pembimbing (unchanged) ── */}
                        <Card className={sectionCardClass}>
                            <CardHeader className={sectionCardHeaderClass}>
                                <CardTitle>Dosen Pembimbing</CardTitle>
                                <CardDescription>
                                    Pembimbing aktif untuk tugas akhir Anda.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="py-6">
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

                        {/* ── Timeline Progres (redesigned) ── */}
                        <Card className={sectionCardClass}>
                            <CardHeader className={sectionCardHeaderClass}>
                                <CardTitle>Timeline Progres</CardTitle>
                                <CardDescription>
                                    Tahap utama perjalanan tugas akhir Anda
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="py-6">
                                <div className="relative">
                                    {timeline.map((step, index) => {
                                        const isDone = step.status === 'done';
                                        const isCurrent =
                                            step.status === 'current';
                                        const isLast =
                                            index === timeline.length - 1;

                                        return (
                                            <div
                                                key={step.title}
                                                className="relative flex gap-4"
                                            >
                                                {/* Connector line + node */}
                                                <div className="flex flex-col items-center">
                                                    {/* Node */}
                                                    <div
                                                        className={cn(
                                                            'relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full border-2 transition-all',
                                                            isDone
                                                                ? 'border-primary bg-primary text-primary-foreground shadow-sm shadow-primary/12'
                                                                : isCurrent
                                                                  ? 'border-primary bg-background shadow-sm shadow-primary/8'
                                                                  : 'border-muted bg-muted/50',
                                                        )}
                                                    >
                                                        {isDone ? (
                                                            <Check className="size-4" />
                                                        ) : isCurrent ? (
                                                            <Circle className="size-3 fill-primary text-primary" />
                                                        ) : (
                                                            <Circle className="size-2.5 fill-muted-foreground/30 text-muted-foreground/30" />
                                                        )}

                                                        {/* Pulse ring for current step */}
                                                        {isCurrent && (
                                                            <span className="absolute inset-0 animate-ping rounded-full border-2 border-primary/18" />
                                                        )}
                                                    </div>

                                                    {/* Connector line */}
                                                    {!isLast && (
                                                        <div
                                                            className={cn(
                                                                'w-0.5 flex-1',
                                                                isDone
                                                                    ? 'bg-primary'
                                                                    : 'bg-border',
                                                            )}
                                                        />
                                                    )}
                                                </div>

                                                {/* Content */}
                                                <div
                                                    className={cn(
                                                        'pb-6',
                                                        isLast && 'pb-0',
                                                    )}
                                                >
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <p
                                                            className={cn(
                                                                'text-sm font-semibold',
                                                                isDone
                                                                    ? 'text-foreground'
                                                                    : isCurrent
                                                                      ? 'text-primary'
                                                                      : 'text-muted-foreground',
                                                            )}
                                                        >
                                                            {step.title}
                                                        </p>
                                                        {step.date && (
                                                            <span
                                                                className={cn(
                                                                    'rounded-full px-2 py-0.5 text-[10px] font-medium',
                                                                    isDone
                                                                        ? 'bg-primary/10 text-primary'
                                                                        : isCurrent
                                                                          ? 'bg-primary/10 text-primary'
                                                                          : 'bg-muted text-muted-foreground',
                                                                )}
                                                            >
                                                                {step.date}
                                                            </span>
                                                        )}
                                                    </div>
                                                    <p
                                                        className={cn(
                                                            'mt-1 text-xs leading-relaxed',
                                                            isDone || isCurrent
                                                                ? 'text-muted-foreground'
                                                                : 'text-muted-foreground/60',
                                                        )}
                                                    >
                                                        {step.description}
                                                    </p>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
