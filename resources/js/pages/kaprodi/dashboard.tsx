import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowUpRight,
    CalendarClock,
    CheckCircle2,
    ClipboardCheck,
    FileArchive,
    FileClock,
    GraduationCap,
    Presentation,
    UserRoundX,
    UserSearch,
    UserX,
    Users,
    UsersRound,
    type LucideIcon,
} from 'lucide-react';

import dosenDashboard from '@/actions/App/Http/Controllers/Dosen/DashboardController';
import dashboard from '@/actions/App/Http/Controllers/Kaprodi/DashboardController';
import studentsIndex from '@/actions/App/Http/Controllers/Kaprodi/MahasiswaController';
import exams from '@/actions/App/Http/Controllers/Kaprodi/SemproSidangController';
import {
    ActionQueue,
    ActionQueueItem,
} from '@/components/patterns/action-queue';
import { DataSection } from '@/components/patterns/data-section';
import { EmptyState } from '@/components/patterns/empty-state';
import { PageHeader } from '@/components/patterns/page-header';
import { SectionCard } from '@/components/patterns/section-card';
import { StatCard } from '@/components/patterns/stat-card';
import { StatusBadge } from '@/components/patterns/status-badge';
import { StudentIdentity } from '@/components/patterns/student-cells';
import {
    StudentProgressPeek,
    type StudentProgress,
} from '@/components/profile/student-progress-peek';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useIsMobile } from '@/hooks/use-mobile';
import KaprodiLayout from '@/layouts/kaprodi-layout';
import { neutralAcademicTerminology } from '@/lib/academic-terminology';
import { toneClassName, type StatusTone } from '@/lib/status-styles';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard.url() },
];

type ProgramStudi = {
    id: number;
    name: string;
    slug: string;
};

type ActionItem = {
    id: string;
    label: string;
    description: string;
    value: number;
    href: string;
    tone: Extract<StatusTone, 'danger' | 'warning'>;
    actionLabel: string;
    names: string[];
};

type AgendaItem = {
    id: string;
    badge: string;
    title: string;
    subtitle: string;
    date: string;
    dayLabel: string;
    time: string;
    isToday: boolean;
};

type AttentionStudent = {
    id: number;
    name: string;
    nim: string | null;
    avatar: string | null;
    profileUrl: string;
    level: 'high' | 'medium' | 'low';
    problems: string[];
    progress: StudentProgress | null;
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
    actionItems: ActionItem[];
    upcomingAgenda: AgendaItem[];
    attentionStudents: { total: number; students: AttentionStudent[] };
    stats: {
        students: number;
        archives: number;
        activeStudents: number;
        withoutSupervisors: number;
        upcomingExams: number;
        expiredMentorship: number;
    };
    recentArchives: ArchiveItem[];
    phaseDistribution: DistributionItem[];
    defenseProgress: DefenseProgressItem[];
};

/** Rows of a divided list sit edge to edge inside a card. */
const listContentClass = 'px-0';
const rowClass =
    'flex items-center gap-3 px-6 py-3.5 transition-colors max-md:min-h-14';
const linkRowClass = cn(
    rowClass,
    'hover:bg-accent/60 focus-visible:bg-accent/60 focus-visible:outline-none',
);
const iconChipClass =
    'flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary-muted text-primary-muted-foreground';

const riskTone = { high: 'danger', medium: 'warning', low: 'success' } as const;
const riskLabel = {
    high: 'Perlu tindakan',
    medium: 'Perlu dipantau',
    low: 'Terkendali',
} as const;

const actionIcons: Record<string, LucideIcon> = {
    'awaiting-finalization': ClipboardCheck,
    'mentorship-lapsed': FileClock,
    'without-supervisor': UserSearch,
    'schedule-sidang': Presentation,
    'schedule-sempro': CalendarClock,
    inactive: UserX,
    'mentorship-minimum': GraduationCap,
    'over-quota': Users,
};

