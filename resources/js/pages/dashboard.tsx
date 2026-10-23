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

import { EmptyState } from '@/components/patterns/empty-state';
import { SectionCard } from '@/components/patterns/section-card';
import { PersonCardLink } from '@/components/profile/person-card-link';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
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

/* ─── Upcoming activity type tokens ────────────────────────────── */
const activityTypeBadge: Record<string, string> = {
    bimbingan: 'bg-info-muted text-info-muted-foreground',
    sempro: 'bg-primary-muted text-primary-muted-foreground',
    sidang: 'bg-warning-muted text-warning-muted-foreground',
};

const activityTypeIcon: Record<string, string> = {
    bimbingan: 'bg-info-muted text-info-muted-foreground',
    sempro: 'bg-primary-muted text-primary-muted-foreground',
    sidang: 'bg-warning-muted text-warning-muted-foreground',
};

const fallbackActivityClass = 'bg-muted text-muted-foreground';

const listRowFocus =
    'focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset focus-visible:outline-none';

export default function DashboardPage() {
    const {
        summary,
        quickActionState,
        upcomingActivities,
        timeline,
        academicTerminology: terms,
    } = usePage<SharedData & DashboardProps>().props;

    const quickActions: Array<{
        title: string;
        description: string;
        href: string;
        icon: typeof FileText;
        enabled: boolean;
        primary: boolean;
    }> = [
        {
            title: quickActionState.canSubmitTitle
                ? 'Ajukan Judul'
                : `Buka ${terms.finalWork}`,
            description: quickActionState.canSubmitTitle
                ? `Mulai pengajuan judul dan ${terms.proposalDocument.toLowerCase()} ${terms.finalWorkLower}.`
                : `Lihat status ${terms.proposalDocument.toLowerCase()}, dosen, dan detail ${terms.finalWorkLower}.`,
            href: tugasAkhir().url,
            icon: FileText,
            enabled: true,
            primary: true,
        },
        {
            title: 'Ajukan Bimbingan',
            description: quickActionState.canScheduleMeeting
                ? 'Buat janji dengan pembimbing atau penguji.'
                : 'Aktif setelah pembimbing atau penguji terhubung.',
            href: jadwalBimbingan({ query: { open: 'ajukan' } }).url,
            icon: CalendarClock,
            enabled: quickActionState.canScheduleMeeting,
            primary: false,
        },
        {
            title: 'Upload Dokumen',
            description: quickActionState.canUploadDocument
                ? `Unggah ${terms.proposalDocument.toLowerCase()}, revisi, atau dokumen terbaru.`
                : `Aktif saat ada pembimbing atau ${terms.proposalExamShort} aktif.`,
            href: uploadDokumen({ query: { open: 'unggah' } }).url,
            icon: Upload,
            enabled: quickActionState.canUploadDocument,
            primary: false,
        },
        {
            title: 'Buka Pesan',
            description: quickActionState.hasThreads
                ? 'Lanjutkan percakapan dengan pembimbing.'
                : 'Aktif saat thread akademik tersedia.',
            href: pesan().url,
            icon: MessageSquareText,
            enabled: true,
            primary: false,
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Dashboard Mahasiswa"
            subtitle={`Ringkasan ${terms.finalWorkLower}, aksi cepat, dan agenda akademik terdekat`}
        >
            <Head title="Dashboard Mahasiswa" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-6 lg:gap-8">
                {/* ── Hero ── */}
                <Card className="overflow-hidden border-border/70 p-0 shadow-sm">
                    <CardContent className="bg-gradient-to-br from-background via-background to-accent/12 p-6 lg:p-8">
                        <div className="grid gap-6 lg:grid-cols-[1.3fr_0.7fr] lg:gap-8">
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
                                        Judul {terms.finalWorkLower}{' '}
                                        {summary.studentName}:
                                    </p>
                                    <h1 className="max-w-3xl text-2xl font-semibold tracking-tight text-balance text-foreground lg:text-3xl">
                                        {summary.projectTitle ??
                                            `Siapkan pengajuan judul dan mulai perjalanan ${terms.finalWorkLower} Anda.`}
                                    </h1>
                                    {summary.projectTitleEn ? (
                                        <p className="max-w-3xl text-sm leading-6 text-pretty text-muted-foreground italic lg:text-base">
                                            {summary.projectTitleEn}
                                        </p>
                                    ) : null}
                                </div>
                            </div>

                            <div className="flex flex-col justify-center border-t pt-6 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-8">
                                <h2 className="text-xs font-medium text-muted-foreground">
                                    Status saat ini
                                </h2>
                                <p className="mt-2 text-2xl font-semibold tracking-tight text-balance text-foreground">
                                    {summary.workflow.label}
                                </p>
                                <p className="mt-2 text-sm leading-6 text-pretty text-muted-foreground">
                                    {summary.workflow.description}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid items-start gap-6 xl:grid-cols-[2fr_1fr]">
                    <div className="grid content-start gap-6">
                        {/* ── Aksi Cepat ── */}
                        <SectionCard
                            title="Aksi Cepat"
                            description="Pintasan untuk langkah yang paling sering Anda gunakan"
                            className="gap-4 overflow-hidden pb-0"
                            contentClassName="px-0"
                        >
                            <ul className="divide-y border-t">
                                {quickActions.map((action) => {
                                    const Icon = action.icon;

                                    if (!action.enabled) {
                                        return (
                                            <li
                                                key={action.title}
                                                className="flex items-center gap-4 bg-muted/40 px-6 py-4"
                                            >
                                                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                                                    <Icon className="size-5" />
                                                </span>
                                                <div className="min-w-0 flex-1">
                                                    <p className="text-sm font-semibold text-muted-foreground">
                                                        {action.title}
                                                    </p>
                                                    <p className="mt-0.5 line-clamp-2 text-xs leading-relaxed text-pretty text-muted-foreground">
                                                        {action.description}
                                                    </p>
                                                </div>
                                            </li>
                                        );
                                    }

                                    return (
                                        <li key={action.title}>
                                            <Link
                                                href={action.href}
                                                className={cn(
                                                    'group flex min-h-14 items-center gap-4 px-6 py-4 transition-colors',
                                                    listRowFocus,
                                                    action.primary
                                                        ? 'bg-primary text-primary-foreground hover:bg-primary/90'
                                                        : 'hover:bg-muted/50',
                                                )}
                                            >
                                                <span
                                                    className={cn(
                                                        'flex size-10 shrink-0 items-center justify-center rounded-xl transition-transform group-hover:scale-105',
                                                        action.primary
                                                            ? 'bg-primary-foreground/15 text-primary-foreground'
                                                            : 'bg-primary-muted text-primary-muted-foreground',
                                                    )}
                                                >
                                                    <Icon className="size-5" />
                                                </span>
                                                <div className="min-w-0 flex-1">
                                                    <p
                                                        className={cn(
                                                            'text-sm font-semibold',
                                                            action.primary
                                                                ? 'text-primary-foreground'
                                                                : 'text-foreground',
                                                        )}
                                                    >
                                                        {action.title}
                                                    </p>
                                                    <p
                                                        className={cn(
                                                            'mt-0.5 line-clamp-2 text-xs leading-relaxed text-pretty',
                                                            action.primary
                                                                ? 'text-primary-foreground'
                                                                : 'text-muted-foreground',
                                                        )}
                                                    >
                                                        {action.description}
                                                    </p>
                                                </div>
                                                <ChevronRight
                                                    className={cn(
                                                        'size-4 shrink-0 transition-transform group-hover:translate-x-0.5',
                                                        action.primary
                                                            ? 'text-primary-foreground'
                                                            : 'text-muted-foreground',
                                                    )}
                                                />
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        </SectionCard>

                        {/* ── Kegiatan Mendatang ── */}
                        <SectionCard
                            title="Kegiatan Mendatang"
                            description={`Jadwal bimbingan, ${terms.proposalExamShort}, dan ${terms.finalExam} terdekat.`}
                            className="overflow-hidden pb-0"
                            contentClassName="px-0"
                        >
                            {upcomingActivities.length > 0 ? (
                                <ul className="divide-y border-t">
                                    {upcomingActivities.map((activity) => (
                                        <li key={activity.id}>
                                            <Link
                                                href={activity.href}
                                                className={cn(
                                                    'group flex min-h-14 items-center gap-4 px-6 py-3.5 transition-colors hover:bg-muted/50',
                                                    listRowFocus,
                                                )}
                                            >
                                                <span
                                                    className={cn(
                                                        'flex size-8 shrink-0 items-center justify-center rounded-lg',
                                                        activityTypeIcon[
                                                            activity.type
                                                        ] ??
                                                            fallbackActivityClass,
                                                    )}
                                                >
                                                    <CalendarClock className="size-4" />
                                                </span>

                                                <div className="min-w-0 flex-1">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <p className="text-sm font-medium text-foreground">
                                                            {activity.title}
                                                        </p>
                                                        <Badge
                                                            variant="outline"
                                                            className={cn(
                                                                'rounded-full border-transparent text-xs font-medium',
                                                                activityTypeBadge[
                                                                    activity
                                                                        .type
                                                                ] ??
                                                                    fallbackActivityClass,
                                                            )}
                                                        >
                                                            {activity.badge}
                                                        </Badge>
                                                    </div>
                                                    <p className="mt-0.5 text-xs text-pretty text-muted-foreground">
                                                        {activity.subtitle}
                                                    </p>
                                                    <p className="mt-1 text-xs text-muted-foreground sm:hidden">
                                                        <span className="font-medium text-foreground">
                                                            {activity.date ??
                                                                '-'}
                                                        </span>
                                                        {' · '}
                                                        {activity.status}
                                                    </p>
                                                </div>

                                                <div className="hidden shrink-0 items-center gap-3 sm:flex">
                                                    <div className="text-right">
                                                        <p className="text-xs font-medium text-foreground">
                                                            {activity.date ??
                                                                '-'}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {activity.status}
                                                        </p>
                                                    </div>
                                                </div>
                                                <ChevronRight className="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <div className="px-6 pb-6">
                                    <EmptyState
                                        icon={CalendarClock}
                                        title="Belum ada agenda mendatang"
                                        description={`Saat ada bimbingan, ${terms.proposalExamShort}, atau ${terms.finalExam} terjadwal, semuanya akan muncul di sini.`}
                                        className="rounded-none border-0 bg-transparent p-0"
                                    />
                                </div>
                            )}
                        </SectionCard>
                    </div>

                    <div className="grid content-start gap-6">
                        {/* ── Dosen Pembimbing ── */}
                        <SectionCard
                            title="Dosen Pembimbing"
                            description={`Pembimbing aktif untuk ${terms.finalWorkLower} Anda.`}
                            className="overflow-hidden pb-0"
                            contentClassName="px-0"
                        >
                            {summary.advisors.length > 0 ? (
                                <ul className="divide-y border-t">
                                    {summary.advisors.map((advisor, index) => (
                                        <li key={advisor.id}>
                                            <PersonCardLink
                                                person={advisor}
                                                label={`Pembimbing ${index + 1}`}
                                                className={cn(
                                                    'min-h-14 rounded-none border-0 px-6 py-3.5 hover:bg-muted/50',
                                                    listRowFocus,
                                                )}
                                            />
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <div className="px-6 pb-6">
                                    <EmptyState
                                        icon={Users}
                                        title="Belum ada pembimbing aktif"
                                        description="Pembimbing akan tampil di sini setelah ditetapkan admin."
                                        className="rounded-none border-0 bg-transparent p-0"
                                    />
                                </div>
                            )}
                        </SectionCard>

                        {/* ── Timeline Progres ── */}
                        <SectionCard
                            title="Timeline Progres"
                            description={`Tahap utama perjalanan ${terms.finalWorkLower} Anda`}
                        >
                            <ol className="relative">
                                {timeline.map((step, index) => {
                                    const isDone = step.status === 'done';
                                    const isCurrent = step.status === 'current';
                                    const isLast =
                                        index === timeline.length - 1;

                                    return (
                                        <li
                                            key={step.title}
                                            aria-current={
                                                isCurrent ? 'step' : undefined
                                            }
                                            className="relative flex gap-4"
                                        >
                                            <div className="flex flex-col items-center">
                                                <div
                                                    className={cn(
                                                        'relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full border-2',
                                                        isDone
                                                            ? 'border-primary bg-primary text-primary-foreground'
                                                            : isCurrent
                                                              ? 'border-primary bg-background'
                                                              : 'border-border bg-muted',
                                                    )}
                                                >
                                                    {isDone ? (
                                                        <Check className="size-4" />
                                                    ) : isCurrent ? (
                                                        <Circle className="size-3 fill-primary text-primary" />
                                                    ) : (
                                                        <Circle className="size-2.5 fill-muted-foreground text-muted-foreground" />
                                                    )}

                                                    {isCurrent && (
                                                        <span className="absolute inset-0 rounded-full border-2 border-primary/20 motion-safe:animate-ping" />
                                                    )}
                                                </div>

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

                                            <div
                                                className={cn(
                                                    'min-w-0 pb-6',
                                                    isLast && 'pb-0',
                                                )}
                                            >
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p
                                                        className={cn(
                                                            'text-sm font-semibold',
                                                            isCurrent
                                                                ? 'text-primary'
                                                                : isDone
                                                                  ? 'text-foreground'
                                                                  : 'text-muted-foreground',
                                                        )}
                                                    >
                                                        {step.title}
                                                    </p>
                                                    {step.date && (
                                                        <span
                                                            className={cn(
                                                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                                                isDone ||
                                                                    isCurrent
                                                                    ? 'bg-primary-muted text-primary-muted-foreground'
                                                                    : 'bg-muted text-muted-foreground',
                                                            )}
                                                        >
                                                            {step.date}
                                                        </span>
                                                    )}
                                                </div>
                                                <p className="mt-1 text-xs leading-relaxed text-pretty text-muted-foreground">
                                                    {step.description}
                                                </p>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ol>
                        </SectionCard>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
