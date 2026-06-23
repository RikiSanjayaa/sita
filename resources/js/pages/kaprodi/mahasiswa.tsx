import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    CalendarClock,
    ChevronRight,
    CircleHelp,
    FileText,
    Search,
    UserCog,
    UserRound,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { LecturerSearchSelect } from '@/components/lecturer-search-select';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DataTableContainer,
    DataTablePagination,
} from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useInitials } from '@/hooks/use-initials';
import { useUrlState } from '@/hooks/use-url-state';
import KaprodiLayout from '@/layouts/kaprodi-layout';
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
    phase: string;
    phaseKey: string;
    projectState: string;
    projectStateKey: string;
    title: string;
    advisors: string[];
    supervisorAssignments: {
        lecturerUserId: number;
        name: string;
        role: string;
    }[];
    progressRisk: {
        level: 'high' | 'medium' | 'low';
        label: string;
        description: string;
        lastActivityAt: string;
        lastActivityLabel: string;
        daysIdle: number | null;
        signals: string[];
    };
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
    phase: string;
    phaseKey: ActivePhaseFilter;
    title: string;
    advisors: string[];
    progressRisk: StudentRow['progressRisk'] | null;
    profileUrl: string;
    completedAt: string | null;
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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/kaprodi/dashboard' },
    { title: 'Mahasiswa Prodi', href: '/kaprodi/mahasiswa' },
];

function searchUrl(path: string, value: string) {
    return `${path}?search=${encodeURIComponent(value)}`;
}

