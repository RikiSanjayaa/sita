import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    CheckCircle2,
    ChevronRight,
    Clock,
    FileWarning,
    Inbox,
    MapPin,
    Pencil,
    Plus,
    Search,
    Star,
    User,
    XCircle,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import {
    BimbinganCalendar,
    type BimbinganEvent,
} from '@/components/bimbingan-calendar';
import {
    LecturerMultiSearch,
    LecturerSearchSelect,
} from '@/components/lecturer-search-select';
import { ScheduleDetailModal } from '@/components/schedule-detail-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DataTableContainer,
    DataTablePagination,
    usePagination,
} from '@/components/ui/data-table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { useUrlState } from '@/hooks/use-url-state';
import KaprodiLayout from '@/layouts/kaprodi-layout';
import {
    type AcademicGrade,
    academicGradeClassName,
} from '@/lib/academic-grade';
import { type AcademicTerminology } from '@/lib/academic-terminology';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';

type ProgramStudi = {
    name: string;
};

type ExaminerRow = {
    id: number;
    name: string;
    role: string;
    decision: string;
    score: string | null;
    profileUrl: string;
};

type ExamRow = {
    id: number;
    projectId: number;
    type: string;
    typeKey: string;
    status: string;
    statusKey: string;
    result: string;
    averageScore: number | null;
    grade: string | null;
    student: string;
    terminology: AcademicTerminology;
    nim: string | null;
    title: string;
    attempt: number;
    scheduledFor: string;
    scheduledForInput: string;
    location: string | null;
    mode: string;
    canManageSchedule: boolean;
    examiners: ExaminerRow[];
    revisionCount: number;
    studentProfileUrl: string;
};

type SchedulableProject = {
    id: number;
    student: string;
    nim: string | null;
    title: string;
    terminology: AcademicTerminology;
    phase: string;
    supervisors: {
        id: number;
        name: string;
        role: string;
    }[];
    latestSempro: DefenseSummary | null;
    latestSidang: DefenseSummary | null;
};

type DefenseSummary = {
    id: number;
    status: string;
    statusKey: string;
    scheduledFor: string;
    scheduledForInput: string;
    location: string | null;
    mode: string;
    canManageSchedule: boolean;
    examiners: {
        id: number;
        name: string;
        role: string;
    }[];
};

type SemproSidangProps = {
    programStudi: ProgramStudi;
    exams: ExamRow[];
    schedulableProjects: SchedulableProject[];
    calendarEvents: BimbinganEvent[];
};

type TypeFilter = 'semua' | 'sempro' | 'sidang';
type StatusFilter =
    | 'semua'
    | 'scheduled'
    | 'awaiting_finalization'
    | 'completed';
type ScheduleDialogState = {
    type: 'sempro' | 'sidang';
    project?: SchedulableProject;
    exam?: ExamRow;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/kaprodi/dashboard' },
    { title: 'Proposal & Ujian Akhir', href: '/kaprodi/sempro-sidang' },
];

const PAGE_SIZE = 15;

const statusColor: Record<string, string> = {
    scheduled: 'bg-primary/10 text-primary',
    awaiting_finalization: 'bg-amber-600/10 text-amber-700',
    completed: 'bg-emerald-600/10 text-emerald-600',
    cancelled: 'bg-destructive/10 text-destructive',
};

function DecisionBadge({ decision }: { decision: string }) {
    if (decision === '-' || decision === 'Menunggu') {
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-amber-600/10 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400">
                <Clock className="size-3" />
                Belum Diputus
            </span>
        );
    }

    if (decision === 'Lulus') {
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-600/10 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-400">
                <CheckCircle2 className="size-3" />
                Lulus
            </span>
        );
    }

    if (decision === 'Lulus dengan Revisi') {
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-amber-600/10 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400">
                <FileWarning className="size-3" />
                Revisi
            </span>
        );
    }

    return (
        <span className="inline-flex items-center gap-1 rounded-full bg-destructive/10 px-2 py-0.5 text-xs font-medium text-destructive">
            <XCircle className="size-3" />
            {decision}
        </span>
    );
}

