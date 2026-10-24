import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    CalendarCheck,
    CalendarClock,
    CircleCheckBig,
    FileCheck2,
    FileStack,
    Presentation,
    UserX,
    Users,
    type LucideIcon,
} from 'lucide-react';

import dashboard from '@/actions/App/Http/Controllers/Dosen/DashboardController';
import { index as documents } from '@/actions/App/Http/Controllers/Dosen/DokumenRevisiController';
import studentsIndex from '@/actions/App/Http/Controllers/Dosen/MahasiswaBimbinganController';
import { index as exams } from '@/actions/App/Http/Controllers/Dosen/SeminarProposalController';
import {
    ActionQueue,
    ActionQueueItem,
} from '@/components/patterns/action-queue';
import { EmptyState } from '@/components/patterns/empty-state';
import { PageHeader } from '@/components/patterns/page-header';
import { SectionCard } from '@/components/patterns/section-card';
import { StatCard } from '@/components/patterns/stat-card';
import { StatusBadge } from '@/components/patterns/status-badge';
import { PersonCardLink } from '@/components/profile/person-card-link';
import { Button } from '@/components/ui/button';
import { useIsMobile } from '@/hooks/use-mobile';
import DosenLayout from '@/layouts/dosen-layout';
import { type StatusTone } from '@/lib/status-styles';
import { cn } from '@/lib/utils';
import {
    type BreadcrumbItem,
    type SharedData,
    type UserProfileSummary,
} from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard.url() },
];

type ActionItem = {
    key: string;
    title: string;
    description: string;
    count: number;
    href: string;
    actionLabel: string;
    students: string[];
};

type AgendaDay = {
    label: string;
    isToday: boolean;
    events: Array<{
        id: string;
        kind: string;
        category: 'ujian' | 'bimbingan';
        title: string;
        subtitle: string;
        time: string;
        href: string;
    }>;
};

type DashboardProps = {
    summary: {
        lecturerName: string;
        programStudi: string | null;
        concentration: string | null;
        activeStudentCount: number;
        capacityLimit: number;
    };
    actionItems: ActionItem[];
    agenda: AgendaDay[];
    students: Array<UserProfileSummary & { attention: string[] }>;
};

const actionIcons: Record<string, LucideIcon> = {
    defenses_to_grade: Presentation,
    revisions_to_verify: FileCheck2,
    pending_schedules: CalendarClock,
    overdue_sessions: CalendarCheck,
    documents_to_review: FileStack,
    inactive_students: UserX,
};
const actionTones: Record<string, StatusTone> = {
    defenses_to_grade: 'danger',
    revisions_to_verify: 'warning',
    pending_schedules: 'warning',
    overdue_sessions: 'danger',
    documents_to_review: 'info',
    inactive_students: 'warning',
};

