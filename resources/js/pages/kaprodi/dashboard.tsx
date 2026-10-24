import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    CalendarClock,
    CheckCircle2,
    ClipboardCheck,
    FileArchive,
    GraduationCap,
    UsersRound,
} from 'lucide-react';

import {
    ActionQueue,
    ActionQueueItem,
} from '@/components/patterns/action-queue';
import { DataSection } from '@/components/patterns/data-section';
import { EmptyState } from '@/components/patterns/empty-state';
import { PageHeader } from '@/components/patterns/page-header';
import { SectionCard } from '@/components/patterns/section-card';
import { StatusBadge } from '@/components/patterns/status-badge';
import { StudentIdentity } from '@/components/patterns/student-cells';
import {
    StudentProgressPeek,
    type StudentProgress,
} from '@/components/profile/student-progress-peek';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import KaprodiLayout from '@/layouts/kaprodi-layout';
import { neutralAcademicTerminology } from '@/lib/academic-terminology';
import { toneClassName, type StatusTone } from '@/lib/status-styles';
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
    stats: { students: number; archives: number };
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

    return (
        <KaprodiLayout
            breadcrumbs={breadcrumbs}
            title="Dashboard Kaprodi"
            subtitle={`Monitoring program studi ${programStudi.name}`}
        >
            <Head title="Dashboard Kaprodi" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-6 lg:py-8">
                <PageHeader
                    title="Dashboard Kaprodi"
                    description={`Yang perlu ditindaklanjuti di program studi ${programStudi.name}.`}
                    actions={
                        <Button
                            variant="outline"
                            asChild
                            className="max-md:h-11"
                        >
                            <Link href="/dosen/dashboard">
                                <GraduationCap aria-hidden="true" />
                                Bimbingan Saya
                            </Link>
                        </Button>
                    }
                />

                <DataSection
                    title="Perlu Tindakan"
                    description="Urut dari yang paling mendesak. Setiap item membuka daftar yang sudah difilter."
                >
                    {actionItems.length > 0 ? (
                        <ActionQueue label="Perlu tindakan">
                            {actionItems.map((item) => {
                                const more = item.value - item.names.length;

                                return (
                                    <ActionQueueItem
                                        key={item.id}
                                        icon={AlertCircle}
                                        title={item.label}
                                        meta={
                                            <Badge
                                                variant="outline"
                                                className={cn(
                                                    'rounded-full border-transparent tabular-nums',
                                                    toneClassName(item.tone),
                                                )}
                                            >
                                                {item.value}
                                            </Badge>
                                        }
                                        description={
                                            <>
                                                {item.description}
                                                <span className="mt-0.5 block text-xs text-pretty">
                                                    {item.names.join(', ')}
                                                    {more > 0
                                                        ? `, +${more} lainnya`
                                                        : ''}
                                                </span>
                                            </>
                                        }
                                        action={
                                            <Button
                                                size="sm"
                                                asChild
                                                className="max-md:h-11 max-md:w-full"
                                            >
                                                <Link href={item.href}>
                                                    {item.actionLabel}
                                                </Link>
                                            </Button>
                                        }
                                    />
                                );
                            })}
                        </ActionQueue>
                    ) : (
                        <EmptyState
                            icon={CheckCircle2}
                            title="Semua beres"
                            description="Tidak ada yang perlu ditindaklanjuti saat ini."
                        />
                    )}
                </DataSection>

                <SectionCard
                    title="Hari Ini & 7 Hari ke Depan"
                    description="Jadwal ujian dan tenggat revisi minggu ini."
                    contentClassName={
                        upcomingAgenda.length > 0 ? listContentClass : undefined
                    }
                >
                    {upcomingAgenda.length > 0 ? (
                        <ul className="divide-y border-y">
                            {upcomingAgenda.map((agenda) => (
                                <li key={agenda.id} className={rowClass}>
                                    <span className={iconChipClass}>
                                        <CalendarClock
                                            className="size-4"
                                            aria-hidden="true"
                                        />
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
                                        <p className="mt-0.5 text-xs font-medium sm:hidden">
                                            {agenda.date}
                                        </p>
                                    </div>
                                    <p className="hidden text-right text-xs font-medium sm:block">
                                        {agenda.date}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <EmptyState
                            icon={CalendarClock}
                            title="Tidak ada agenda minggu ini"
                            description="Jadwal ujian dan tenggat revisi muncul di sini."
                            className="rounded-none border-0 bg-transparent"
                        />
                    )}
                </SectionCard>

                <DataSection
                    title="Progres Mahasiswa"
                    description="Mahasiswa yang paling perlu dipantau lebih dulu."
                >
                    {attentionStudents.students.length > 0 ? (
                        <div className="space-y-4">
                            <ul
                                aria-label="Mahasiswa yang perlu dipantau"
                                className="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                            >
                                {attentionStudents.students.map((student) => (
                                    <li
                                        key={student.id}
                                        className="flex flex-col gap-3 rounded-lg border bg-card p-4"
                                    >
                                        <div className="flex items-start justify-between gap-2 text-sm">
                                            <StudentIdentity
                                                name={student.name}
                                                nim={student.nim}
                                                avatar={student.avatar}
                                                profileUrl={student.profileUrl}
                                            />
                                            <Badge
                                                variant="outline"
                                                className={cn(
                                                    'shrink-0 rounded-full border-transparent',
                                                    toneClassName(
                                                        riskTone[student.level],
                                                    ),
                                                )}
                                            >
                                                {riskLabel[student.level]}
                                            </Badge>
                                        </div>
                                        <ul className="list-disc space-y-0.5 pl-5 text-sm text-pretty">
                                            {student.problems.map((problem) => (
                                                <li key={problem}>{problem}</li>
                                            ))}
                                        </ul>
                                        {student.progress ? (
                                            <StudentProgressPeek
                                                progress={student.progress}
                                                className="border-t pt-3"
                                            />
                                        ) : null}
                                    </li>
                                ))}
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
                                    className="max-md:h-11"
                                >
                                    <Link href="/kaprodi/mahasiswa">
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
                        title="Fase Mahasiswa"
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
                        title="Arsip Terbaru"
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