export default function KaprodiMahasiswaPage() {
    const { auth } = usePage<SharedData>().props;
    const { programStudi, filters, students, archives } = usePage<
        SharedData & MahasiswaProps
    >().props;
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
            subtitle={`Daftar mahasiswa dan arsip proyek di ${programStudi.name}`}
        >
            <Head title="Mahasiswa Prodi" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-10 px-4 py-6 md:px-6 lg:py-8">
                <section>
                    <div className="mb-4 border-b pb-3">
                        <h2 className="text-base font-semibold">
                            Daftar Mahasiswa
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Memantau{' '}
                            <span className="font-semibold text-foreground">
                                {totalRows}
                            </span>{' '}
                            mahasiswa dan arsip proyek di {programStudi.name}
                        </p>
                    </div>
                    <StudentTable
                        rows={activeRows}
                        archives={archives}
                        filters={filters}
                        canManageSupervisors={canManageSupervisors}
                        emptyText="Belum ada mahasiswa atau arsip proyek"
                    />
                </section>
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
    const getInitials = useInitials();
    const [search, setSearch] = useUrlState('activeSearch', '');
    const [phaseFilter, setPhaseFilter] = useUrlState<ActivePhaseFilter>(
        'activePhase',
        'semua',
    );
    const [riskFilter, setRiskFilter] = useUrlState<RiskFilter>(
        'activeRisk',
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
    const [pageSize, setPageSize] = useUrlState('activePageSize', PAGE_SIZE);
    const [supervisorDialog, setSupervisorDialog] = useState<StudentRow | null>(
        null,
    );

    const phaseOptions: { label: string; value: ActivePhaseFilter }[] = [
        { label: 'Review Judul', value: 'title_review' },
        { label: 'Proposal', value: 'sempro' },
        { label: 'Penelitian', value: 'research' },
        { label: 'Ujian Akhir', value: 'sidang' },
        { label: 'Selesai', value: 'completed' },
        { label: 'Dibatalkan', value: 'cancelled' },
        { label: 'Belum Ada Proyek', value: 'none' },
    ];

    const riskOptions: { label: string; value: RiskFilter }[] = [
        { label: 'Risiko Telat', value: 'high' },
        { label: 'Perlu Dipantau', value: 'medium' },
        { label: 'Terkendali', value: 'low' },
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
                phase: row.phase,
                phaseKey: row.phaseKey as ActivePhaseFilter,
                title: row.title,
                advisors: row.advisors,
                progressRisk: row.progressRisk,
                profileUrl: row.profileUrl,
                completedAt: null,
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
                phase: row.state,
                phaseKey: isCancelled ? 'cancelled' : 'completed',
                title: row.title,
                advisors: [],
                progressRisk: null,
                profileUrl: row.profileUrl,
                completedAt: row.completedAt,
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

    return (
        <div className="space-y-3">
            <div className="flex flex-col gap-2 xl:flex-row xl:items-center xl:justify-between">
                <div className="relative max-w-xs flex-1">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(event) => {
                            setSearch(event.target.value);
                            resetPage();
                        }}
                        placeholder="Cari nama atau NIM..."
                        className="h-8 pl-8 text-sm"
                    />
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <FilterSelect
                        value={phaseFilter}
                        onChange={(value) => {
                            setPhaseFilter(value as ActivePhaseFilter);
                            resetPage();
                        }}
                        options={phaseOptions}
                        placeholder="Semua Tahap"
                    />
                    <FilterSelect
                        value={riskFilter}
                        onChange={(value) => {
                            setRiskFilter(value as RiskFilter);
                            resetPage();
                        }}
                        options={riskOptions}
                        placeholder="Semua Risiko"
                    />
                    <FilterSelect
                        value={angkatanFilter}
                        onChange={(value) => {
                            setAngkatanFilter(value);
                            resetPage();
                        }}
                        options={filters.angkatan}
                        placeholder="Semua Angkatan"
                    />
                    <FilterSelect
                        value={concentrationFilter}
                        onChange={(value) => {
                            setConcentrationFilter(value);
                            resetPage();
                        }}
                        options={filters.concentrations}
                        placeholder="Semua Konsentrasi"
                    />
                    {riskFilter !== 'semua' ||
                    angkatanFilter !== 'semua' ||
                    concentrationFilter !== 'semua' ||
                    phaseFilter !== 'semua' ? (
                        <button
                            type="button"
                            onClick={() => {
                                setPhaseFilter('semua');
                                setRiskFilter('semua');
                                setAngkatanFilter('semua');
                                setConcentrationFilter('semua');
                                resetPage();
                            }}
                            className="rounded-full px-3 py-1 text-xs font-medium text-muted-foreground transition-colors hover:bg-background hover:text-foreground"
                        >
                            Reset filter
                        </button>
                    ) : null}
                </div>
            </div>

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
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/30">
                                <th className="px-4 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                    Mahasiswa
                                </th>
                                <th className="hidden px-4 py-2.5 text-left text-xs font-medium text-muted-foreground md:table-cell">
                                    Angkatan
                                </th>
                                <th className="px-4 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                    Tahap
                                </th>
                                <th className="hidden px-4 py-2.5 text-left text-xs font-medium text-muted-foreground lg:table-cell">
                                    Risiko
                                </th>
                                <th className="hidden px-4 py-2.5 text-left text-xs font-medium text-muted-foreground xl:table-cell">
                                    Judul
                                </th>
                                <th className="hidden px-4 py-2.5 text-left text-xs font-medium text-muted-foreground 2xl:table-cell">
                                    Pembimbing
                                </th>
                                <th className="w-8 px-4 py-2.5" />
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {paginated.map((row) => (
                                <tr
                                    key={`${row.kind}-${row.id}`}
                                    className="transition-colors hover:bg-muted/20"
                                >
                                    <td className="px-4 py-3">
                                        <Link
                                            href={row.profileUrl}
                                            className="flex items-center gap-2.5"
                                        >
                                            <Avatar className="size-7 shrink-0 border">
                                                <AvatarImage
                                                    src={
                                                        row.avatar ?? undefined
                                                    }
                                                    alt={row.name}
                                                />
                                                <AvatarFallback className="bg-primary/10 text-[10px] text-primary">
                                                    {getInitials(row.name)}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div className="min-w-0">
                                                <p className="truncate leading-snug font-medium">
                                                    {row.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {row.nim}
                                                </p>
                                            </div>
                                        </Link>
                                    </td>
                                    <td className="hidden px-4 py-3 md:table-cell">
                                        <p className="text-xs font-medium">
                                            {row.degreeLevel} ·{' '}
                                            {row.angkatan ?? '-'}
                                        </p>
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            {row.concentration ?? '-'}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-col items-start gap-1.5">
                                            <Badge
                                                variant={
                                                    row.kind === 'archive' &&
                                                    row.phaseKey === 'cancelled'
                                                        ? 'outline'
                                                        : 'default'
                                                }
                                            >
                                                {row.phase}
                                            </Badge>
                                            {row.completedAt ? (
                                                <span className="text-xs text-muted-foreground">
                                                    {row.completedAt}
                                                </span>
                                            ) : null}
                                            {row.progressRisk ? (
                                                <ProgressRiskBadge
                                                    risk={row.progressRisk}
                                                    className="lg:hidden"
                                                />
                                            ) : null}
                                        </div>
                                    </td>
                                    <td className="hidden px-4 py-3 lg:table-cell">
                                        {row.progressRisk ? (
                                            <ProgressRiskBadge
                                                risk={row.progressRisk}
                                            />
                                        ) : (
                                            <span className="text-xs text-muted-foreground">
                                                -
                                            </span>
                                        )}
                                    </td>
                                    <td className="hidden max-w-[260px] px-4 py-3 xl:table-cell">
                                        <p className="line-clamp-2 text-xs leading-relaxed">
                                            {row.title}
                                        </p>
                                    </td>
                                    <td className="hidden px-4 py-3 2xl:table-cell">
                                        <p className="max-w-xs truncate text-xs text-muted-foreground">
                                            {row.advisors.length > 0
                                                ? row.advisors.join(', ')
                                                : '-'}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        <div className="flex items-center justify-end gap-2">
                                            <Link
                                                href={searchUrl(
                                                    '/kaprodi/dokumen',
                                                    row.nim,
                                                )}
                                                title="Lihat dokumen mahasiswa"
                                                className="rounded-md p-1.5 transition-colors hover:bg-muted hover:text-foreground"
                                            >
                                                <FileText className="size-4" />
                                            </Link>
                                            <Link
                                                href={searchUrl(
                                                    '/kaprodi/sempro-sidang',
                                                    row.nim,
                                                )}
                                                title="Lihat jadwal sempro dan sidang"
                                                className="rounded-md p-1.5 transition-colors hover:bg-muted hover:text-foreground"
                                            >
                                                <CalendarClock className="size-4" />
                                            </Link>
                                            {canManageSupervisors &&
                                            row.canManageSupervisors &&
                                            row.projectId !== null &&
                                            row.activeStudent ? (
                                                <button
                                                    type="button"
                                                    title="Atur pembimbing"
                                                    onClick={() =>
                                                        setSupervisorDialog(
                                                            row.activeStudent,
                                                        )
                                                    }
                                                    className="rounded-md p-1.5 transition-colors hover:bg-muted hover:text-foreground"
                                                >
                                                    <UserCog className="size-4" />
                                                </button>
                                            ) : null}
                                            <Link
                                                href={row.profileUrl}
                                                title="Buka profil mahasiswa"
                                                className="rounded-md p-1.5 transition-colors hover:bg-muted hover:text-foreground"
                                            >
                                                <ChevronRight className="size-4" />
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </DataTableContainer>
            ) : (
                <EmptyBlock icon={AlertCircle} text={emptyText} />
            )}
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
    const form = useForm({
        primary_lecturer_user_id: primary?.lecturerUserId
            ? String(primary.lecturerUserId)
            : '',
        secondary_lecturer_user_id: secondary?.lecturerUserId
            ? String(secondary.lecturerUserId)
            : '',
        notes: '',
    });
    const { clearErrors, setData } = form;

    useEffect(() => {
        setData({
            primary_lecturer_user_id: primary?.lecturerUserId
                ? String(primary.lecturerUserId)
                : '',
            secondary_lecturer_user_id: secondary?.lecturerUserId
                ? String(secondary.lecturerUserId)
                : '',
            notes: '',
        });
        clearErrors();
    }, [
        clearErrors,
        primary?.lecturerUserId,
        secondary?.lecturerUserId,
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
            <DialogContent className="sm:max-w-2xl">
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
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="rounded-lg border bg-muted/20 p-3">
                        <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                            Judul Saat Ini
                        </p>
                        <p className="mt-1 text-sm font-medium">
                            {activeStudent.title}
                        </p>
                    </div>

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
    return (
        <div className={cn('flex min-w-[150px] flex-col gap-1', className)}>
            <Badge
                variant="outline"
                className={cn(
                    'w-fit',
                    risk.level === 'high' &&
                        'border-red-200 bg-red-50 text-red-700',
                    risk.level === 'medium' &&
                        'border-amber-200 bg-amber-50 text-amber-700',
                    risk.level === 'low' &&
                        'border-emerald-200 bg-emerald-50 text-emerald-700',
                )}
            >
                {risk.label}
            </Badge>
            <p className="text-xs text-muted-foreground">
                {risk.lastActivityLabel} - {risk.lastActivityAt}
            </p>
        </div>
    );
}

function EmptyBlock({
    icon: Icon,
    text,
}: {
    icon: typeof UserRound;
    text: string;
}) {
    return (
        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-10 text-center">
            <Icon className="mb-2 size-8 text-muted-foreground/40" />
            <p className="text-sm text-muted-foreground">{text}</p>
        </div>
    );
}

function FilterSelect({
    value,
    onChange,
    options,
    placeholder,
}: {
    value: string;
    onChange: (value: string) => void;
    options: (string | number | { label: string; value: string | number })[];
    placeholder: string;
}) {
    const normalizedOptions = options.map((option) =>
        typeof option === 'string' || typeof option === 'number'
            ? { label: String(option), value: String(option) }
            : {
                  label: option.label,
                  value: String(option.value),
              },
    );

    return (
        <select
            value={value}
            onChange={(event) => onChange(event.target.value)}
            className="h-7 rounded-full border border-transparent bg-muted px-3 text-xs font-medium text-muted-foreground transition-colors outline-none hover:bg-muted/80 hover:text-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
        >
            <option value="semua">{placeholder}</option>
            {normalizedOptions.map((option) => (
                <option key={option.value} value={option.value}>
                    {option.label}
                </option>
            ))}
        </select>
    );
}
