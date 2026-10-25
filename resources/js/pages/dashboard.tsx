import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    CalendarClock,
    Check,
    Circle,
    FileText,
    MessageSquareText,
    Upload,
    Users,
    type LucideIcon,
} from 'lucide-react';

import {
    ActionQueue,
    ActionQueueItem,
} from '@/components/patterns/action-queue';
import { EmptyState } from '@/components/patterns/empty-state';
import { PageHeader } from '@/components/patterns/page-header';
import { StatCard } from '@/components/patterns/stat-card';
import { PersonCardLink } from '@/components/profile/person-card-link';
import { Button } from '@/components/ui/button';
import { useIsMobile } from '@/hooks/use-mobile';
import AppLayout from '@/layouts/app-layout';
import { type StatusTone } from '@/lib/status-styles';
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
    stats: Array<{
        title: string;
        value: string;
        description: string;
        icon: string;
    }>;
    quickActionState: QuickActionState;
    upcomingActivities: UpcomingActivity[];
    timeline: TimelineStep[];
};

const statIcons: Record<string, LucideIcon> = {
    'file-text': FileText,
    users: Users,
    'calendar-clock': CalendarClock,
    'message-square-text': MessageSquareText,
};
const statTones: StatusTone[] = ['primary', 'success', 'info', 'warning'];
const listRowFocus =
    'focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset focus-visible:outline-none';

