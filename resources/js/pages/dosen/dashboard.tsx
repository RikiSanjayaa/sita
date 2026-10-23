import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    CalendarClock,
    ChevronRight,
    FileStack,
    MessageSquareText,
    Presentation,
    Users,
} from 'lucide-react';

import { EmptyState } from '@/components/patterns/empty-state';
import { SectionCard } from '@/components/patterns/section-card';
import { PersonCardLink } from '@/components/profile/person-card-link';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import DosenLayout from '@/layouts/dosen-layout';
import { neutralAcademicTerminology as n } from '@/lib/academic-terminology';
import { cn } from '@/lib/utils';
import {
    type BreadcrumbItem,
    type SharedData,
    type UserProfileSummary,
} from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dosen/dashboard' },
];

type DashboardProps = {
    summary: {
        lecturerName: string;
        programStudi: string | null;
        concentration: string | null;
        quotaLabel: string;
        status: {
            label: string;
            description: string;
        };
        metrics: Array<{
            label: string;
            value: string;
        }>;
    };
    upcomingActivities: Array<{
        id: string;
        badge: string;
        title: string;
        subtitle: string;
        date: string;
        href: string;
    }>;
    activeStudents: UserProfileSummary[];
};

export default function DosenDashboardPage() {
    const {
        summary,
        upcomingActivities,
        activeStudents,
        academicTerminology: terms,
    } = usePage<SharedData & DashboardProps>().props;

    const quickActions = [
        {
            title: 'Mahasiswa Dosen',
            description:
                'Lihat mahasiswa bimbingan dan mahasiswa yang Anda uji.',
            href: '/dosen/mahasiswa-bimbingan',
            icon: Users,
            primary: true,
        },
        {
            title: 'Jadwal Bimbingan',
            description:
                'Kelola permintaan jadwal dan pantau kalender bimbingan.',
            href: '/dosen/jadwal-bimbingan',
            icon: CalendarClock,
            primary: false,
        },
        {
            title: `${n.proposalExamShort} & ${n.finalExam}`,
            description: `Pantau agenda ${n.proposalExamShort} dan ${n.finalExam} yang perlu Anda nilai.`,
            href: '/dosen/seminar-proposal',
            icon: Presentation,
            primary: false,
        },
        {
            title: 'Dokumen Revisi',
            description:
                'Review dokumen mahasiswa yang menunggu tindak lanjut.',
            href: '/dosen/dokumen-revisi',
            icon: FileStack,
            primary: false,
        },
        {
            title: 'Pesan',
            description: 'Percakapan grup dan pribadi.',
            href: '/dosen/pesan',
            icon: MessageSquareText,
            primary: false,
        },
    ];

    return (
        <DosenLayout
            breadcrumbs={breadcrumbs}
            title="Dashboard Dosen"
            subtitle="Ringkasan bimbingan, ujian, dan aktivitas mahasiswa"
        >
            <Head title="Dashboard Dosen" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-6 lg:gap-8 lg:py-8">
                <Card className="overflow-hidden py-0">
                    <CardContent className="bg-gradient-to-br from-background via-background to-accent/12 p-0">
                        <div className="grid lg:grid-cols-[1.3fr_0.7fr]">
                            <div className="flex flex-col justify-center gap-5 p-6 lg:p-8">
                                {summary.programStudi ||
                                summary.concentration ? (
                                    <div className="flex flex-wrap items-center gap-2">
                                        {summary.programStudi ? (
                                            <Badge>
                                                {summary.programStudi}
                                            </Badge>
                                        ) : null}
                                        {summary.concentration ? (
                                            <Badge variant="outline">
                                                {summary.concentration}
                                            </Badge>
                                        ) : null}
                                    </div>
                                ) : null}

                                <div className="space-y-2">
                                    <h1 className="max-w-xl text-2xl font-semibold tracking-tight text-balance lg:text-3xl">
                                        Selamat datang, {summary.lecturerName}
                                    </h1>
                                    <p className="max-w-xl text-sm leading-6 text-pretty text-muted-foreground">
                                        Progres mahasiswa, jadwal bimbingan,
                                        dokumen, dan penilaian{' '}
                                        {terms.proposalExamShort} dan{' '}
                                        {terms.finalExam} Anda ada di satu
                                        tempat.
                                    </p>
                                </div>
                            </div>

                            <div className="flex flex-col justify-between gap-5 border-t bg-card p-6 lg:border-t-0 lg:border-l lg:p-8">
                                <div className="space-y-3">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="text-xs font-medium text-muted-foreground">
                                                Kuota bimbingan
                                            </p>
                                            <p className="mt-1 text-3xl font-bold tracking-tight tabular-nums">
                                                {summary.quotaLabel}
                                            </p>
                                        </div>
                                        <Badge
                                            variant="outline"
                                            className="shrink-0"
                                        >
                                            {summary.status.label}
                                        </Badge>
                                    </div>
                                    <p className="text-sm text-pretty text-muted-foreground">
                                        {summary.status.description}
                                    </p>
                                </div>

                                {summary.metrics.length > 0 ? (
                                    <dl className="grid grid-cols-2 gap-3">
                                        {summary.metrics.map((metric) => (
                                            <div
                                                key={metric.label}
                                                className="rounded-lg bg-muted px-3 py-2.5"
                                            >
                                                <dt className="text-xs font-medium text-muted-foreground">
                                                    {metric.label}
                                                </dt>
                                                <dd className="mt-1 text-xl font-semibold tabular-nums">
                                                    {metric.value}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                ) : null}

                                <Link
                                    href="/dosen/jadwal-bimbingan"
                                    className="group inline-flex items-center gap-1.5 self-start text-sm font-medium text-primary hover:underline max-md:min-h-11"
                                >
                                    Buka jadwal bimbingan
                                    <ArrowUpRight className="size-4 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                                </Link>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                    <div className="grid min-w-0 content-start gap-6">
                        <SectionCard
                            title="Aksi cepat"
                            description="Halaman kerja utama untuk mahasiswa, jadwal, ujian, dan dokumen."
                        >
                            <div className="grid grid-cols-1 gap-2 2xl:grid-cols-2">
                                {quickActions.map((action) => {
                                    const Icon = action.icon;

                                    return (
                                        <Link
                                            key={action.title}
                                            href={action.href}
                                            className={cn(
                                                'group flex items-center gap-4 rounded-lg px-4 py-3.5 transition-colors',
                                                action.primary
                                                    ? 'bg-primary text-primary-foreground hover:bg-primary/90'
                                                    : 'bg-muted hover:bg-accent',
                                            )}
                                        >
                                            <span
                                                className={cn(
                                                    'flex size-10 shrink-0 items-center justify-center rounded-lg',
                                                    action.primary
                                                        ? 'bg-primary-foreground text-primary'
                                                        : 'bg-primary-muted text-primary-muted-foreground',
                                                )}
                                            >
                                                <Icon className="size-5" />
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="block text-sm font-semibold">
                                                    {action.title}
                                                </span>
                                                <span
                                                    className={cn(
                                                        'mt-0.5 line-clamp-2 block text-xs leading-relaxed',
                                                        action.primary
                                                            ? 'text-primary-foreground'
                                                            : 'text-muted-foreground',
                                                    )}
                                                >
                                                    {action.description}
                                                </span>
                                            </span>
                                            <ChevronRight
                                                aria-hidden="true"
                                                className="size-4 shrink-0 transition-transform group-hover:translate-x-0.5"
                                            />
                                        </Link>
                                    );
                                })}
                            </div>
                        </SectionCard>

                        <SectionCard
                            title="Agenda mendatang"
                            description="Bimbingan dan ujian terdekat."
                            contentClassName="px-0"
                        >
                            {upcomingActivities.length > 0 ? (
                                <ul className="divide-y border-t">
                                    {upcomingActivities.map((activity) => (
                                        <li key={activity.id}>
                                            <Link
                                                href={activity.href}
                                                className="group flex items-center gap-4 px-6 py-3.5 transition-colors hover:bg-accent/50"
                                            >
                                                <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary-muted text-primary-muted-foreground">
                                                    <CalendarClock className="size-4" />
                                                </span>
                                                <span className="min-w-0 flex-1">
                                                    <span className="block text-sm font-medium group-hover:text-primary">
                                                        {activity.title}
                                                    </span>
                                                    <span className="mt-0.5 block text-xs text-muted-foreground">
                                                        {activity.badge} ·{' '}
                                                        {activity.subtitle}
                                                    </span>
                                                    <span className="mt-0.5 block text-xs font-medium tabular-nums sm:hidden">
                                                        {activity.date}
                                                    </span>
                                                </span>
                                                <span className="hidden shrink-0 items-center gap-3 sm:flex">
                                                    <span className="text-xs font-medium tabular-nums">
                                                        {activity.date}
                                                    </span>
                                                    <ChevronRight
                                                        aria-hidden="true"
                                                        className="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                                                    />
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <div className="px-6">
                                    <EmptyState
                                        icon={CalendarClock}
                                        title="Belum ada agenda mendatang"
                                        description="Saat ada bimbingan atau jadwal ujian baru, semuanya akan muncul di sini."
                                    />
                                </div>
                            )}
                        </SectionCard>
                    </div>

                    <SectionCard
                        title="Mahasiswa aktif"
                        description="Mahasiswa yang sedang Anda bimbing."
                    >
                        {activeStudents.length > 0 ? (
                            <div className="grid gap-3">
                                {activeStudents.map((student) => (
                                    <PersonCardLink
                                        key={student.id}
                                        person={student}
                                    />
                                ))}
                            </div>
                        ) : (
                            <EmptyState
                                icon={Users}
                                title="Belum ada mahasiswa aktif"
                                description="Mahasiswa aktif akan tampil di sini setelah penugasan pembimbing berjalan."
                            />
                        )}
                    </SectionCard>
                </div>
            </div>
        </DosenLayout>
    );
}
