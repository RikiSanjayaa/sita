import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    CheckCircle2,
    Clock,
    Eye,
    FileWarning,
    Inbox,
    MapPin,
    MoreHorizontal,
    Pencil,
    Plus,
    Star,
    User,
    XCircle,
} from 'lucide-react';
import { useEffect, useId, useMemo, useState } from 'react';

import { type BimbinganEvent } from '@/components/bimbingan-calendar';
import { CollapsibleCalendarSection } from '@/components/collapsible-calendar-section';
import { DateRangePicker } from '@/components/date-range-picker';
import { RowIconButton } from '@/components/kaprodi/row-icon-button';
import {
    LecturerMultiSearch,
    LecturerSearchSelect,
} from '@/components/lecturer-search-select';
import {
    MentorshipMinimumAlert,
    type MentorshipProgressRow,
} from '@/components/mentorship-progress';
import {
    DataSection,
    DataToolbar,
    dataTh,
    stackedTable,
} from '@/components/patterns/data-section';
import { FilterPills } from '@/components/patterns/filter-pills';
import { PageHeader } from '@/components/patterns/page-header';
import { StatusBadge } from '@/components/patterns/status-badge';
import { StickyFilterBar } from '@/components/patterns/sticky-filter-bar';
import { ScheduleDetailModal } from '@/components/schedule-detail-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DataTableContainer,
    DataTableEmptyState,
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
    neutralAcademicTerminology,
    type AcademicTerminology,
} from '@/lib/academic-terminology';
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
    scoreRequired: boolean;
    grade: string | null;
    student: string;
    terminology: AcademicTerminology;
    nim: string | null;
    title: string;
    attempt: number;
    scheduledFor: string;
    scheduledForInput: string;
    scheduledDateStartInput: string;
    scheduledDateEndInput: string;
    scheduledTimeInput: string;
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
    mentorshipProgress: MentorshipProgressRow[];
    latestSempro: DefenseSummary | null;
    latestSidang: DefenseSummary | null;
};

type DefenseSummary = {
    id: number;
    attempt: number;
    status: string;
    statusKey: string;
    resultKey: string;
    scheduledFor: string;
    scheduledForInput: string;
    scheduledDateStartInput: string;
    scheduledDateEndInput: string;
    scheduledTimeInput: string;
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

function requestedScheduleType(): 'sempro' | 'sidang' | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const type = new URLSearchParams(window.location.search).get('jadwalkan');

    return type === 'sempro' || type === 'sidang' ? type : null;
}

function examScheduleBlockedReason(
    project: SchedulableProject,
    type: 'sempro' | 'sidang',
): string | null {
    const defense =
        type === 'sempro' ? project.latestSempro : project.latestSidang;
    const terms = project.terminology;
    const label = type === 'sempro' ? terms.proposalExamShort : terms.finalExam;

    if (!defense) {
        return null;
    }

    if (defense.statusKey === 'awaiting_finalization') {
        return `${label} masih menunggu finalisasi`;
    }

    if (defense.statusKey === 'cancelled') {
        return `${label} terakhir dibatalkan`;
    }

    if (defense.statusKey === 'completed') {
        if (defense.resultKey === 'fail') {
            return null;
        }

        return `${label} sudah lulus/selesai`;
    }

    return null;
}

function examScheduleOptionHint(
    project: SchedulableProject,
    type: 'sempro' | 'sidang',
): string | null {
    const defense =
        type === 'sempro' ? project.latestSempro : project.latestSidang;

    if (defense?.statusKey === 'completed' && defense.resultKey === 'fail') {
        return `percobaan #${defense.attempt + 1}`;
    }

    if (defense?.statusKey === 'scheduled' || defense?.statusKey === 'draft') {
        return 'sudah ada jadwal aktif';
    }

    return null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/kaprodi/dashboard' },
    {
        title: `${neutralAcademicTerminology.proposalExamShort} & ${neutralAcademicTerminology.finalExam}`,
        href: '/kaprodi/sempro-sidang',
    },
];

