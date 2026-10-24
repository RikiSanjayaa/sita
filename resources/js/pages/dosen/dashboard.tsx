import { Head, Link, usePage } from '@inertiajs/react';
import {
    CalendarCheck,
    CalendarClock,
    CircleCheckBig,
    FileCheck2,
    FileStack,
    Presentation,
    UserX,
    type LucideIcon,
} from 'lucide-react';

import {
    ActionQueue,
    ActionQueueItem,
} from '@/components/patterns/action-queue';
import { EmptyState } from '@/components/patterns/empty-state';
import { PageHeader } from '@/components/patterns/page-header';
import { SectionCard } from '@/components/patterns/section-card';
import { StatusBadge } from '@/components/patterns/status-badge';
import { PersonCardLink } from '@/components/profile/person-card-link';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import DosenLayout from '@/layouts/dosen-layout';
import {
    type BreadcrumbItem,
    type SharedData,
    type UserProfileSummary,
} from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dosen/dashboard' },
];

type ActionItem = {
    key: string;
    title: string;
    description: string;
    count: number;
    href: string;
    actionLabel: string;
};

type AgendaDay = {
    label: string;
    isToday: boolean;
    events: Array<{
        id: string;
        kind: string;
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

export default function DosenDashboardPage() {
    const { summary, actionItems, agenda, students } = usePage<
        SharedData & DashboardProps
    >().props;

    return (
        <DosenLayout
            breadcrumbs={breadcrumbs}
            title="Dashboard Dosen"
            subtitle={`Selamat datang, ${summary.lecturerName}`}
        >
            <Head title="Dashboard Dosen" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-6 lg:gap-8 lg:py-8">
                <section aria-label="Perlu tindakan" className="grid gap-4">
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
                        <ActionQueue label="Perlu tindakan">
                            {actionItems.map((item) => (
                                <ActionQueueItem
                                    key={item.key}
                                    icon={actionIcons[item.key]}
                                    title={item.title}
                                    meta={
                                        <Badge
                                            variant="secondary"
                                            className="tabular-nums"
                                        >
                                            {item.count}
                                        </Badge>
                                    }
                                    description={item.description}
                                    action={
                                        <Button
                                            asChild
                                            size="sm"
                                            className="max-md:h-11 max-md:w-full"
                                        >
                                            <Link href={item.href}>
                                                {item.actionLabel}
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
                        />
                    )}
                </section>

                <div className="grid grid-cols-1 gap-6 lg:gap-8">
                    <SectionCard
                        title="Hari ini & 7 hari ke depan"
                        description="Bimbingan dan ujian yang sudah terjadwal."
                    >
                        {agenda.length > 0 ? (
                            <div className="grid gap-5">
                                {agenda.map((day) => (
                                    <section
                                        key={day.label}
                                        aria-label={day.label}
                                        className="grid gap-2"
                                    >
                                        <h3 className="text-sm font-semibold">
                                            {day.label}
                                        </h3>
                                        <ul className="divide-y border-y">
                                            {day.events.map((event) => (
                                                <li key={event.id}>
                                                    <Link
                                                        href={event.href}
                                                        className="group flex items-center gap-4 px-4 py-3 transition-colors hover:bg-accent/50 max-md:min-h-11"
                                                    >
                                                        <span className="w-12 shrink-0 text-sm font-medium tabular-nums">
                                                            {event.time}
                                                        </span>
                                                        <span className="min-w-0 flex-1">
                                                            <span className="block text-sm font-medium group-hover:underline">
                                                                {event.title}
                                                            </span>
                                                            <span className="mt-0.5 block text-xs text-muted-foreground">
                                                                {event.kind}
                                                                {event.subtitle
                                                                    ? ` · ${event.subtitle}`
                                                                    : ''}
                                                            </span>
                                                        </span>
                                                    </Link>
                                                </li>
                                            ))}
                                        </ul>
                                    </section>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Belum ada bimbingan atau ujian terjadwal dalam 7
                                hari ke depan.
                            </p>
                        )}
                    </SectionCard>

                    <SectionCard
                        title="Progres mahasiswa"
                        description="Yang perlu perhatian tampil paling atas."
                        actions={
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                className="max-md:h-11"
                            >
                                <Link href="/dosen/mahasiswa-bimbingan">
                                    Lihat semua
                                </Link>
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
                                                {student.attention.map(
                                                    (reason) => (
                                                        <li key={reason}>
                                                            <StatusBadge
                                                                domain="ticket"
                                                                status="open"
                                                            >
                                                                {reason}
                                                            </StatusBadge>
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="border-t px-6 pt-4 text-sm text-muted-foreground">
                                Belum ada mahasiswa aktif. Mahasiswa tampil di
                                sini setelah penugasan pembimbing berjalan.
                            </p>
                        )}
                    </SectionCard>
                </div>

                <SectionCard
                    title="Kuota bimbingan"
                    description={[summary.programStudi, summary.concentration]
                        .filter(Boolean)
                        .join(' · ')}
                >
                    <p className="text-sm tabular-nums">
                        <span className="font-semibold">
                            {summary.activeStudentCount}
                        </span>{' '}
                        dari {summary.capacityLimit} mahasiswa aktif dalam
                        bimbingan Anda.
                    </p>
                </SectionCard>
            </div>
        </DosenLayout>
    );
}