export default function KaprodiSemproSidangPage() {
    const { auth, programStudi, exams, schedulableProjects, calendarEvents } =
        usePage<SharedData & SemproSidangProps>().props;
    const canScheduleSempro = auth.kaprodiCapabilities?.schedule_sempro ?? true;
    const canScheduleSidang = auth.kaprodiCapabilities?.schedule_sidang ?? true;
    const [search, setSearch] = useUrlState('search', '');
    const [typeFilter, setTypeFilter] = useUrlState<TypeFilter>(
        'type',
        'semua',
    );
    const [statusFilter, setStatusFilter] = useUrlState<StatusFilter>(
        'status',
        'semua',
    );
    const pageState = useUrlState('page', 1);
    const [selected, setSelected] = useState<ExamRow | null>(null);
    const [selectedEvent, setSelectedEvent] = useState<BimbinganEvent | null>(
        null,
    );
    const [scheduleDialog, setScheduleDialog] =
        useState<ScheduleDialogState | null>(null);

    const filtered = useMemo(() => {
        const query = search.trim().toLowerCase();

        return exams.filter((exam) => {
            const matchesSearch =
                !query ||
                [
                    exam.student,
                    exam.nim ?? '',
                    exam.title,
                    exam.type,
                    exam.status,
                    exam.result,
                    exam.grade ?? '',
                    ...exam.examiners.map((examiner) => examiner.name),
                ]
                    .join(' ')
                    .toLowerCase()
                    .includes(query);

            return (
                matchesSearch &&
                (typeFilter === 'semua' || exam.typeKey === typeFilter) &&
                (statusFilter === 'semua' || exam.statusKey === statusFilter)
            );
        });
    }, [exams, search, statusFilter, typeFilter]);

    const {
        page,
        setPage,
        pageSize,
        setPageSize,
        totalPages,
        paginated,
        totalItems,
    } = usePagination(
        filtered,
        PAGE_SIZE,
        [search, typeFilter, statusFilter],
        pageState,
    );

    const typeTabs: { label: string; value: TypeFilter }[] = [
        { label: 'Semua', value: 'semua' },
        { label: 'Proposal', value: 'sempro' },
        { label: 'Ujian Akhir', value: 'sidang' },
    ];

    const statusTabs: { label: string; value: StatusFilter; count?: number }[] =
        [
            { label: 'Semua Status', value: 'semua' },
            {
                label: 'Terjadwal',
                value: 'scheduled',
                count: exams.filter((exam) => exam.statusKey === 'scheduled')
                    .length,
            },
            {
                label: 'Finalisasi',
                value: 'awaiting_finalization',
                count: exams.filter(
                    (exam) => exam.statusKey === 'awaiting_finalization',
                ).length,
            },
            {
                label: 'Selesai',
                value: 'completed',
                count: exams.filter((exam) => exam.statusKey === 'completed')
                    .length,
            },
        ];

    return (
        <KaprodiLayout
            breadcrumbs={breadcrumbs}
            title="Proposal & Ujian Akhir"
            subtitle={`Monitoring ujian dan jadwal untuk ${programStudi.name}`}
        >
            <Head title="Proposal & Ujian Akhir" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-8 px-4 py-6 md:px-6 lg:py-8">
                <section>
                    <div className="mb-4 border-b pb-3">
                        <h2 className="text-base font-semibold">
                            Kalender Ujian Prodi
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Kalender ujian proposal dan ujian akhir dari seluruh
                            mahasiswa prodi.
                        </p>
                    </div>
                    <BimbinganCalendar
                        events={calendarEvents}
                        onEventClick={setSelectedEvent}
                    />
                </section>

                <section>
                    <div className="mb-4 border-b pb-3">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h2 className="text-base font-semibold">
                                    Monitoring Ujian
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Kaprodi dapat mengatur jadwal dan dosen
                                    terlibat tanpa melakukan penilaian.
                                </p>
                            </div>
                            {canScheduleSempro || canScheduleSidang ? (
                                <div className="flex flex-wrap gap-2">
                                    {canScheduleSempro ? (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                setScheduleDialog({
                                                    type: 'sempro',
                                                })
                                            }
                                        >
                                            <Plus className="size-4" />
                                            Jadwalkan Proposal
                                        </Button>
                                    ) : null}
                                    {canScheduleSidang ? (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                setScheduleDialog({
                                                    type: 'sidang',
                                                })
                                            }
                                        >
                                            <Plus className="size-4" />
                                            Jadwalkan Ujian Akhir
                                        </Button>
                                    ) : null}
                                </div>
                            ) : null}
                        </div>
                    </div>

                    <div className="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="relative w-full max-w-xs">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari nama, NIM, judul, atau dosen..."
                                className="h-8 pl-8 text-sm"
                            />
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <div className="flex gap-1">
                                {typeTabs.map((tab) => (
                                    <button
                                        key={tab.value}
                                        type="button"
                                        onClick={() => setTypeFilter(tab.value)}
                                        className={cn(
                                            'rounded-full px-3 py-1 text-xs font-medium transition-colors',
                                            typeFilter === tab.value
                                                ? 'bg-primary text-primary-foreground shadow-sm'
                                                : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground',
                                        )}
                                    >
                                        {tab.label}
                                    </button>
                                ))}
                            </div>
                            <div className="flex gap-1">
                                {statusTabs.map((tab) => (
                                    <button
                                        key={tab.value}
                                        type="button"
                                        onClick={() =>
                                            setStatusFilter(tab.value)
                                        }
                                        className={cn(
                                            'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium transition-colors',
                                            statusFilter === tab.value
                                                ? 'bg-primary text-primary-foreground shadow-sm'
                                                : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground',
                                        )}
                                    >
                                        {tab.label}
                                        {tab.count ? (
                                            <span
                                                className={cn(
                                                    'rounded-full px-1.5 py-0.5 text-[10px] leading-none font-bold',
                                                    statusFilter === tab.value
                                                        ? 'bg-white/20 text-white'
                                                        : 'bg-amber-600/15 text-amber-700',
                                                )}
                                            >
                                                {tab.count}
                                            </span>
                                        ) : null}
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>

                    {filtered.length === 0 ? (
                        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-center">
                            <Inbox className="mb-3 size-10 text-muted-foreground/40" />
                            <p className="text-sm font-semibold">
                                Tidak ada ujian yang cocok
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Coba ubah filter atau kata kunci pencarian.
                            </p>
                        </div>
                    ) : (
                        <DataTableContainer
                            pagination={
                                <DataTablePagination
                                    currentPage={page}
                                    totalPages={totalPages}
                                    totalItems={totalItems}
                                    pageSize={pageSize}
                                    onPageChange={setPage}
                                    onPageSizeChange={setPageSize}
                                    itemLabel="ujian"
                                />
                            }
                        >
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b bg-muted/30">
                                        <th className="px-4 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                            Mahasiswa
                                        </th>
                                        <th className="hidden px-4 py-2.5 text-left text-xs font-medium text-muted-foreground md:table-cell">
                                            Judul
                                        </th>
                                        <th className="px-4 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                            Tipe
                                        </th>
                                        <th className="hidden px-4 py-2.5 text-left text-xs font-medium text-muted-foreground lg:table-cell">
                                            Dosen Terlibat
                                        </th>
                                        <th className="hidden px-4 py-2.5 text-left text-xs font-medium text-muted-foreground xl:table-cell">
                                            Jadwal
                                        </th>
                                        <th className="px-4 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                            Nilai
                                        </th>
                                        <th className="hidden px-4 py-2.5 text-left text-xs font-medium text-muted-foreground sm:table-cell">
                                            Status
                                        </th>
                                        <th className="w-24 px-4 py-2.5" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {paginated.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="cursor-pointer transition-colors hover:bg-muted/30"
                                            onClick={() => setSelected(item)}
                                        >
                                            <td className="px-4 py-3">
                                                <p className="leading-snug font-medium">
                                                    {item.student}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {item.nim ?? '-'}
                                                </p>
                                            </td>
                                            <td className="hidden max-w-[260px] px-4 py-3 md:table-cell">
                                                <p className="line-clamp-2 text-xs leading-relaxed">
                                                    {item.title}
                                                </p>
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant="outline"
                                                    className={cn(
                                                        'rounded-full text-xs',
                                                        item.typeKey ===
                                                            'sempro'
                                                            ? 'border-cyan-500/50 bg-cyan-500/10 text-cyan-700 dark:text-cyan-400'
                                                            : 'border-purple-500/50 bg-purple-500/10 text-purple-700 dark:text-purple-400',
                                                    )}
                                                >
                                                    {item.type} #{item.attempt}
                                                </Badge>
                                            </td>
                                            <td className="hidden px-4 py-3 lg:table-cell">
                                                <div className="flex max-w-sm flex-wrap gap-1.5">
                                                    {item.examiners.map(
                                                        (examiner) => (
                                                            <Link
                                                                key={`${item.id}-${examiner.id}-${examiner.role}`}
                                                                href={
                                                                    examiner.profileUrl
                                                                }
                                                                onClick={(
                                                                    event,
                                                                ) =>
                                                                    event.stopPropagation()
                                                                }
                                                            >
                                                                <Badge variant="outline">
                                                                    {
                                                                        examiner.role
                                                                    }
                                                                    :{' '}
                                                                    {
                                                                        examiner.name
                                                                    }
                                                                </Badge>
                                                            </Link>
                                                        ),
                                                    )}
                                                </div>
                                            </td>
                                            <td className="hidden px-4 py-3 xl:table-cell">
                                                <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                    <Clock className="size-3 shrink-0" />
                                                    {item.scheduledFor}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    <span className="text-xs font-semibold">
                                                        {item.averageScore ??
                                                            '-'}
                                                    </span>
                                                    <Badge
                                                        className={cn(
                                                            'rounded-full text-xs',
                                                            academicGradeClassName(
                                                                item.grade as AcademicGrade | null,
                                                            ),
                                                        )}
                                                    >
                                                        {item.grade ?? '-'}
                                                    </Badge>
                                                </div>
                                            </td>
                                            <td className="hidden px-4 py-3 sm:table-cell">
                                                <span
                                                    className={cn(
                                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                                                        statusColor[
                                                            item.statusKey
                                                        ] ??
                                                            'bg-muted text-muted-foreground',
                                                    )}
                                                >
                                                    {item.status}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                <div className="flex items-center justify-end gap-2">
                                                    {item.canManageSchedule &&
                                                    ((item.typeKey ===
                                                        'sempro' &&
                                                        canScheduleSempro) ||
                                                        (item.typeKey ===
                                                            'sidang' &&
                                                            canScheduleSidang)) ? (
                                                        <button
                                                            type="button"
                                                            title="Ubah jadwal"
                                                            onClick={(
                                                                event,
                                                            ) => {
                                                                event.stopPropagation();
                                                                setScheduleDialog(
                                                                    {
                                                                        type: item.typeKey as
                                                                            | 'sempro'
                                                                            | 'sidang',
                                                                        exam: item,
                                                                    },
                                                                );
                                                            }}
                                                            className="rounded-md p-1.5 transition-colors hover:bg-muted hover:text-foreground"
                                                        >
                                                            <Pencil className="size-4" />
                                                        </button>
                                                    ) : null}
                                                    <ChevronRight className="size-4" />
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </DataTableContainer>
                    )}
                </section>
            </div>

            <ExamDetailSheet
                item={selected}
                open={selected !== null}
                onOpenChange={(open) => {
                    if (!open) setSelected(null);
                }}
            />
            <ScheduleDetailModal
                open={selectedEvent !== null}
                onOpenChange={(open) => {
                    if (!open) setSelectedEvent(null);
                }}
                schedule={selectedEvent}
                currentUserRole="dosen"
            />
            <ScheduleDialog
                state={scheduleDialog}
                projects={schedulableProjects}
                open={scheduleDialog !== null}
                onOpenChange={(open) => {
                    if (!open) setScheduleDialog(null);
                }}
            />
        </KaprodiLayout>
    );
}

function ExamDetailSheet({
    item,
    open,
    onOpenChange,
}: {
    item: ExamRow | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    if (!item) return null;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="w-full gap-0 p-0 sm:max-w-xl">
                <SheetHeader className="border-b bg-muted/20 px-6 py-4">
                    <div className="flex items-center gap-2 pr-6">
                        <Badge variant="outline">
                            {item.type} #{item.attempt}
                        </Badge>
                        <span
                            className={cn(
                                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                statusColor[item.statusKey] ??
                                    'bg-muted text-muted-foreground',
                            )}
                        >
                            {item.status}
                        </span>
                    </div>
                    <SheetTitle className="mt-1 text-base leading-snug">
                        {item.title}
                    </SheetTitle>
                    <SheetDescription>
                        Detail monitoring read-only untuk kaprodi.
                    </SheetDescription>
                </SheetHeader>

                <ScrollArea className="h-[calc(100vh-8rem)]">
                    <div className="space-y-5 px-6 py-5">
                        <div className="grid gap-2 rounded-xl border bg-muted/20 p-4 text-sm">
                            <Link
                                href={item.studentProfileUrl}
                                className="flex items-center gap-2 text-muted-foreground hover:text-primary"
                            >
                                <User className="size-3.5 shrink-0" />
                                <span className="font-medium text-foreground">
                                    {item.student}
                                </span>
                                <span className="text-xs">
                                    {item.nim ?? '-'}
                                </span>
                            </Link>
                            <div className="flex items-center gap-2 text-muted-foreground">
                                <CalendarClock className="size-3.5 shrink-0" />
                                <span>{item.scheduledFor}</span>
                            </div>
                            <div className="flex items-center gap-2 text-muted-foreground">
                                <MapPin className="size-3.5 shrink-0" />
                                <span>{item.location ?? '-'}</span>
                            </div>
                        </div>

                        <div>
                            <p className="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Keputusan Dosen
                            </p>
                            <div className="divide-y rounded-xl border bg-card">
                                {item.examiners.map((examiner) => (
                                    <Link
                                        key={`${examiner.id}-${examiner.role}`}
                                        href={examiner.profileUrl}
                                        className="flex flex-wrap items-center gap-2 px-4 py-3 text-sm transition-colors hover:bg-muted/20"
                                    >
                                        <span className="font-medium">
                                            {examiner.name}
                                        </span>
                                        <Badge variant="outline">
                                            {examiner.role}
                                        </Badge>
                                        <DecisionBadge
                                            decision={examiner.decision}
                                        />
                                        {examiner.score ? (
                                            <span className="inline-flex items-center gap-1 text-xs font-semibold">
                                                <Star className="size-3 text-amber-500" />
                                                {examiner.score}
                                            </span>
                                        ) : null}
                                    </Link>
                                ))}
                            </div>
                        </div>

                        <div className="rounded-xl border bg-muted/20 p-4">
                            <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Ringkasan Hasil
                            </p>
                            <div className="mt-3 grid gap-3 sm:grid-cols-4">
                                <MiniStat label="Hasil" value={item.result} />
                                <MiniStat
                                    label="Nilai"
                                    value={
                                        item.averageScore === null
                                            ? '-'
                                            : `${item.averageScore}`
                                    }
                                />
                                <MiniStat
                                    label="Grade"
                                    value={item.grade ?? '-'}
                                />
                                <MiniStat
                                    label="Revisi"
                                    value={`${item.revisionCount}`}
                                />
                            </div>
                        </div>
                    </div>
                </ScrollArea>
            </SheetContent>
        </Sheet>
    );
}

function ScheduleDialog({
    state,
    projects,
    open,
    onOpenChange,
}: {
    state: ScheduleDialogState | null;
    projects: SchedulableProject[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const initialFormData = useMemo(() => {
        const project =
            state?.project ??
            projects.find((item) => item.id === state?.exam?.projectId);
        const currentExaminers = state?.exam?.examiners ?? [];

        return {
            project_id: project ? String(project.id) : '',
            scheduled_for: state?.exam?.scheduledForInput ?? '',
            location: state?.exam?.location ?? '',
            mode: state?.exam?.mode ?? 'offline',
            examiner_1_user_id:
                state?.type === 'sempro' && currentExaminers[0]?.id
                    ? String(currentExaminers[0].id)
                    : '',
            examiner_2_user_id:
                state?.type === 'sempro' && currentExaminers[1]?.id
                    ? String(currentExaminers[1].id)
                    : '',
            additional_examiner_user_ids:
                state?.type === 'sidang'
                    ? currentExaminers
                          .filter((examiner) => examiner.role === 'Penguji')
                          .map((examiner) => String(examiner.id))
                    : [],
            notes: '',
        };
    }, [projects, state]);
    const form = useForm(initialFormData);
    const { clearErrors, setData } = form;

    useEffect(() => {
        setData(initialFormData);
        clearErrors();
    }, [clearErrors, initialFormData, setData]);

    if (!state) return null;

    const activeState = state;
    const currentProject = projects.find(
        (project) => String(project.id) === form.data.project_id,
    );
    const terms =
        currentProject?.terminology ?? state?.exam?.terminology ?? null;
    const title =
        activeState.type === 'sempro'
            ? activeState.exam
                ? `Ubah Jadwal ${terms?.proposalExamShort ?? 'Proposal'}`
                : `Jadwalkan ${terms?.proposalExamShort ?? 'Proposal'}`
            : activeState.exam
              ? `Ubah Jadwal ${terms?.finalExam ?? 'Ujian Akhir'}`
              : `Jadwalkan ${terms?.finalExam ?? 'Ujian Akhir'}`;
    const submitDisabled =
        form.processing ||
        form.data.project_id === '' ||
        form.data.scheduled_for === '' ||
        form.data.location === '' ||
        (activeState.type === 'sempro' &&
            (form.data.examiner_1_user_id === '' ||
                (form.data.examiner_2_user_id !== '' &&
                    form.data.examiner_1_user_id ===
                        form.data.examiner_2_user_id))) ||
        (activeState.type === 'sidang' &&
            form.data.additional_examiner_user_ids.length === 0);

    function submit() {
        const projectId = Number(form.data.project_id);
        const endpoint =
            activeState.type === 'sempro'
                ? `/kaprodi/projects/${projectId}/sempro`
                : `/kaprodi/projects/${projectId}/sidang`;

        form.post(endpoint, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>
                        Atur jadwal dan dosen terlibat untuk mahasiswa prodi.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="grid min-w-0 gap-1.5">
                        <label className="text-sm font-medium">Mahasiswa</label>
                        <select
                            value={form.data.project_id}
                            disabled={activeState.exam !== undefined}
                            onChange={(event) =>
                                form.setData('project_id', event.target.value)
                            }
                            className="h-9 w-full min-w-0 rounded-md border bg-background px-3 text-sm shadow-xs transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-60"
                        >
                            <option value="">Pilih mahasiswa</option>
                            {projects.map((project) => (
                                <option key={project.id} value={project.id}>
                                    {project.student} ({project.nim ?? '-'})
                                </option>
                            ))}
                        </select>
                    </div>

                    {currentProject ? (
                        <div className="rounded-lg border bg-muted/20 p-3">
                            <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Proyek
                            </p>
                            <p className="mt-1 text-sm font-medium">
                                {currentProject.title}
                            </p>
                            {activeState.type === 'sidang' ? (
                                <div className="mt-2 flex flex-wrap gap-1.5">
                                    {currentProject.supervisors.length > 0 ? (
                                        currentProject.supervisors.map(
                                            (supervisor) => (
                                                <Badge
                                                    key={supervisor.id}
                                                    variant="outline"
                                                >
                                                    {supervisor.role}:{' '}
                                                    {supervisor.name}
                                                </Badge>
                                            ),
                                        )
                                    ) : (
                                        <Badge variant="outline">
                                            Belum ada pembimbing aktif
                                        </Badge>
                                    )}
                                </div>
                            ) : null}
                        </div>
                    ) : null}

                    <div className="grid gap-3 sm:grid-cols-3">
                        <div className="grid min-w-0 gap-1.5 sm:col-span-1">
                            <label className="text-sm font-medium">
                                Jadwal
                            </label>
                            <Input
                                type="datetime-local"
                                value={form.data.scheduled_for}
                                onChange={(event) =>
                                    form.setData(
                                        'scheduled_for',
                                        event.target.value,
                                    )
                                }
                            />
                            {(form.errors as Record<string, string>)
                                .scheduled_for ? (
                                <p className="text-xs text-destructive">
                                    {
                                        (form.errors as Record<string, string>)
                                            .scheduled_for
                                    }
                                </p>
                            ) : null}
                        </div>
                        <div className="grid min-w-0 gap-1.5 sm:col-span-1">
                            <label className="text-sm font-medium">
                                Lokasi
                            </label>
                            <Input
                                value={form.data.location}
                                onChange={(event) =>
                                    form.setData('location', event.target.value)
                                }
                                placeholder="Ruang ujian / tautan meeting"
                            />
                        </div>
                        <div className="grid min-w-0 gap-1.5 sm:col-span-1">
                            <label className="text-sm font-medium">Mode</label>
                            <select
                                value={form.data.mode}
                                onChange={(event) =>
                                    form.setData('mode', event.target.value)
                                }
                                className="h-9 w-full min-w-0 rounded-md border bg-background px-3 text-sm shadow-xs transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="offline">Offline</option>
                                <option value="online">Online</option>
                                <option value="hybrid">Hybrid</option>
                            </select>
                        </div>
                    </div>

                    {activeState.type === 'sempro' ? (
                        <div className="grid gap-2">
                            <p className="text-xs text-muted-foreground">
                                Minimal 1 penguji. D3 umumnya 1 penguji; S1/S2
                                disarankan 2, mengikuti kebijakan prodi.
                            </p>
                            <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                                <LecturerSearchSelect
                                    label={`Penguji ${terms?.proposalExamShort ?? 'Proposal'} 1`}
                                    value={form.data.examiner_1_user_id}
                                    onChange={(value) =>
                                        form.setData(
                                            'examiner_1_user_id',
                                            value,
                                        )
                                    }
                                    projectId={
                                        Number(form.data.project_id) || null
                                    }
                                    purpose="examiner"
                                    excludeIds={[form.data.examiner_2_user_id]}
                                    error={form.errors.examiner_1_user_id}
                                />
                                <LecturerSearchSelect
                                    label={`Penguji ${terms?.proposalExamShort ?? 'Proposal'} 2 (Opsional)`}
                                    value={form.data.examiner_2_user_id}
                                    onChange={(value) =>
                                        form.setData(
                                            'examiner_2_user_id',
                                            value,
                                        )
                                    }
                                    projectId={
                                        Number(form.data.project_id) || null
                                    }
                                    purpose="examiner"
                                    excludeIds={[form.data.examiner_1_user_id]}
                                    optional
                                    error={form.errors.examiner_2_user_id}
                                />
                            </div>
                        </div>
                    ) : (
                        <LecturerMultiSearch
                            label={`Penguji ${terms?.finalExam ?? 'Ujian Akhir'} Tambahan`}
                            values={form.data.additional_examiner_user_ids}
                            onChange={(values) =>
                                form.setData(
                                    'additional_examiner_user_ids',
                                    values,
                                )
                            }
                            projectId={Number(form.data.project_id) || null}
                            purpose="examiner"
                            excludeIds={
                                currentProject?.supervisors.map((supervisor) =>
                                    String(supervisor.id),
                                ) ?? []
                            }
                            error={
                                (form.errors as Record<string, string>)
                                    .additional_examiner_user_ids
                            }
                        />
                    )}

                    {activeState.type === 'sidang' ? (
                        <div className="grid min-w-0 gap-1.5">
                            <label className="text-sm font-medium">
                                Catatan
                            </label>
                            <Textarea
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                                placeholder="Opsional, misalnya catatan kebutuhan jadwal."
                            />
                        </div>
                    ) : null}
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Batal
                    </Button>
                    <Button
                        type="button"
                        disabled={submitDisabled}
                        onClick={submit}
                    >
                        Simpan Jadwal
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function MiniStat({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-[10px] font-semibold tracking-widest text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-1 text-sm font-semibold">{value}</p>
        </div>
    );
}
