import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    CalendarClock,
    CircleHelp,
    FileText,
    MoreHorizontal,
    NotebookPen,
    UserCog,
    UserRound,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { RowIconButton } from '@/components/kaprodi/row-icon-button';
import { LecturerSearchSelect } from '@/components/lecturer-search-select';
import {
    MentorshipProgressList,
    type MentorshipProgressRow,
} from '@/components/mentorship-progress';
import {
    DataSection,
    DataSelect,
    DataToolbar,
    dataTh,
    stackedTable,
} from '@/components/patterns/data-section';
import { FilterPills } from '@/components/patterns/filter-pills';
import { StatusBadge } from '@/components/patterns/status-badge';
import { StickyFilterBar } from '@/components/patterns/sticky-filter-bar';
import {
    StudentCohort,
    StudentIdentity,
    StudentTitleAdvisors,
} from '@/components/patterns/student-cells';
import {
    StudentProgressPeek,
    type StudentProgress,
} from '@/components/profile/student-progress-peek';
import {
    type ProjectNotes,
    ThesisNotesPanel,
} from '@/components/thesis-notes-panel';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DataTableContainer,
    DataTableEmptyState,
    DataTablePagination,
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
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useIsMobile } from '@/hooks/use-mobile';
import { useUrlState } from '@/hooks/use-url-state';
import KaprodiLayout from '@/layouts/kaprodi-layout';
import {
    neutralAcademicTerminology,
    phaseLabels,
} from '@/lib/academic-terminology';
import { toneClassName } from '@/lib/status-styles';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';

const PAGE_SIZE = 10;

type ProgramStudi = {
    name: string;
};

type StudentRow = {
    id: number;
    projectId: number | null;
    canManageSupervisors: boolean;
    name: string;
    nim: string;
    avatar: string | null;
    status: string;
    angkatan: string | null;
    degreeLevel: string;
    concentration: string | null;
    isObeCurriculum: boolean;
    phase: string;
    phaseKey: string;
    projectState: string;
    projectStateKey: string;
    mentorshipTicket: { suspended: boolean; validUntil: string | null } | null;
    title: string;
    advisors: string[];
    supervisorAssignments: {
        lecturerUserId: number;
        name: string;
        role: string;
    }[];
    mentorshipProgress: MentorshipProgressRow[];
    discussionLecturer: { lecturerUserId: number; name: string } | null;
    progressRisk: {
        level: 'high' | 'medium' | 'low';
        label: string;
        description: string;
        lastActivityAt: string;
        lastActivityLabel: string;
        daysIdle: number | null;
        signals: string[];
    };
    needsAction: FocusFilter[];
    progress: StudentProgress | null;
    profileUrl: string;
};

type ArchiveRow = {
    id: number;
    student: string;
    nim: string | null;
    avatar: string | null;
    angkatan: string | null;
    degreeLevel: string;
    concentration: string | null;
    isObeCurriculum: boolean;
    title: string;
    state: string;
    phase: string;
    completedAt: string;
    documentCount: number;
    defenseCount: number;
    profileUrl: string;
};

type DirectoryRow = {
    kind: 'active' | 'archive';
    id: number;
    projectId: number | null;
    canManageSupervisors: boolean;
    name: string;
    nim: string;
    avatar: string | null;
    angkatan: string | null;
    degreeLevel: string;
    concentration: string | null;
    isObeCurriculum: boolean;
    phase: string;
    phaseKey: ActivePhaseFilter;
    title: string;
    advisors: string[];
    progressRisk: StudentRow['progressRisk'] | null;
    progress: StudentProgress | null;
    profileUrl: string;
    completedAt: string | null;
    mentorshipTicket: StudentRow['mentorshipTicket'];
    activeStudent: StudentRow | null;
};

type MahasiswaProps = {
    programStudi: ProgramStudi;
    filters: {
        angkatan: string[];
        concentrations: string[];
    };
    students: StudentRow[];
    archives: ArchiveRow[];
    projectNotes: Record<string, ProjectNotes>;
};

type ActivePhaseFilter =
    | 'semua'
    | 'title_review'
    | 'sempro'
    | 'research'
    | 'sidang'
    | 'completed'
    | 'cancelled'
    | 'none';