export default function DosenDashboardPage() {
    const { summary, actionItems, agenda, students } = usePage<
        SharedData & DashboardProps
    >().props;
    const isMobile = useIsMobile();
    const upcomingExamCount = agenda.reduce(
        (count, day) =>
            count +
            day.events.filter((event) => event.category === 'ujian').length,
        0,
    );
    const summaryTiles = [
        {
            title: 'Mahasiswa bimbingan aktif',
            value: `${summary.activeStudentCount} / ${summary.capacityLimit}`,
            description: 'Mahasiswa aktif / kuota bimbingan',
            href: studentsIndex.url(),
            icon: Users,
        },
        {
            title: 'Ujian 7 hari ke depan',
            value: String(upcomingExamCount),
            description: 'Terjadwal mulai hari ini',
            href: exams.url(),
            icon: Presentation,
        },
        {
            title: 'Revisi menunggu verifikasi',
            value: String(
                actionItems.find((item) => item.key === 'revisions_to_verify')
                    ?.count ?? 0,
            ),
            description: 'Perbaikan sudah dikirim mahasiswa',
            href: exams.url({ query: { status: 'revisi' } }),
            icon: FileCheck2,
        },
        {
            title: 'Dokumen menunggu review',
            value: String(
                actionItems.find((item) => item.key === 'documents_to_review')
                    ?.count ?? 0,
            ),
            description: 'Menunggu tanggapan Anda',
            href: documents.url({ query: { status: 'Perlu Review' } }),
            icon: FileStack,
        },
    ];

    const summarySection = (
        <nav
            aria-label="Ringkasan bimbingan"
            className="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4"
        >
            {summaryTiles.map((tile) => (
                <Link
                    key={tile.title}
                    href={tile.href}
                    className="group relative min-w-0 rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <StatCard
                        {...tile}
                        compact
                        className="transition-colors group-hover:border-primary/50 group-hover:bg-accent/30"
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
                            key={item.key}
                            variant="card"
                            icon={actionIcons[item.key]}
                            tone={actionTones[item.key]}
                            count={item.count}
                            title={item.title}
                            description={item.description}
                            className={cn(
                                index === 0 && 'md:col-span-2',
                                index === 0 &&
                                    actionTones[item.key] === 'danger' &&
                                    'border-danger/40',
                                index === 0 &&
                                    actionTones[item.key] === 'warning' &&
                                    'border-warning/60',
                                index === 0 &&
                                    actionTones[item.key] === 'info' &&
                                    'border-info/40',
                            )}
                            meta={
                                item.students.length > 0 ? (
                                    <p className="border-t pt-3 text-xs leading-relaxed text-pretty text-muted-foreground">
                                        <span className="font-medium text-foreground">
                                            Mahasiswa:{' '}
                                        </span>
                                        {item.students.join(', ')}
                                    </p>
                                ) : undefined
                            }
                            action={
                                <Button
                                    asChild
                                    variant={
                                        index === 0 ? 'default' : 'outline'
                                    }
                                    className="min-h-11 w-full justify-between gap-2"
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
                    icon={CircleCheckBig}
                    title="Semua beres"
                    description="Tidak ada penilaian, jadwal, dokumen, atau mahasiswa yang menunggu Anda."
                    className="min-h-64 bg-card"
                />
            )}
        </section>
    );

    const agendaSection = (
        <SectionCard
            title="Hari ini & 7 hari ke depan"
            description="Bimbingan dan ujian yang sudah terjadwal."
            className="min-w-0 self-start"
        >
            {agenda.length > 0 ? (
                <div className="grid gap-6">
                    {agenda.map((day) => (
                        <section
                            key={day.label}
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
                            <ul className="grid gap-1 border-l pl-4">
                                {day.events.map((event) => (
                                    <li key={event.id} className="relative">
                                        <span
                                            aria-hidden="true"
                                            className="absolute top-5 -left-5 size-2 rounded-full border border-primary bg-card"
                                        />
                                        <Link
                                            href={event.href}
                                            className="group flex min-h-11 gap-3 rounded-md px-2 py-3 transition-colors hover:bg-accent/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            <span className="w-10 shrink-0 text-sm font-medium tabular-nums">
                                                {event.time}
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="block text-xs font-medium text-muted-foreground">
                                                    {event.kind}
                                                </span>
                                                {event.title !== event.kind && (
                                                    <span className="mt-1 block text-sm font-medium text-pretty group-hover:underline">
                                                        {event.title}
                                                    </span>
                                                )}
                                                {event.subtitle && (
                                                    <span className="mt-1 block text-sm text-pretty text-muted-foreground">
                                                        {event.subtitle}
                                                    </span>
                                                )}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))}
                </div>
            ) : (
                <EmptyState
                    icon={CalendarClock}
                    title="Belum ada agenda"
                    description="Belum ada bimbingan atau ujian terjadwal dalam 7 hari ke depan."
                    className="rounded-none border-0 bg-transparent px-0"
                />
            )}
        </SectionCard>
    );

    return (
        <DosenLayout
            breadcrumbs={breadcrumbs}
            title="Dashboard Dosen"
            subtitle={`Selamat datang, ${summary.lecturerName}`}
        >
            <Head title="Dashboard Dosen" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-6 lg:gap-8 lg:py-8">
                <PageHeader
                    title="Dashboard dosen"
                    description={`Selamat datang, ${summary.lecturerName}${summary.programStudi ? ` · ${summary.programStudi}` : ''}${summary.concentration ? ` · ${summary.concentration}` : ''}`}
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

                <SectionCard
                    title="Progres mahasiswa"
                    description="Yang perlu perhatian tampil paling atas."
                    actions={
                        <Button asChild variant="outline" className="min-h-11">
                            <Link href={studentsIndex.url()}>Lihat semua</Link>
                        </Button>
                    }
                    contentClassName="px-0"
                >
                    {students.length > 0 ? (
                        <ul className="divide-y border-t">
                            {students.map((student) => (
                                <li key={student.id}>
                                    <PersonCardLink
                                        person={student}
                                        progress={student.progress}
                                        variant="row"
                                    />
                                    {student.attention.length > 0 ? (
                                        <ul
                                            aria-label={`Perlu perhatian: ${student.name}`}
                                            className="flex flex-wrap gap-1.5 px-4 pb-3"
                                        >
                                            {student.attention.map((reason) => (
                                                <li key={reason}>
                                                    <StatusBadge
                                                        domain="ticket"
                                                        status="open"
                                                    >
                                                        {reason}
                                                    </StatusBadge>
                                                </li>
                                            ))}
                                        </ul>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="border-t px-6 pt-4 text-sm text-muted-foreground">
                            Belum ada mahasiswa aktif. Mahasiswa tampil di sini
                            setelah penugasan pembimbing berjalan.
                        </p>
                    )}
                </SectionCard>
            </div>
        </DosenLayout>
    );
}
