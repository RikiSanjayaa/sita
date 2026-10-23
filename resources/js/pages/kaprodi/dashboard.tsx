import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowUpRight,
    CalendarClock,
    ChevronRight,
    ClipboardCheck,
    FileArchive,
    FileStack,
    GraduationCap,
    UsersRound,
} from 'lucide-react';

import { EmptyState } from '@/components/patterns/empty-state';
import { SectionCard } from '@/components/patterns/section-card';
import { StatusBadge } from '@/components/patterns/status-badge';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import KaprodiLayout from '@/layouts/kaprodi-layout';
import { neutralAcademicTerminology } from '@/lib/academic-terminology';
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
    const terms = neutralAcademicTerminology;

    const canViewDocuments = auth.kaprodiCapabilities?.view_documents ?? true;
    const quickActions = [
        {
            title: 'Mahasiswa Prodi',
            description:
                'Cari mahasiswa, lihat fase, pembimbing, dan detail arsip.',
            href: '/kaprodi/mahasiswa',
            icon: UsersRound,
        },
        {
            title: `${terms.proposalExamShort} & ${terms.finalExam}`,
            description:
                'Pantau jadwal, status ujian, hasil, dan revisi terbuka.',
            href: '/kaprodi/sempro-sidang',
            icon: ClipboardCheck,
        },
        {
            title: 'Dokumen',
            description: 'Pantau upload mahasiswa dan status review dosen.',
            href: '/kaprodi/dokumen',
            icon: FileStack,
            visible: canViewDocuments,
        },
        {
            title: 'Dosen Prodi',
            description:
                'Lihat beban dosen sebagai pembimbing dan penguji prodi.',
            href: '/kaprodi/dosen-prodi',
            icon: GraduationCap,
        },
    ].filter((action) => action.visible ?? true);

    return (
        <KaprodiLayout
            breadcrumbs={breadcrumbs}
            title="Dashboard Kaprodi"
            subtitle={`Monitoring program studi ${programStudi.name}`}
        >
            <Head title="Dashboard Kaprodi" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-6 lg:py-8">
                <Card className="gap-0 overflow-hidden bg-gradient-to-br from-card via-card to-accent/40 py-0">
                    <CardContent className="grid p-0 lg:grid-cols-[1.3fr_0.7fr]">
                        <div className="flex flex-col justify-center gap-4 p-6 lg:p-8">
                            <p className="text-sm font-medium text-muted-foreground">
                                {programStudi.name}
                            </p>
                            <h1 className="max-w-2xl text-2xl font-bold tracking-tight text-balance text-foreground lg:text-3xl">
                                Selamat datang, {auth.user.name}
                            </h1>
                            <p className="max-w-lg text-sm leading-7 text-muted-foreground">
                                Portal kaprodi membantu memantau progres{' '}
                                {terms.finalWorkLower} seluruh mahasiswa prodi,
                                agenda {terms.proposalExamShort} dan{' '}
                                {terms.finalExamShort}, beban dosen, serta arsip{' '}
                                {terms.finalWorkLower} tanpa mengubah data
                                operasional.
                            </p>
                        </div>

                        <div className="flex flex-col justify-between gap-5 border-t bg-card p-6 lg:border-t-0 lg:border-l lg:p-8">
                            <div className="space-y-3">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Ringkasan prodi
                                    </p>
                                    <Badge variant="soft">
                                        {workSummary.label}
                                    </Badge>
                                </div>
                                <p className="text-3xl font-bold tracking-tight text-foreground">
                                    {workSummary.headline}
                                </p>
                                <p className="text-sm leading-6 text-muted-foreground">
                                    {workSummary.description}
                                </p>
                            </div>

                            <dl className="grid grid-cols-2 gap-2">
                                {workSummary.metrics.map((metric) => (
                                    <div
                                        key={metric.label}
                                        className="rounded-lg bg-muted px-3 py-2.5"
                                    >
                                        <dt className="text-xs font-medium text-muted-foreground">
                                            {metric.label}
                                        </dt>
                                        <dd className="mt-1 text-xl font-semibold text-foreground tabular-nums">
                                            {metric.value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>

                            <div className="border-t pt-4">
                                <Link
                                    href="/kaprodi/mahasiswa"
                                    className="group inline-flex items-center gap-1.5 rounded-sm text-sm font-semibold text-primary focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none max-md:min-h-11"
                                >
                                    Buka mahasiswa prodi
                                    <ArrowUpRight className="size-4 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                                </Link>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid items-start gap-6 xl:grid-cols-[2fr_1fr]">
                    <div className="grid content-start gap-6">
                        <SectionCard
                            title="Akses Monitoring"
                            description="Buka halaman khusus untuk membaca data prodi tanpa menumpuk semuanya di dashboard."
                            contentClassName={listContentClass}
                        >
                            <ul className="divide-y border-y">
                                {quickActions.map((action) => {
                                    const Icon = action.icon;

                                    return (
                                        <li key={action.title}>
                                            <Link
                                                href={action.href}
                                                className={cn(
                                                    linkRowClass,
                                                    'group gap-4 py-4',
                                                )}
                                            >
                                                <span
                                                    className={cn(
                                                        iconChipClass,
                                                        'size-10',
                                                    )}
                                                >
                                                    <Icon className="size-5" />
                                                </span>
                                                <div className="min-w-0 flex-1">
                                                    <p className="text-sm font-medium group-hover:text-primary">
                                                        {action.title}
                                                    </p>
                                                    <p className="mt-0.5 text-xs leading-relaxed text-pretty text-muted-foreground">
                                                        {action.description}
                                                    </p>
                                                </div>
                                                <ChevronRight className="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary" />
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        </SectionCard>

                        <SectionCard
                            title="Fase Mahasiswa"
                            description={`Snapshot cepat progres ${terms.finalWorkLower} di prodi.`}
                            contentClassName={listContentClass}
                        >
                            <ul className="divide-y border-y">
                                {phaseDistribution.map((item) => (
                                    <li
                                        key={item.key}
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
                        </SectionCard>
                    </div>

                    <div className="grid content-start gap-6">
                        <SectionCard
                            title="Perlu Perhatian"
                            description="Sinyal monitoring yang paling cepat perlu dibuka."
                            contentClassName={
                                attentionItems.length > 0
                                    ? listContentClass
                                    : undefined
                            }
                        >
                            {attentionItems.length > 0 ? (
                                <ul className="divide-y border-y">
                                    {attentionItems.map((item) => (
                                        <li key={item.id}>
                                            <Link
                                                href={item.href}
                                                className={cn(
                                                    linkRowClass,
                                                    'group items-start',
                                                )}
                                            >
                                                <span
                                                    className={cn(
                                                        iconChipClass,
                                                        'mt-0.5',
                                                    )}
                                                >
                                                    <AlertCircle className="size-4" />
                                                </span>
                                                <div className="min-w-0 flex-1">
                                                    <p className="text-sm font-medium group-hover:text-primary">
                                                        {item.label}
                                                    </p>
                                                    <p className="mt-0.5 text-xs leading-relaxed text-pretty text-muted-foreground">
                                                        {item.description}
                                                    </p>
                                                </div>
                                                <span className="text-lg font-semibold tabular-nums">
                                                    {item.value}
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <EmptyState
                                    icon={AlertCircle}
                                    title="Belum ada perhatian mendesak"
                                    description="Indikator penting akan muncul saat ada revisi, ujian, atau penugasan yang perlu dipantau."
                                />
                            )}
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
                                />
                            )}
                        </SectionCard>
                    </div>
                </div>

                <div className="grid items-start gap-6 xl:grid-cols-2">
                    <SectionCard
                        title="Agenda Terdekat"
                        description="Jadwal ujian dan revisi yang masih terbuka."
                        contentClassName={
                            upcomingAgenda.length > 0
                                ? listContentClass
                                : undefined
                        }
                    >
                        {upcomingAgenda.length > 0 ? (
                            <ul className="divide-y border-y">
                                {upcomingAgenda.map((agenda) => (
                                    <li key={agenda.id} className={rowClass}>
                                        <span className={iconChipClass}>
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
                                title="Belum ada agenda terdekat"
                                description="Jadwal ujian dan deadline revisi akan muncul di sini."
                            />
                        )}
                    </SectionCard>

                    <SectionCard
                        title="Arsip Terbaru"
                        description={`${terms.finalWork} terkini yang telah selesai.`}
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
                                                <FileArchive className="size-4" />
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
                                                <p className="mt-0.5 text-xs font-medium sm:hidden">
                                                    {archive.completedAt}
                                                </p>
                                            </div>
                                            <p className="hidden text-right text-xs font-medium sm:block">
                                                {archive.completedAt}
                                            </p>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <EmptyState
                                icon={FileArchive}
                                title="Belum ada arsip"
                                description={`${terms.finalWork} terkini yang telah selesai akan muncul di sini.`}
                                className="rounded-none border-0 bg-transparent"
                            />
                        )}
                    </SectionCard>
                </div>
            </div>
        </KaprodiLayout>
    );
}