type RiskFilter = 'semua' | 'high' | 'medium' | 'low';
type FocusFilter =
    | 'semua'
    | 'masa_habis'
    | 'tanpa_pembimbing'
    | 'bimbingan_kurang'
    | 'tidak_aktif';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/kaprodi/dashboard' },
    { title: 'Mahasiswa Prodi', href: '/kaprodi/mahasiswa' },
];

function searchUrl(path: string, value: string) {
    return `${path}?search=${encodeURIComponent(value)}`;
}

export default function KaprodiMahasiswaPage() {
    const { auth, programStudi, filters, students, archives } = usePage<
        SharedData & MahasiswaProps
    >().props;
    const terms = neutralAcademicTerminology;
    const canManageSupervisors =
        auth.kaprodiCapabilities?.manage_supervisors ?? true;

    const activeRows = students.filter(
        (student) =>
            student.status === 'Aktif' &&
            !['completed', 'cancelled'].includes(student.projectStateKey),
    );
    const totalRows = activeRows.length + archives.length;

    return (
        <KaprodiLayout
            breadcrumbs={breadcrumbs}
            title="Mahasiswa Prodi"
            subtitle={`Daftar mahasiswa dan arsip ${terms.finalWorkLower} di ${programStudi.name}`}
        >
            <Head title="Mahasiswa Prodi" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-6 lg:py-8">
                <DataSection
                    level={1}
                    title="Daftar Mahasiswa"
                    description={`${totalRows} mahasiswa dan arsip ${terms.finalWorkLower} di ${programStudi.name}.`}
                >
                    <StudentTable
                        rows={activeRows}
                        archives={archives}
                        filters={filters}
                        canManageSupervisors={canManageSupervisors}
                        emptyText={`Belum ada mahasiswa atau arsip ${terms.finalWorkLower}`}
                    />
                </DataSection>
            </div>
        </KaprodiLayout>
    );
}