export default function DashboardPage() {
    const {
        summary,
        stats,
        quickActionState,
        upcomingActivities,
        timeline,
        academicTerminology: terms,
    } = usePage<SharedData & DashboardProps>().props;
    const isMobile = useIsMobile();
    const mainAction = {
        title: quickActionState.canSubmitTitle
            ? 'Ajukan Judul'
            : summary.workflow.label,
        label: quickActionState.canSubmitTitle
            ? 'Ajukan Judul'
            : `Buka ${terms.finalWork}`,
        description: quickActionState.canSubmitTitle
            ? `Mulai pengajuan judul dan ${terms.proposalDocument.toLowerCase()} ${terms.finalWorkLower}.`
            : `Lihat status ${terms.proposalDocument.toLowerCase()}, dosen, dan detail ${terms.finalWorkLower}.`,
    };
    const secondaryActions = [
        {
            title: 'Ajukan Bimbingan',
            unavailable: 'Aktif setelah pembimbing atau penguji terhubung.',
            href: jadwalBimbingan({ query: { open: 'ajukan' } }).url,
            icon: CalendarClock,
            enabled: quickActionState.canScheduleMeeting,
        },
        {
            title: 'Upload Dokumen',
            unavailable: `Aktif saat ada pembimbing atau ${terms.proposalExamShort} aktif.`,
            href: uploadDokumen({ query: { open: 'unggah' } }).url,
            icon: Upload,
            enabled: quickActionState.canUploadDocument,
        },
    ];
    const summaryDestinations = [
        tugasAkhir().url,
        '#dosen-pembimbing',
        '#agenda-mendatang',
        pesan().url,
    ];

    const summarySection = (
        <section aria-label="Ringkasan akademik">
            <PageHeader
                level={2}
                title="Ringkasan akademik"
                className="sr-only"
            />
            <div className="grid grid-cols-2 overflow-hidden rounded-xl border bg-card lg:grid-cols-4">
                {stats.map((stat, index) => {
                    const href = summaryDestinations[index];
                    const card = (
                        <StatCard
                            {...stat}
                            icon={statIcons[stat.icon] ?? FileText}
                            variant="snapshot"
                            tone={statTones[index]}
                            className={cn(
                                'rounded-none border-0 bg-transparent shadow-none',
                                index === 0 &&
                                    '[&_[data-slot=card-title]]:text-base [&_[data-slot=card-title]]:leading-snug xl:[&_[data-slot=card-title]]:text-xl',
                            )}
                        />
                    );
                    const linkClassName = cn(
                        'block h-full min-h-11 min-w-0 transition-colors hover:bg-muted',
                        listRowFocus,
                    );

                    return (
                        <div
                            key={stat.title}
                            role="group"
                            aria-label={stat.title}
                            className={cn(
                                'min-w-0 border-b lg:border-b-0',
                                index % 2 === 0 && 'border-r',
                                index === 1 && 'lg:border-r',
                                index > 1 && 'max-lg:border-b-0',
                            )}
                        >
                            {href.startsWith('#') ? (
                                <a href={href} className={linkClassName}>
                                    {card}
                                </a>
                            ) : (
                                <Link href={href} className={linkClassName}>
                                    {card}
                                </Link>
                            )}
                        </div>
                    );
                })}
            </div>
        </section>
    );

    const actionSection = (
        <section aria-label="Tindakan Anda" className="grid min-w-0 gap-4">
            <PageHeader
                level={2}
                title="Tindakan Anda"
                actions={
                    <Button
                        asChild
                        variant="ghost"
                        className="min-h-11 gap-2 text-primary"
                    >
                        <Link
                            href={pesan().url}
                            title={
                                quickActionState.hasThreads
                                    ? 'Lanjutkan percakapan dengan pembimbing.'
                                    : 'Aktif saat thread akademik tersedia.'
                            }
                        >
                            <MessageSquareText aria-hidden="true" />
                            Buka Pesan
                        </Link>
                    </Button>
                }
            />
            <ActionQueue label="Tindakan Anda" layout="bento">
                <ActionQueueItem
                    variant="featured"
                    title={mainAction.title}
                    icon={FileText}
                    description={mainAction.description}
                    className="md:col-span-2"
                    action={
                        <Button asChild className="min-h-11 gap-2">
                            <Link href={tugasAkhir().url}>
                                {mainAction.label}
                                <ArrowUpRight aria-hidden="true" />
                            </Link>
                        </Button>
                    }
                />
                {secondaryActions.map((action) => (
                    <ActionQueueItem
                        key={action.title}
                        variant="card"
                        title={action.title}
                        icon={action.icon}
                        tone={action.enabled ? 'primary' : 'neutral'}
                        description={
                            action.enabled ? undefined : action.unavailable
                        }
                        action={
                            action.enabled ? (
                                <Button
                                    asChild
                                    variant="ghost"
                                    size="sm"
                                    className="min-h-11 gap-2 px-0 text-xs text-primary hover:bg-primary-muted hover:text-primary-muted-foreground"
                                >
                                    <Link href={action.href}>
                                        {action.title}
                                        <ArrowUpRight aria-hidden="true" />
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    disabled
                                    variant="ghost"
                                    size="sm"
                                    className="min-h-11 text-xs"
                                >
                                    {action.title}
                                </Button>
                            )
                        }
                    />
                ))}
            </ActionQueue>
        </section>
    );

    const agendaSection = (
        <section
            id="agenda-mendatang"
            aria-label="Agenda mendatang"
            className="grid min-w-0 scroll-mt-24 content-start gap-4 self-start"
        >
            <PageHeader
                level={2}
                title="Agenda mendatang"
                description={`Bimbingan, ${terms.proposalExamShort}, dan ${terms.finalExam} terdekat.`}
            />
            {upcomingActivities.length > 0 ? (
                <ul className="divide-y border-l border-primary/25 pl-4">
                    {upcomingActivities.map((activity) => (
                        <li key={activity.id} className="relative">
                            <span
                                aria-hidden="true"
                                className="absolute top-5 -left-5 size-2 rounded-full border border-primary bg-card"
                            />
                            <Link
                                href={activity.href}
                                className={cn(
                                    'group grid min-h-11 gap-2 rounded-md px-2 py-4 transition-colors hover:bg-accent/50',
                                    listRowFocus,
                                )}
                            >
                                <span className="text-sm font-semibold text-primary tabular-nums">
                                    {activity.date ?? '-'}
                                </span>
                                <span className="flex flex-wrap items-center justify-between gap-2 text-xs">
                                    <span className="font-medium">
                                        {activity.badge}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {activity.status}
                                    </span>
                                </span>
                                <span className="text-sm font-medium text-pretty group-hover:underline">
                                    {activity.title}
                                </span>
                                <span className="text-xs leading-relaxed text-pretty text-muted-foreground">
                                    {activity.subtitle}
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            ) : (
                <EmptyState
                    icon={CalendarClock}
                    title="Belum ada agenda mendatang"
                    description={`Saat ada bimbingan, ${terms.proposalExamShort}, atau ${terms.finalExam} terjadwal, semuanya akan muncul di sini.`}
                    className="rounded-none border-0 bg-transparent px-0 py-4"
                />
            )}
        </section>
    );

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Dashboard Mahasiswa"
            subtitle={`Ringkasan ${terms.finalWorkLower}, tindakan, dan agenda akademik`}
        >
            <Head title="Dashboard Mahasiswa" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-6 lg:gap-8 lg:py-8">
                <PageHeader
                    title="Dashboard mahasiswa"
                    description={`${summary.studentName}${summary.programStudi ? ` · ${summary.programStudi}` : ''}`}
                />
                {!isMobile && summarySection}
                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(18rem,1fr)] xl:gap-8">
                    {actionSection}
                    {isMobile && summarySection}
                    {agendaSection}
                </div>
                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(18rem,1fr)] xl:gap-8">
                    <section
                        aria-label="Progres akademik"
                        className="grid min-w-0 gap-4"
                    >
                        <PageHeader
                            level={2}
                            title="Progres akademik"
                            actions={
                                <p className="text-sm text-muted-foreground">
                                    Progres tahapan{' '}
                                    <span className="font-semibold text-foreground tabular-nums">
                                        {summary.progress}%
                                    </span>
                                </p>
                            }
                        />
                        <div className="divide-y overflow-hidden rounded-xl border bg-card">
                            <div className="grid gap-3 p-5 md:p-6">
                                {summary.projectTitle ? (
                                    <>
                                        <h3 className="text-lg leading-snug font-semibold text-pretty break-words">
                                            {summary.projectTitle}
                                        </h3>
                                        {summary.projectTitleEn && (
                                            <p className="text-sm leading-relaxed text-pretty break-words text-muted-foreground italic">
                                                {summary.projectTitleEn}
                                            </p>
                                        )}
                                    </>
                                ) : (
                                    <p className="text-sm leading-relaxed text-muted-foreground">
                                        Siapkan pengajuan judul dan mulai
                                        perjalanan {terms.finalWorkLower} Anda.
                                    </p>
                                )}
                                {summary.startedAt && (
                                    <p className="text-xs text-muted-foreground">
                                        Mulai {summary.startedAt}
                                    </p>
                                )}
                            </div>
                            <ol className="divide-y">
                                {timeline.map((step) => (
                                    <li
                                        key={step.title}
                                        aria-current={
                                            step.status === 'current'
                                                ? 'step'
                                                : undefined
                                        }
                                        className="flex gap-4 px-5 py-4 md:px-6"
                                    >
                                        <span
                                            aria-hidden="true"
                                            className={cn(
                                                'mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full',
                                                step.status === 'done'
                                                    ? 'bg-primary text-primary-foreground'
                                                    : step.status === 'current'
                                                      ? 'bg-primary-muted text-primary-muted-foreground'
                                                      : 'bg-muted text-muted-foreground',
                                            )}
                                        >
                                            {step.status === 'done' ? (
                                                <Check className="size-4" />
                                            ) : (
                                                <Circle
                                                    className={cn(
                                                        'size-3',
                                                        step.status ===
                                                            'current' &&
                                                            'fill-current',
                                                    )}
                                                />
                                            )}
                                        </span>
                                        <div className="grid min-w-0 flex-1 gap-1">
                                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                                <h3 className="text-sm font-semibold">
                                                    {step.title}
                                                </h3>
                                                {step.date && (
                                                    <span className="text-xs text-muted-foreground tabular-nums">
                                                        {step.date}
                                                    </span>
                                                )}
                                            </div>
                                            <p className="text-xs leading-relaxed text-pretty text-muted-foreground">
                                                {step.description}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </section>
                    <section
                        id="dosen-pembimbing"
                        aria-label="Dosen pembimbing"
                        className="grid min-w-0 scroll-mt-24 gap-4"
                    >
                        <PageHeader
                            level={2}
                            title="Dosen pembimbing"
                            description={`Pembimbing aktif untuk ${terms.finalWorkLower} Anda.`}
                        />
                        {summary.advisors.length > 0 ? (
                            <ul className="divide-y overflow-hidden rounded-xl border bg-card">
                                {summary.advisors.map((advisor, index) => (
                                    <li key={advisor.id}>
                                        <PersonCardLink
                                            person={advisor}
                                            label={`Pembimbing ${index + 1}`}
                                            variant="row"
                                            className={cn(
                                                'min-h-11',
                                                listRowFocus,
                                            )}
                                        />
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <EmptyState
                                icon={Users}
                                title="Belum ada pembimbing aktif"
                                description="Pembimbing akan tampil di sini setelah ditetapkan admin."
                                className="border-solid bg-card"
                            />
                        )}
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}