export default function KaprodiDashboardPage() {
    const {
        programStudi,
        actionItems,
        upcomingAgenda,
        attentionStudents,
        stats,
        recentArchives,
        phaseDistribution,
        defenseProgress,
    } = usePage<SharedData & DashboardProps>().props;
    const terms = neutralAcademicTerminology;
    const isMobile = useIsMobile();
    const summaryTiles = [
        {
            title: 'Mahasiswa prodi aktif',
            value: String(stats.activeStudents),
            description: 'Mahasiswa terdaftar dengan status aktif',
            href: studentsIndex.url(),
            icon: Users,
            tone: 'success' as const,
        },
        {
            title: 'Tanpa pembimbing',
            value: String(stats.withoutSupervisors),
            description: `Sudah melewati ${terms.proposalPhase.toLowerCase()}`,
            href: studentsIndex.url({
                query: { activeFocus: 'tanpa_pembimbing' },
            }),
            icon: UserRoundX,
            tone: 'warning' as const,
        },
        {
            title: 'Ujian 7 hari ke depan',
            value: String(stats.upcomingExams),
            description: 'Terjadwal mulai hari ini',
            href: exams.url(),
            icon: Presentation,
            tone: 'primary' as const,
        },
        {
            title: 'Masa aktif bimbingan habis',
            value: String(stats.expiredMentorship),
            description: 'Menunggu perpanjangan masa aktif',
            href: studentsIndex.url({ query: { activeFocus: 'masa_habis' } }),
            icon: FileClock,
            tone: 'danger' as const,
        },
    ];
    const agendaDays: {
        label: string;
        isToday: boolean;
        events: AgendaItem[];
    }[] = [];
    for (const event of upcomingAgenda) {
        const previous = agendaDays.at(-1);
        if (previous?.label === event.dayLabel) {
            previous.events.push(event);
        } else {
            agendaDays.push({
                label: event.dayLabel,
                isToday: event.isToday,
                events: [event],
            });
        }
    }

    const summarySection = (
        <nav
            aria-label="Ringkasan prodi"
            className="grid grid-cols-2 overflow-hidden rounded-xl border bg-card lg:grid-cols-4"
        >
            <PageHeader level={2} title="Ringkasan prodi" className="sr-only" />
            {summaryTiles.map((tile, index) => (
                <Link
                    key={tile.title}
                    href={tile.href}
                    className={cn(
                        'group relative min-w-0 border-b transition-colors hover:bg-muted focus-visible:z-10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset lg:border-b-0',
                        index % 2 === 0 && 'border-r',
                        index === 1 && 'lg:border-r',
                        index > 1 && 'max-lg:border-b-0',
                    )}
                >
                    <StatCard
                        {...tile}
                        variant="snapshot"
                        className="rounded-none border-0 bg-transparent shadow-none"
                    />
                </Link>
            ))}
        </nav>
    );

    const actionSection = (
        <section
            aria-label="Perlu tindakan"
            className="grid min-w-0 content-start gap-4"
        >
            <PageHeader
                level={2}
                title="Perlu tindakan"
                description={
                    actionItems.length > 0
                        ? 'Urut dari yang paling mendesak. Setiap tombol membuka daftar yang sudah difilter.'
                        : undefined
                }
            />
            {actionItems.length > 0 ? (
                <ActionQueue label="Perlu tindakan" layout="bento">
                    {actionItems.map((item, index) => (
                        <ActionQueueItem
                            key={item.id}
                            variant={index === 0 ? 'featured' : 'card'}
                            icon={actionIcons[item.id] ?? AlertCircle}
                            tone={item.tone}
                            count={item.value}
                            title={item.label}
                            description={
                                index === 0 ? item.description : undefined
                            }
                            className={
                                index === 0 ? 'md:col-span-2' : undefined
                            }
                            meta={
                                index === 0 && item.names.length > 0 ? (
                                    <p className="text-xs leading-relaxed text-pretty text-muted-foreground">
                                        <span className="font-medium text-foreground">
                                            Terkait:{' '}
                                        </span>
                                        {item.names[0]}
                                    </p>
                                ) : undefined
                            }
                            action={
                                <Button
                                    asChild
                                    variant={index === 0 ? 'default' : 'ghost'}
                                    size="sm"
                                    className={cn(
                                        'min-h-11 gap-2',
                                        index > 0 &&
                                            'px-0 text-xs text-primary hover:bg-primary-muted hover:text-primary-muted-foreground',
                                    )}
                                >
                                    <Link href={item.href}>
                                        {item.actionLabel}
                                        <ArrowUpRight
                                            className="size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                </Button>
                            }
                        />
                    ))}
                </ActionQueue>
            ) : (
                <EmptyState
                    icon={CheckCircle2}
                    title="Semua beres"
                    description="Tidak ada yang perlu ditindaklanjuti saat ini."
                    className="flex min-h-64 flex-col justify-center border-solid border-primary/20 bg-primary-muted [&>p]:text-primary-muted-foreground"
                />
            )}
        </section>
    );

    const agendaSection = (
        <section
            aria-label="Hari ini & 7 hari ke depan"
            className="grid min-w-0 content-start gap-5 self-start xl:min-h-0 xl:grid-rows-[auto_minmax(0,1fr)] xl:self-stretch xl:[contain:size]"
        >
            <PageHeader
                level={2}
                title="Hari ini & 7 hari ke depan"
                description="Jadwal ujian dan tenggat revisi minggu ini, dalam WITA."
            />
            {agendaDays.length > 0 ? (
                <div
                    role="region"
                    aria-label="Daftar agenda mendatang"
                    tabIndex={0}
                    className="grid gap-6 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring xl:min-h-0 xl:overflow-y-auto xl:overscroll-y-contain xl:pr-2 xl:pl-1"
                >
                    {agendaDays.map((day) => (
                        <section
                            key={day.events[0].id}
                            aria-label={day.label}
                            className="grid gap-3"
                        >
                            <h3
                                className={cn(
                                    'text-sm font-semibold',
                                    day.isToday && 'text-primary',
                                )}
                            >
                                {day.label}
                            </h3>
                            <ul className="grid gap-2 border-l border-primary/25 pl-4">
                                {day.events.map((event) => (
                                    <li key={event.id} className="relative">
                                        <span
                                            aria-hidden="true"
                                            className="absolute top-5 -left-5 size-2 rounded-full border border-primary bg-card"
                                        />
                                        <div className="flex min-h-11 gap-3 px-2 py-3">
                                            <span className="w-12 shrink-0 text-lg leading-tight font-semibold text-primary tabular-nums">
                                                {event.time}
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <p className="text-xs font-medium text-foreground">
                                                    {event.badge}
                                                </p>
                                                <p className="mt-1 text-sm font-medium text-pretty">
                                                    {event.title}
                                                </p>
                                                <p className="mt-1 text-sm text-pretty text-muted-foreground">
                                                    {event.subtitle}
                                                </p>
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))}
                </div>
            ) : (
                <EmptyState
                    icon={CalendarClock}
                    title="Tidak ada agenda minggu ini"
                    description="Jadwal ujian akan tampil di sini."
                    className="rounded-none border-0 bg-transparent px-0"
                />
            )}
        </section>
    );

    return (
        <KaprodiLayout
            breadcrumbs={breadcrumbs}
            title="Dashboard Kaprodi"
            subtitle={`Monitoring program studi ${programStudi.name}`}
        >
            <Head title="Dashboard Kaprodi" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-6 lg:gap-8 lg:py-8">
                <PageHeader
                    title="Dashboard kaprodi"
                    description={`Yang perlu ditindaklanjuti di program studi ${programStudi.name}.`}
                    className="[&_h1]:text-3xl [&_h1]:leading-tight"
                    actions={
                        <Button variant="outline" asChild className="min-h-11">
                            <Link href={dosenDashboard.url()}>
                                <GraduationCap aria-hidden="true" />
                                Bimbingan Saya
                            </Link>
                        </Button>
                    }
                />

                {isMobile ? (
                    <>
                        {actionSection}
                        {summarySection}
                        {agendaSection}
                    </>
                ) : (
                    <>
                        {summarySection}
                        <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                            {actionSection}
                            {agendaSection}
                        </div>
                    </>
                )}

                <DataSection
                    title="Progres mahasiswa"
                    description="Mahasiswa yang paling perlu dipantau lebih dulu."
                >
                    {attentionStudents.students.length > 0 ? (
                        <div className="space-y-4">
                            <ul
                                aria-label="Mahasiswa yang perlu dipantau"
                                className="grid overflow-hidden rounded-xl border bg-card md:grid-cols-2"
                            >
                                {attentionStudents.students.map(
                                    (student, index) => (
                                        <li
                                            key={student.id}
                                            className={cn(
                                                'flex min-w-0 flex-col gap-3 border-b p-4 last:border-b-0 [&_a]:min-h-11',
                                                index % 2 === 0 &&
                                                    'md:border-r',
                                                index >=
                                                    attentionStudents.students
                                                        .length -
                                                        (attentionStudents
                                                            .students.length %
                                                            2 || 2) &&
                                                    'md:border-b-0',
                                            )}
                                        >
                                            <div className="flex items-start justify-between gap-2 text-sm">
                                                <StudentIdentity
                                                    name={student.name}
                                                    nim={student.nim}
                                                    avatar={student.avatar}
                                                    profileUrl={
                                                        student.profileUrl
                                                    }
                                                />
                                                <Badge
                                                    variant="outline"
                                                    className={cn(
                                                        'shrink-0 rounded-full border-transparent',
                                                        toneClassName(
                                                            riskTone[
                                                                student.level
                                                            ],
                                                        ),
                                                    )}
                                                >
                                                    {riskLabel[student.level]}
                                                </Badge>
                                            </div>
                                            <ul className="list-disc space-y-0.5 pl-5 text-sm text-pretty">
                                                {student.problems.map(
                                                    (problem) => (
                                                        <li key={problem}>
                                                            {problem}
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                            {student.progress ? (
                                                <StudentProgressPeek
                                                    progress={student.progress}
                                                    className="border-t pt-3"
                                                />
                                            ) : null}
                                        </li>
                                    ),
                                )}
                            </ul>
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <p className="text-sm text-pretty text-muted-foreground">
                                    {attentionStudents.total >
                                    attentionStudents.students.length
                                        ? `Menampilkan ${attentionStudents.students.length} dari ${attentionStudents.total} mahasiswa yang perlu dipantau.`
                                        : null}
                                </p>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    asChild
                                    className="min-h-11"
                                >
                                    <Link href={studentsIndex.url()}>
                                        <UsersRound aria-hidden="true" />
                                        Semua mahasiswa
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <EmptyState
                            icon={CheckCircle2}
                            title="Tidak ada mahasiswa bermasalah"
                            description={`Semua mahasiswa aktif berjalan sesuai rencana ${terms.finalWorkLower}.`}
                        />
                    )}
                </DataSection>

                <div className="grid items-start gap-6 xl:grid-cols-3">
                    <SectionCard
                        title="Fase mahasiswa"
                        description={`${stats.students} mahasiswa terdaftar di prodi.`}
                        contentClassName={listContentClass}
                    >
                        <ul className="divide-y border-y">
                            {phaseDistribution.map((item) => (
                                <li
                                    key={item.key}
                                    className={cn(rowClass, 'justify-between')}
                                >
                                    <span className="text-sm">
                                        {item.label}
                                    </span>
                                    <span className="text-sm font-semibold tabular-nums">
                                        {item.count}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </SectionCard>

                    <SectionCard
                        title={`${terms.proposalExamShort} & ${terms.finalExam}`}
                        description="Ringkasan status attempt ujian."
                        contentClassName={
                            defenseProgress.length > 0
                                ? listContentClass
                                : undefined
                        }
                    >
                        {defenseProgress.length > 0 ? (
                            <ul className="divide-y border-y">
                                {defenseProgress.map((item) => (
                                    <li
                                        key={`${item.type}-${item.status}`}
                                        className={cn(
                                            rowClass,
                                            'justify-between',
                                        )}
                                    >
                                        <span className="text-sm">
                                            {item.label}
                                        </span>
                                        <span className="text-sm font-semibold tabular-nums">
                                            {item.count}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <EmptyState
                                icon={ClipboardCheck}
                                title={`Belum ada ${terms.proposalExamShort.toLowerCase()} atau ${terms.finalExam.toLowerCase()}`}
                                description="Attempt ujian akan tampil saat data tersedia."
                                className="rounded-none border-0 bg-transparent"
                            />
                        )}
                    </SectionCard>

                    <SectionCard
                        title="Arsip terbaru"
                        description={`${stats.archives} ${terms.finalWorkLower} sudah selesai atau dibatalkan.`}
                        contentClassName={
                            recentArchives.length > 0
                                ? listContentClass
                                : undefined
                        }
                    >
                        {recentArchives.length > 0 ? (
                            <ul className="divide-y border-y">
                                {recentArchives.map((archive) => (
                                    <li key={archive.id}>
                                        <Link
                                            href={archive.profileUrl}
                                            className={cn(
                                                'group',
                                                linkRowClass,
                                            )}
                                        >
                                            <span className={iconChipClass}>
                                                <FileArchive
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="text-sm font-medium group-hover:text-primary">
                                                        {archive.student}
                                                    </p>
                                                    <StatusBadge
                                                        domain="state"
                                                        status={
                                                            archive.state ===
                                                            'Dibatalkan'
                                                                ? 'cancelled'
                                                                : 'completed'
                                                        }
                                                    >
                                                        {archive.state}
                                                    </StatusBadge>
                                                </div>
                                                <p className="mt-0.5 line-clamp-1 text-xs text-muted-foreground">
                                                    {archive.title}
                                                </p>
                                                <p className="mt-0.5 text-xs font-medium">
                                                    {archive.completedAt}
                                                </p>
                                            </div>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <EmptyState
                                icon={FileArchive}
                                title="Belum ada arsip"
                                description={`${terms.finalWork} yang telah selesai akan muncul di sini.`}
                                className="rounded-none border-0 bg-transparent"
                            />
                        )}
                    </SectionCard>
                </div>
            </div>
        </KaprodiLayout>
    );
}