const PAGE_SIZE = 15;

function DecisionBadge({ decision }: { decision: string }) {
    if (decision === '-' || decision === 'Menunggu') {
        return (
            <StatusBadge domain="result" status="pending">
                <Clock className="size-3" />
                Belum Diputus
            </StatusBadge>
        );
    }

    if (decision === 'Lulus') {
        return (
            <StatusBadge domain="result" status="pass">
                <CheckCircle2 className="size-3" />
                Lulus
            </StatusBadge>
        );
    }

    if (decision === 'Lulus dengan Syarat') {
        return (
            <StatusBadge domain="result" status="pass_with_revision">
                <FileWarning className="size-3" />
                Lulus Bersyarat
            </StatusBadge>
        );
    }

    return (
        <StatusBadge domain="result" status="fail">
            <XCircle className="size-3" />
            {decision}
        </StatusBadge>
    );
}

export default function KaprodiSemproSidangPage() {
    const { auth, programStudi, exams, schedulableProjects, calendarEvents } =
        usePage<SharedData & SemproSidangProps>().props;
    const terms = neutralAcademicTerminology;
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
    // The dashboard links here with ?jadwalkan=sempro|sidang to open the schedule dialog.
    const [scheduleDialog, setScheduleDialog] =
        useState<ScheduleDialogState | null>(() => {
            const requested = requestedScheduleType();

            return (requested === 'sempro' && canScheduleSempro) ||
                (requested === 'sidang' && canScheduleSidang)
                ? { type: requested }
                : null;
        });

    useEffect(() => {
        const url = new URL(window.location.href);

        if (url.searchParams.has('jadwalkan')) {
            url.searchParams.delete('jadwalkan');
            window.history.replaceState(window.history.state, '', url);
        }
    }, []);

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
        { label: terms.proposalExamShort, value: 'sempro' },
        { label: terms.finalExam, value: 'sidang' },
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
            title={`${terms.proposalExamShort} & ${terms.finalExam}`}
            subtitle={`Monitoring ujian dan jadwal untuk ${programStudi.name}`}
        >
            <Head title={`${terms.proposalExamShort} & ${terms.finalExam}`} />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-6 lg:py-8">
                <PageHeader
                    title={`${terms.proposalExamShort} & ${terms.finalExam}`}
                    description={`Monitoring ujian dan jadwal untuk ${programStudi.name}. Kaprodi dapat mengatur jadwal dan dosen terlibat tanpa melakukan penilaian.`}
                />

                <CollapsibleCalendarSection
                    storageKey="kaprodi-sempro-sidang"
                    title="Kalender Ujian Prodi"
                    description={`Kalender ${terms.proposalExamShort} dan ${terms.finalExam} dari seluruh mahasiswa prodi.`}
                    events={calendarEvents}
                    onEventClick={setSelectedEvent}
                />

                <DataSection
                    title="Monitoring Ujian"
                    summary={`${filtered.length} ujian`}
                >
                    <StickyFilterBar>
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            placeholder="Cari nama, NIM, judul, atau dosen..."
                        >
                            {canScheduleSempro ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    className="max-md:h-11 max-md:flex-1"
                                    onClick={() =>
                                        setScheduleDialog({ type: 'sempro' })
                                    }
                                >
                                    <Plus className="size-4" />
                                    {`Jadwalkan ${terms.proposalExamShort}`}
                                </Button>
                            ) : null}
                            {canScheduleSidang ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    className="max-md:h-11 max-md:flex-1"
                                    onClick={() =>
                                        setScheduleDialog({ type: 'sidang' })
                                    }
                                >
                                    <Plus className="size-4" />
                                    Jadwalkan {terms.finalExam}
                                </Button>
                            ) : null}
                        </DataToolbar>

                        <div className="flex flex-col gap-y-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-x-3 sm:gap-y-2">
                            <FilterPills
                                label="Filter tipe ujian"
                                value={typeFilter}
                                onValueChange={setTypeFilter}
                                itemClassName="max-md:h-11 max-md:px-4"
                                options={typeTabs}
                            />
                            <span
                                aria-hidden="true"
                                className="hidden h-5 w-px bg-border sm:block"
                            />
                            <FilterPills
                                label="Filter status ujian"
                                value={statusFilter}
                                onValueChange={setStatusFilter}
                                itemClassName="max-md:h-11 max-md:px-4"
                                options={statusTabs}
                            />
                        </div>
                    </StickyFilterBar>

                    {filtered.length === 0 ? (
                        <DataTableEmptyState
                            icon={Inbox}
                            title="Tidak ada ujian yang cocok"
                            description="Coba ubah filter atau kata kunci pencarian."
                        />
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
                            <table
                                className={cn(
                                    stackedTable.table,
                                    'md:min-w-[900px]',
                                )}
                            >
                                <thead className={stackedTable.thead}>
                                    <tr className="border-b bg-muted/40">
                                        <th className={dataTh}>Mahasiswa</th>
                                        <th className={dataTh}>Jadwal</th>
                                        <th className={dataTh}>
                                            Dosen terlibat
                                        </th>
                                        <th className={dataTh}>Nilai</th>
                                        <th className={dataTh}>Status</th>
                                        <th className={dataTh}>
                                            <span className="sr-only">
                                                Aksi
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    className={cn(
                                        stackedTable.tbody,
                                        'md:divide-y',
                                    )}
                                >
                                    {paginated.map((item) => (
                                        <tr
                                            key={item.id}
                                            className={cn(
                                                stackedTable.tr,
                                                'cursor-pointer align-top transition-colors hover:bg-muted/30',
                                            )}
                                            onClick={() => setSelected(item)}
                                        >
                                            <td
                                                className={cn(
                                                    stackedTable.tdPlain,
                                                    'md:max-w-72',
                                                )}
                                            >
                                                <button
                                                    type="button"
                                                    onClick={(event) => {
                                                        event.stopPropagation();
                                                        setSelected(item);
                                                    }}
                                                    className="rounded-sm text-left font-medium hover:text-primary focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                                >
                                                    {item.student}
                                                </button>
                                                <p className="text-muted-foreground tabular-nums">
                                                    {item.nim ?? '-'}
                                                </p>
                                                <p className="mt-1 line-clamp-2 text-xs text-pretty text-muted-foreground">
                                                    {item.title}
                                                </p>
                                            </td>
                                            <td
                                                data-label="Jadwal"
                                                className={stackedTable.td}
                                            >
                                                <p className="font-medium">
                                                    {item.type} #{item.attempt}
                                                </p>
                                                <p className="text-muted-foreground">
                                                    {item.scheduledFor}
                                                </p>
                                            </td>
                                            <td
                                                data-label="Dosen terlibat"
                                                className={stackedTable.td}
                                            >
                                                <ul className="flex flex-col gap-1 text-xs text-pretty">
                                                    {item.examiners.map(
                                                        (examiner) => (
                                                            <li
                                                                key={`${item.id}-${examiner.id}-${examiner.role}`}
                                                            >
                                                                <span className="text-muted-foreground">
                                                                    {
                                                                        examiner.role
                                                                    }
                                                                    :{' '}
                                                                </span>
                                                                <Link
                                                                    href={
                                                                        examiner.profileUrl
                                                                    }
                                                                    onClick={(
                                                                        event,
                                                                    ) =>
                                                                        event.stopPropagation()
                                                                    }
                                                                    className="font-medium hover:text-primary hover:underline"
                                                                >
                                                                    {
                                                                        examiner.name
                                                                    }
                                                                </Link>
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            </td>
                                            <td
                                                data-label="Nilai"
                                                className={stackedTable.td}
                                            >
                                                {item.averageScore === null &&
                                                item.grade === null ? (
                                                    <span className="text-muted-foreground">
                                                        {item.scoreRequired
                                                            ? '-'
                                                            : 'Tanpa nilai'}
                                                    </span>
                                                ) : (
                                                    <span className="font-medium tabular-nums">
                                                        {item.averageScore ??
                                                            '-'}
                                                        {item.grade ? (
                                                            <span className="ml-1.5 text-muted-foreground">
                                                                {item.grade}
                                                            </span>
                                                        ) : null}
                                                    </span>
                                                )}
                                            </td>
                                            <td
                                                data-label="Status"
                                                className={stackedTable.td}
                                            >
                                                <StatusBadge
                                                    domain="defense"
                                                    status={item.statusKey}
                                                >
                                                    {item.status}
                                                </StatusBadge>
                                            </td>
                                            <td
                                                className={
                                                    stackedTable.tdActions
                                                }
                                            >
                                                <div
                                                    className="flex items-center justify-end gap-1 max-md:justify-start"
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    {item.canManageSchedule &&
                                                    ((item.typeKey ===
                                                        'sempro' &&
                                                        canScheduleSempro) ||
                                                        (item.typeKey ===
                                                            'sidang' &&
                                                            canScheduleSidang)) ? (
                                                        <RowIconButton
                                                            label="Ubah jadwal"
                                                            onClick={() =>
                                                                setScheduleDialog(
                                                                    {
                                                                        type: item.typeKey as
                                                                            | 'sempro'
                                                                            | 'sidang',
                                                                        exam: item,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <Pencil className="size-4" />
                                                        </RowIconButton>
                                                    ) : null}
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger
                                                            asChild
                                                        >
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label="Aksi lainnya"
                                                                className="text-muted-foreground hover:text-foreground max-md:size-11"
                                                            >
                                                                <MoreHorizontal className="size-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent
                                                            align="end"
                                                            className="w-52"
                                                        >
                                                            <DropdownMenuItem
                                                                onSelect={() =>
                                                                    setSelected(
                                                                        item,
                                                                    )
                                                                }
                                                            >
                                                                <Eye className="size-4" />
                                                                Lihat detail
                                                                ujian
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={
                                                                        item.studentProfileUrl
                                                                    }
                                                                >
                                                                    <User className="size-4" />
                                                                    Buka profil
                                                                    mahasiswa
                                                                </Link>
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </DataTableContainer>
                    )}
                </DataSection>
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
                        <StatusBadge domain="defense" status={item.statusKey}>
                            {item.status}
                        </StatusBadge>
                    </div>
                    <SheetTitle className="mt-1 text-base leading-snug">
                        {item.title}
                    </SheetTitle>
                    <SheetDescription>
                        Detail ujian untuk pemantauan kaprodi (hanya baca).
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
                            <p className="mb-2 text-xs font-medium text-muted-foreground">
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
                                                <Star className="size-3 text-warning-muted-foreground" />
                                                {examiner.score}
                                            </span>
                                        ) : null}
                                    </Link>
                                ))}
                            </div>
                        </div>

                        <div className="rounded-xl border bg-muted/20 p-4">
                            <p className="text-xs font-medium text-muted-foreground">
                                Ringkasan Hasil
                            </p>
                            <div className="mt-3 grid gap-3 sm:grid-cols-4">
                                <MiniStat label="Hasil" value={item.result} />
                                <MiniStat
                                    label="Nilai"
                                    value={
                                        item.averageScore !== null
                                            ? `${item.averageScore}`
                                            : item.scoreRequired
                                              ? '-'
                                              : 'Tanpa nilai'
                                    }
                                />
                                {(item.scoreRequired ||
                                    item.averageScore !== null) && (
                                    <MiniStat
                                        label="Grade"
                                        value={item.grade ?? '-'}
                                    />
                                )}
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
            scheduled_for: '',
            scheduled_date_start: state?.exam?.scheduledDateStartInput ?? '',
            scheduled_date_end:
                state?.exam?.scheduledDateEndInput ||
                state?.exam?.scheduledDateStartInput ||
                '',
            scheduled_time: state?.exam?.scheduledTimeInput ?? '',
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
    const resetKey = useMemo(() => {
        return [
            open ? 'open' : 'closed',
            state?.type ?? 'none',
            state?.project?.id ?? 'new',
            state?.exam?.id ?? 'new',
        ].join(':');
    }, [open, state]);
    const form = useForm(initialFormData);
    const fieldId = useId();

    useEffect(() => {
        form.setData(initialFormData);
        form.clearErrors();
        // Reset should only follow the opened dialog target, not every form
        // change, otherwise submit can read freshly-reset state.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [resetKey]);

    if (!state) return null;

    const activeState = state;
    const currentProject = projects.find(
        (project) => String(project.id) === form.data.project_id,
    );
    const currentProjectScheduleBlockedReason =
        currentProject && !activeState.exam
            ? examScheduleBlockedReason(currentProject, activeState.type)
            : null;
    const terms =
        currentProject?.terminology ?? state?.exam?.terminology ?? null;
    const title =
        activeState.type === 'sempro'
            ? activeState.exam
                ? `Ubah Jadwal ${terms?.proposalExamShort ?? neutralAcademicTerminology.proposalExamShort}`
                : `Jadwalkan ${terms?.proposalExamShort ?? neutralAcademicTerminology.proposalExamShort}`
            : activeState.exam
              ? `Ubah Jadwal ${terms?.finalExam ?? neutralAcademicTerminology.finalExam}`
              : `Jadwalkan ${terms?.finalExam ?? neutralAcademicTerminology.finalExam}`;
    const errors = form.errors as Record<string, string | undefined>;
    const projectError =
        errors.project_id ??
        errors.project ??
        currentProjectScheduleBlockedReason;
    const dateRangeError =
        errors.scheduled_date_start ??
        errors.scheduled_date_end ??
        errors.scheduled_for;
    const submitDisabled =
        form.processing ||
        form.data.project_id === '' ||
        Boolean(currentProjectScheduleBlockedReason);

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
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>
                        Atur jadwal dan dosen terlibat untuk mahasiswa prodi.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="grid min-w-0 gap-1.5">
                        <label
                            htmlFor={`${fieldId}-project`}
                            className="text-sm font-medium"
                        >
                            Mahasiswa
                        </label>
                        <select
                            id={`${fieldId}-project`}
                            value={form.data.project_id}
                            disabled={activeState.exam !== undefined}
                            onChange={(event) =>
                                form.setData('project_id', event.target.value)
                            }
                            className="h-11 w-full min-w-0 rounded-md border bg-background px-3 text-base shadow-xs transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-60 md:h-9 md:text-sm"
                        >
                            <option value="">Pilih mahasiswa</option>
                            {projects.map((project) => {
                                const blockedReason = !activeState.exam
                                    ? examScheduleBlockedReason(
                                          project,
                                          activeState.type,
                                      )
                                    : null;
                                const hint =
                                    !blockedReason && !activeState.exam
                                        ? examScheduleOptionHint(
                                              project,
                                              activeState.type,
                                          )
                                        : null;

                                return (
                                    <option
                                        key={project.id}
                                        value={project.id}
                                        disabled={Boolean(blockedReason)}
                                    >
                                        {project.student} ({project.nim ?? '-'})
                                        {blockedReason
                                            ? ` — ${blockedReason}`
                                            : hint
                                              ? ` — ${hint}`
                                              : ''}
                                    </option>
                                );
                            })}
                        </select>
                        {projectError ? (
                            <p className="text-xs text-destructive">
                                {projectError}
                            </p>
                        ) : form.data.project_id === '' ? (
                            <p className="text-xs text-muted-foreground">
                                Pilih mahasiswa terlebih dahulu untuk
                                melanjutkan penjadwalan.
                            </p>
                        ) : null}
                    </div>

                    {currentProject ? (
                        <div className="rounded-lg border bg-muted/20 p-3">
                            <p className="text-xs font-medium text-muted-foreground">
                                {neutralAcademicTerminology.finalWork}
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

                    {currentProject && activeState.type === 'sidang' ? (
                        <MentorshipMinimumAlert
                            progress={currentProject.mentorshipProgress}
                        />
                    ) : null}

                    <div className="grid gap-3 sm:grid-cols-4">
                        <div className="grid min-w-0 gap-1.5 sm:col-span-2">
                            <label className="text-sm font-medium">
                                Rentang Tanggal
                            </label>
                            <DateRangePicker
                                startDate={form.data.scheduled_date_start}
                                endDate={form.data.scheduled_date_end}
                                onChange={(range) => {
                                    form.setData(
                                        'scheduled_date_start',
                                        range.from,
                                    );
                                    form.setData(
                                        'scheduled_date_end',
                                        range.to,
                                    );
                                }}
                                error={dateRangeError}
                            />
                        </div>
                        <div className="grid min-w-0 gap-1.5 sm:col-span-1">
                            <label
                                htmlFor={`${fieldId}-time`}
                                className="text-sm font-medium"
                            >
                                Jam
                            </label>
                            <Input
                                id={`${fieldId}-time`}
                                className="max-md:h-11"
                                type="time"
                                value={form.data.scheduled_time}
                                onChange={(event) =>
                                    form.setData(
                                        'scheduled_time',
                                        event.target.value,
                                    )
                                }
                            />
                            {(form.errors as Record<string, string>)
                                .scheduled_time ? (
                                <p className="text-xs text-destructive">
                                    {
                                        (form.errors as Record<string, string>)
                                            .scheduled_time
                                    }
                                </p>
                            ) : null}
                        </div>
                        <div className="grid min-w-0 gap-1.5 sm:col-span-1">
                            <label
                                htmlFor={`${fieldId}-location`}
                                className="text-sm font-medium"
                            >
                                Lokasi
                            </label>
                            <Input
                                id={`${fieldId}-location`}
                                className="max-md:h-11"
                                value={form.data.location}
                                onChange={(event) =>
                                    form.setData('location', event.target.value)
                                }
                                placeholder="Ruang ujian / tautan meeting"
                            />
                            {errors.location ? (
                                <p className="text-xs text-destructive">
                                    {errors.location}
                                </p>
                            ) : null}
                        </div>
                        <div className="grid min-w-0 gap-1.5 sm:col-span-1">
                            <label
                                htmlFor={`${fieldId}-mode`}
                                className="text-sm font-medium"
                            >
                                Mode
                            </label>
                            <select
                                id={`${fieldId}-mode`}
                                value={form.data.mode}
                                onChange={(event) =>
                                    form.setData('mode', event.target.value)
                                }
                                className="h-11 w-full min-w-0 rounded-md border bg-background px-3 text-base shadow-xs transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:h-9 md:text-sm"
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
                                    label={`Penguji ${terms?.proposalExamShort ?? neutralAcademicTerminology.proposalExamShort} 1`}
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
                                    label={`Penguji ${terms?.proposalExamShort ?? neutralAcademicTerminology.proposalExamShort} 2 (Opsional)`}
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
                            label={`Penguji ${terms?.finalExam ?? neutralAcademicTerminology.finalExam} Tambahan`}
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
                            <label
                                htmlFor={`${fieldId}-notes`}
                                className="text-sm font-medium"
                            >
                                Catatan
                            </label>
                            <Textarea
                                id={`${fieldId}-notes`}
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
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <p className="mt-1 text-sm font-semibold">{value}</p>
        </div>
    );
}