function StudentTable({
    rows,
    archives,
    filters,
    canManageSupervisors,
    emptyText,
}: {
    rows: StudentRow[];
    archives: ArchiveRow[];
    filters: MahasiswaProps['filters'];
    canManageSupervisors: boolean;
    emptyText: string;
}) {
    const { academicTerminology: terms, projectNotes } = usePage<
        SharedData & MahasiswaProps
    >().props;
    const [notesRow, setNotesRow] = useState<DirectoryRow | null>(null);
    const [search, setSearch] = useUrlState('activeSearch', '');
    const [phaseFilter, setPhaseFilter] = useUrlState<ActivePhaseFilter>(
        'activePhase',
        'semua',
    );
    const [riskFilter, setRiskFilter] = useUrlState<RiskFilter>(
        'activeRisk',
        'semua',
    );
    const [focusFilter, setFocusFilter] = useUrlState<FocusFilter>(
        'activeFocus',
        'semua',
    );
    const [angkatanFilter, setAngkatanFilter] = useUrlState(
        'activeAngkatan',
        'semua',
    );
    const [concentrationFilter, setConcentrationFilter] = useUrlState(
        'activeKonsentrasi',
        'semua',
    );
    const [page, setPage] = useUrlState('activePage', 1);
    const mobile = useIsMobile();
    const [pageSizeOverride, setPageSize] = useUrlState('activePageSize', 0);
    const pageSize = pageSizeOverride || (mobile ? 5 : PAGE_SIZE);
    const [supervisorDialog, setSupervisorDialog] = useState<StudentRow | null>(
        null,
    );

    const phaseOptions: { label: string; value: ActivePhaseFilter }[] = [
        { label: phaseLabels.title_review, value: 'title_review' },
        { label: phaseLabels.sempro, value: 'sempro' },
        { label: phaseLabels.research, value: 'research' },
        { label: phaseLabels.sidang, value: 'sidang' },
        { label: phaseLabels.completed, value: 'completed' },
        { label: 'Dibatalkan', value: 'cancelled' },
        {
            label: `Belum Ada ${neutralAcademicTerminology.finalWork}`,
            value: 'none',
        },
    ];

    const riskOptions: { label: string; value: RiskFilter }[] = [
        { label: 'Risiko Telat', value: 'high' },
        { label: 'Perlu Dipantau', value: 'medium' },
        { label: 'Terkendali', value: 'low' },
    ];

    const focusOptions: { label: string; value: FocusFilter }[] = [
        { label: 'Masa aktif habis', value: 'masa_habis' },
        { label: 'Belum ada pembimbing', value: 'tanpa_pembimbing' },
        { label: 'Bimbingan belum cukup', value: 'bimbingan_kurang' },
        { label: 'Tidak aktif', value: 'tidak_aktif' },
    ];

    const directoryRows = useMemo<DirectoryRow[]>(() => {
        const activeDirectoryRows = rows.map(
            (row): DirectoryRow => ({
                kind: 'active',
                id: row.id,
                projectId: row.projectId,
                canManageSupervisors: row.canManageSupervisors,
                name: row.name,
                nim: row.nim,
                avatar: row.avatar,
                angkatan: row.angkatan,
                degreeLevel: row.degreeLevel,
                concentration: row.concentration,
                isObeCurriculum: row.isObeCurriculum,
                phase: row.phase,
                phaseKey: row.phaseKey as ActivePhaseFilter,
                title: row.title,
                advisors: row.advisors,
                progressRisk: row.progressRisk,
                progress: row.progress,
                profileUrl: row.profileUrl,
                completedAt: null,
                mentorshipTicket: row.mentorshipTicket,
                activeStudent: row,
            }),
        );

        const archiveDirectoryRows = archives.map((row): DirectoryRow => {
            const isCancelled = row.state === 'Dibatalkan';

            return {
                kind: 'archive',
                id: row.id,
                projectId: null,
                canManageSupervisors: false,
                name: row.student,
                nim: row.nim ?? '',
                avatar: row.avatar,
                angkatan: row.angkatan,
                degreeLevel: row.degreeLevel,
                concentration: row.concentration,
                isObeCurriculum: row.isObeCurriculum,
                phase: row.state,
                phaseKey: isCancelled ? 'cancelled' : 'completed',
                title: row.title,
                advisors: [],
                progressRisk: null,
                progress: null,
                profileUrl: row.profileUrl,
                completedAt: row.completedAt,
                mentorshipTicket: null,
                activeStudent: null,
            };
        });

        return [...activeDirectoryRows, ...archiveDirectoryRows];
    }, [archives, rows]);

    const filtered = useMemo(() => {
        const query = search.trim().toLowerCase();

        return directoryRows.filter((row) => {
            const matchesSearch =
                !query ||
                [
                    row.name,
                    row.nim,
                    row.phase,
                    row.title,
                    row.degreeLevel,
                    row.concentration ?? '',
                    row.progressRisk?.label ?? '',
                    row.progressRisk?.description ?? '',
                    row.progressRisk?.lastActivityLabel ?? '',
                    ...(row.progressRisk?.signals ?? []),
                    ...row.advisors,
                ]
                    .join(' ')
                    .toLowerCase()
                    .includes(query);

            return (
                matchesSearch &&
                (phaseFilter === 'semua' || row.phaseKey === phaseFilter) &&
                (riskFilter === 'semua' ||
                    row.progressRisk?.level === riskFilter) &&
                (focusFilter === 'semua' ||
                    (row.activeStudent?.needsAction ?? []).includes(
                        focusFilter,
                    )) &&
                (angkatanFilter === 'semua' ||
                    String(row.angkatan ?? '') === angkatanFilter) &&
                (concentrationFilter === 'semua' ||
                    row.concentration === concentrationFilter)
            );
        });
    }, [
        angkatanFilter,
        concentrationFilter,
        directoryRows,
        phaseFilter,
        riskFilter,
        focusFilter,
        search,
    ]);

    const safePage = Math.max(
        1,
        Math.min(page, Math.max(1, Math.ceil(filtered.length / pageSize))),
    );
    const paginated = filtered.slice(
        (safePage - 1) * pageSize,
        safePage * pageSize,
    );

    function resetPage() {
        setPage(1);
    }

    const phaseCounts = directoryRows.reduce<Record<string, number>>(
        (counts, row) => ({
            ...counts,
            [row.phaseKey]: (counts[row.phaseKey] ?? 0) + 1,
        }),
        {},
    );
    const phasePills: {
        label: string;
        value: ActivePhaseFilter;
        count: number;
    }[] = [
        { label: 'Semua', value: 'semua', count: directoryRows.length },
        ...phaseOptions.map((option) => ({
            ...option,
            count: phaseCounts[option.value] ?? 0,
        })),
    ];
    const hasActiveFilter =
        riskFilter !== 'semua' ||
        focusFilter !== 'semua' ||
        angkatanFilter !== 'semua' ||
        concentrationFilter !== 'semua' ||
        phaseFilter !== 'semua';
    const selectOptions = (
        allLabel: string,
        options: (string | number)[],
    ): { value: string; label: string }[] => [
        { value: 'semua', label: allLabel },
        ...options.map((option) => ({
            value: String(option),
            label: String(option),
        })),
    ];

    return (
        <div className="space-y-4">
            <StickyFilterBar>
                <DataToolbar
                    search={search}
                    onSearchChange={(value) => {
                        setSearch(value);
                        resetPage();
                    }}
                    placeholder="Cari nama atau NIM..."
                >
                    <DataSelect
                        label="Filter risiko"
                        value={riskFilter}
                        onValueChange={(value) => {
                            setRiskFilter(value as RiskFilter);
                            resetPage();
                        }}
                        placeholder="Semua risiko"
                        options={[
                            { value: 'semua', label: 'Semua risiko' },
                            ...riskOptions,
                        ]}
                        className="md:w-40"
                    />
                    <DataSelect
                        label="Filter tindakan"
                        value={focusFilter}
                        onValueChange={(value) => {
                            setFocusFilter(value as FocusFilter);
                            resetPage();
                        }}
                        placeholder="Semua mahasiswa"
                        options={[
                            { value: 'semua', label: 'Semua mahasiswa' },
                            ...focusOptions,
                        ]}
                        className="md:w-52"
                    />
                    <DataSelect
                        label="Filter angkatan"
                        value={angkatanFilter}
                        onValueChange={(value) => {
                            setAngkatanFilter(value);
                            resetPage();
                        }}
                        placeholder="Semua angkatan"
                        options={selectOptions(
                            'Semua angkatan',
                            filters.angkatan,
                        )}
                        className="md:w-40"
                    />
                    <DataSelect
                        label="Filter konsentrasi"
                        value={concentrationFilter}
                        onValueChange={(value) => {
                            setConcentrationFilter(value);
                            resetPage();
                        }}
                        placeholder="Semua konsentrasi"
                        options={selectOptions(
                            'Semua konsentrasi',
                            filters.concentrations,
                        )}
                        className="md:w-48"
                    />
                </DataToolbar>

                <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                    <FilterPills
                        label="Filter tahap"
                        value={phaseFilter}
                        onValueChange={(value) => {
                            setPhaseFilter(value);
                            resetPage();
                        }}
                        itemClassName="max-md:h-11 max-md:px-4"
                        options={phasePills}
                    />
                    {hasActiveFilter ? (
                        <Button
                            type="button"
                            data-desktop-filter-reset
                            variant="ghost"
                            size="sm"
                            className="text-muted-foreground max-md:h-11"
                            onClick={() => {
                                setPhaseFilter('semua');
                                setRiskFilter('semua');
                                setFocusFilter('semua');
                                setAngkatanFilter('semua');
                                setConcentrationFilter('semua');
                                resetPage();
                            }}
                        >
                            Reset filter
                        </Button>
                    ) : null}
                </div>
            </StickyFilterBar>

            {paginated.length > 0 ? (
                <DataTableContainer
                    pagination={
                        <DataTablePagination
                            currentPage={safePage}
                            totalPages={Math.max(
                                1,
                                Math.ceil(filtered.length / pageSize),
                            )}
                            totalItems={filtered.length}
                            pageSize={pageSize}
                            onPageChange={setPage}
                            onPageSizeChange={(nextPageSize) => {
                                setPageSize(nextPageSize);
                                resetPage();
                            }}
                            itemLabel="mahasiswa"
                        />
                    }
                >
                    <table
                        className={cn(stackedTable.table, 'md:min-w-[880px]')}
                    >
                        <thead className={stackedTable.thead}>
                            <tr className="border-b bg-muted/40">
                                <th className={dataTh}>Mahasiswa</th>
                                <th className={dataTh}>Angkatan</th>
                                <th className={dataTh}>Tahap</th>
                                <th className={dataTh}>Risiko</th>
                                <th className={dataTh}>Judul dan pembimbing</th>
                                <th className={dataTh}>
                                    <span className="sr-only">Aksi</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            className={cn(stackedTable.tbody, 'md:divide-y')}
                        >
                            {paginated.map((row) => (
                                <tr
                                    key={`${row.kind}-${row.id}`}
                                    className={cn(
                                        stackedTable.tr,
                                        'align-top transition-colors hover:bg-muted/30',
                                    )}
                                >
                                    <td className={stackedTable.tdPlain}>
                                        <StudentIdentity
                                            name={row.name}
                                            nim={row.nim}
                                            avatar={row.avatar}
                                            profileUrl={row.profileUrl}
                                        />
                                    </td>
                                    <td className={stackedTable.td}>
                                        <StudentCohort
                                            degreeLevel={row.degreeLevel}
                                            angkatan={row.angkatan}
                                            concentration={row.concentration}
                                            isObeCurriculum={
                                                row.isObeCurriculum
                                            }
                                        />
                                    </td>
                                    <td className={stackedTable.tdDetail}>
                                        {row.progress ? (
                                            <StudentProgressPeek
                                                progress={row.progress}
                                            />
                                        ) : row.phase ? (
                                            <StatusBadge
                                                domain="phase"
                                                status={row.phaseKey}
                                            >
                                                {row.phase}
                                            </StatusBadge>
                                        ) : (
                                            <span className="text-muted-foreground max-md:hidden">
                                                -
                                            </span>
                                        )}
                                        {row.mentorshipTicket?.suspended &&
                                        !row.progress?.detail ? (
                                            <StatusBadge
                                                domain="ticket"
                                                status="overdue"
                                                className="mt-1"
                                            >
                                                Masa aktif habis
                                            </StatusBadge>
                                        ) : null}
                                        {row.completedAt ? (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {row.completedAt}
                                            </p>
                                        ) : null}
                                    </td>
                                    <td className={stackedTable.td}>
                                        {row.progressRisk ? (
                                            <ProgressRiskBadge
                                                risk={row.progressRisk}
                                            />
                                        ) : (
                                            <span className="text-muted-foreground max-md:hidden">
                                                -
                                            </span>
                                        )}
                                    </td>
                                    <td
                                        className={cn(
                                            stackedTable.tdDetail,
                                            'md:max-w-xs',
                                        )}
                                    >
                                        <StudentTitleAdvisors
                                            title={row.title}
                                            advisors={row.advisors}
                                        />
                                    </td>
                                    <td className={stackedTable.tdActions}>
                                        <div className="flex items-center justify-end gap-1">
                                            {canManageSupervisors &&
                                            row.canManageSupervisors &&
                                            row.projectId !== null &&
                                            row.activeStudent ? (
                                                <RowIconButton
                                                    label="Atur pembimbing"
                                                    onClick={() =>
                                                        setSupervisorDialog(
                                                            row.activeStudent,
                                                        )
                                                    }
                                                >
                                                    <UserCog className="size-4" />
                                                </RowIconButton>
                                            ) : null}
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
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
                                                    className="w-56"
                                                >
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={
                                                                row.profileUrl
                                                            }
                                                        >
                                                            <UserRound className="size-4" />
                                                            Buka profil
                                                            mahasiswa
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={searchUrl(
                                                                '/kaprodi/dokumen',
                                                                row.nim,
                                                            )}
                                                        >
                                                            <FileText className="size-4" />
                                                            Dokumen mahasiswa
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={searchUrl(
                                                                '/kaprodi/sempro-sidang',
                                                                row.nim,
                                                            )}
                                                        >
                                                            <CalendarClock className="size-4" />
                                                            {`Jadwal ${terms.proposalExamShort} dan ${terms.finalExamShort}`}
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            setNotesRow(row)
                                                        }
                                                    >
                                                        <NotebookPen className="size-4" />
                                                        Catatan dosen
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
            ) : (
                <DataTableEmptyState
                    icon={AlertCircle}
                    title={
                        directoryRows.length > 0
                            ? 'Tidak ada mahasiswa yang cocok'
                            : emptyText
                    }
                    description={
                        directoryRows.length > 0
                            ? 'Coba ubah filter atau kata kunci pencarian.'
                            : undefined
                    }
                />
            )}
            <Sheet
                open={notesRow !== null}
                onOpenChange={(open) => {
                    if (!open) setNotesRow(null);
                }}
            >
                <SheetContent
                    side="right"
                    className="w-full gap-0 overflow-y-auto p-0 sm:max-w-xl"
                >
                    <SheetHeader className="border-b bg-muted/20 px-6 py-4">
                        <SheetTitle>Catatan Dosen</SheetTitle>
                        <SheetDescription>
                            {notesRow?.name} ({notesRow?.nim})
                        </SheetDescription>
                        {notesRow?.mentorshipTicket?.suspended ? (
                            <StatusBadge
                                domain="ticket"
                                status="overdue"
                                className="mt-2"
                            >
                                Masa aktif habis
                            </StatusBadge>
                        ) : null}
                    </SheetHeader>
                    <div className="p-6">
                        <ThesisNotesPanel
                            projectId={notesProjectId(notesRow)}
                            data={notesFor(projectNotes, notesRow)}
                        />
                    </div>
                </SheetContent>
            </Sheet>
            <SupervisorDialog
                student={supervisorDialog}
                open={supervisorDialog !== null}
                onOpenChange={(open) => {
                    if (!open) setSupervisorDialog(null);
                }}
            />
        </div>
    );
}

function notesProjectId(row: DirectoryRow | null): number | null {
    if (row === null) {
        return null;
    }

    return row.projectId ?? (row.kind === 'archive' ? row.id : null);
}

function notesFor(
    projectNotes: Record<string, ProjectNotes>,
    row: DirectoryRow | null,
): ProjectNotes | undefined {
    const projectId = notesProjectId(row);

    return projectId === null ? undefined : projectNotes[projectId];
}

function SupervisorDialog({
    student,
    open,
    onOpenChange,
}: {
    student: StudentRow | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const primary = student?.supervisorAssignments.find((assignment) =>
        assignment.role.includes('1'),
    );
    const secondary = student?.supervisorAssignments.find((assignment) =>
        assignment.role.includes('2'),
    );
    const initialPrimaryId =
        primary?.lecturerUserId ??
        student?.discussionLecturer?.lecturerUserId ??
        null;
    const initialSecondaryId = secondary?.lecturerUserId ?? null;
    const form = useForm({
        primary_lecturer_user_id: initialPrimaryId
            ? String(initialPrimaryId)
            : '',
        secondary_lecturer_user_id: initialSecondaryId
            ? String(initialSecondaryId)
            : '',
        notes: '',
    });
    const { clearErrors, setData } = form;

    useEffect(() => {
        setData({
            primary_lecturer_user_id: initialPrimaryId
                ? String(initialPrimaryId)
                : '',
            secondary_lecturer_user_id: initialSecondaryId
                ? String(initialSecondaryId)
                : '',
            notes: '',
        });
        clearErrors();
    }, [
        clearErrors,
        initialPrimaryId,
        initialSecondaryId,
        setData,
        student?.projectId,
    ]);

    if (!student || student.projectId === null) return null;

    const activeStudent = student;
    const submitDisabled =
        form.processing ||
        form.data.primary_lecturer_user_id === '' ||
        form.data.secondary_lecturer_user_id === '' ||
        form.data.primary_lecturer_user_id ===
            form.data.secondary_lecturer_user_id;

    function submit() {
        form.post(`/kaprodi/projects/${activeStudent.projectId}/supervisors`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <div className="flex items-center gap-2">
                        <DialogTitle>Atur Pembimbing</DialogTitle>
                        <TooltipProvider delayDuration={100}>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <button
                                        type="button"
                                        aria-label="Panduan pemilihan pembimbing"
                                        className="inline-flex size-6 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        <CircleHelp className="size-4" />
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent className="max-w-xs text-xs leading-relaxed">
                                    Badge prodi, konsentrasi, dan bidang
                                    keilmuan adalah informasi pendukung, bukan
                                    syarat kesamaan. Dosen dengan kuota penuh
                                    tidak dapat dipilih.
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                    <DialogDescription>
                        Tetapkan pembimbing aktif untuk {activeStudent.name} (
                        {activeStudent.nim}).
                    </DialogDescription>
                    {activeStudent.isObeCurriculum && (
                        <StatusBadge domain="curriculum" status="obe">
                            OBE
                        </StatusBadge>
                    )}
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="rounded-lg border bg-muted/20 p-3">
                        <p className="text-xs font-medium text-muted-foreground">
                            Judul saat ini
                        </p>
                        <p className="mt-1 text-sm font-medium">
                            {activeStudent.title}
                        </p>
                    </div>

                    {activeStudent.mentorshipProgress.length > 0 ? (
                        <div className="rounded-lg border bg-muted/20 p-3">
                            <p className="mb-2 text-xs font-medium text-muted-foreground">
                                Bimbingan selesai per pembimbing
                            </p>
                            <MentorshipProgressList
                                progress={activeStudent.mentorshipProgress}
                            />
                        </div>
                    ) : null}

                    <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                        <LecturerSearchSelect
                            label="Pembimbing 1"
                            value={form.data.primary_lecturer_user_id}
                            onChange={(value) =>
                                form.setData('primary_lecturer_user_id', value)
                            }
                            projectId={activeStudent.projectId}
                            purpose="supervisor"
                            excludeIds={[form.data.secondary_lecturer_user_id]}
                            error={form.errors.primary_lecturer_user_id}
                        />
                        <LecturerSearchSelect
                            label="Pembimbing 2"
                            value={form.data.secondary_lecturer_user_id}
                            onChange={(value) =>
                                form.setData(
                                    'secondary_lecturer_user_id',
                                    value,
                                )
                            }
                            projectId={activeStudent.projectId}
                            purpose="supervisor"
                            excludeIds={[form.data.primary_lecturer_user_id]}
                            error={form.errors.secondary_lecturer_user_id}
                        />
                    </div>

                    {activeStudent.discussionLecturer && (
                        <p className="text-xs text-muted-foreground">
                            Dosen diskusi proposal:{' '}
                            {activeStudent.discussionLecturer.name}
                        </p>
                    )}

                    <div className="grid gap-1.5">
                        <label className="text-sm font-medium">Catatan</label>
                        <Textarea
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                            placeholder="Opsional, misalnya alasan pergantian pembimbing."
                        />
                    </div>
                    {(form.errors as Record<string, string>).project ? (
                        <p className="text-sm text-destructive">
                            {(form.errors as Record<string, string>).project}
                        </p>
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
                        Simpan Pembimbing
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function ProgressRiskBadge({
    risk,
    className,
}: {
    risk: StudentRow['progressRisk'];
    className?: string;
}) {
    const tone = { high: 'danger', medium: 'warning', low: 'success' } as const;

    return (
        <div
            className={cn(
                'flex max-w-48 flex-col gap-1 max-md:max-w-none max-md:flex-row max-md:flex-wrap max-md:items-center max-md:gap-x-2 max-md:gap-y-0.5',
                className,
            )}
        >
            <span className="text-xs text-muted-foreground md:hidden">
                Risiko
            </span>
            <Badge
                variant="outline"
                className={cn(
                    'rounded-full border-transparent',
                    toneClassName(tone[risk.level]),
                )}
            >
                {risk.label}
            </Badge>
            <p className="text-xs text-muted-foreground">
                {risk.lastActivityAt === '-'
                    ? risk.lastActivityLabel
                    : `${risk.lastActivityLabel} - ${risk.lastActivityAt}`}
            </p>
        </div>
    );
}
