import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ChevronRight,
    FileArchive,
    Search,
    UserRound,
} from 'lucide-react';
import { useMemo, useState } from 'react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { useInitials } from '@/hooks/use-initials';
import KaprodiLayout from '@/layouts/kaprodi-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';

const PAGE_SIZE = 10;

type ProgramStudi = {
    name: string;
};

type StudentRow = {
    id: number;
    name: string;
    nim: string;
    avatar: string | null;
    status: string;
    angkatan: string | null;
    concentration: string | null;
    phase: string;
    phaseKey: string;
    projectState: string;
    projectStateKey: string;
    title: string;
    advisors: string[];
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
    title: string;
    state: string;
    phase: string;
    completedAt: string;
    documentCount: number;
    defenseCount: number;
    profileUrl: string;
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
    | 'none';

type RiskFilter = 'semua' | 'high' | 'medium' | 'low';

type ArchiveFilter = 'semua' | 'Selesai' | 'Dibatalkan';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/kaprodi/dashboard' },
    { title: 'Mahasiswa Prodi', href: '/kaprodi/mahasiswa' },
];

export default function KaprodiMahasiswaPage() {
    const { programStudi, filters, students, archives } = usePage<
        SharedData & MahasiswaProps
    >().props;

    const activeRows = students.filter(
        (student) =>
            student.status === 'Aktif' &&
            !['completed', 'cancelled'].includes(student.projectStateKey),
    );

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
                            Mahasiswa Aktif
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Memantau{' '}
                            <span className="font-semibold text-foreground">
                                {activeRows.length}
                            </span>{' '}
                            mahasiswa aktif di {programStudi.name}
                        </p>
                    </div>
                    <ActiveStudentTable
                        rows={activeRows}
                        filters={filters}
                        emptyText="Belum ada mahasiswa aktif"
                    />
                </section>

                <section>
                    <div className="mb-4 border-b pb-3">
                        <h2 className="text-base font-semibold">Arsip Prodi</h2>
                        <p className="text-sm text-muted-foreground">
                            Proyek selesai atau dibatalkan, tetap bisa dibuka
                            dari profil mahasiswa.
                        </p>
                    </div>
                    <ArchiveTable rows={archives} />
                </section>
            </div>
        </KaprodiLayout>
    );
}

function ActiveStudentTable({
    rows,
    filters,
    emptyText,
}: {
    rows: StudentRow[];
    filters: MahasiswaProps['filters'];
    emptyText: string;
}) {
    const getInitials = useInitials();
    const [search, setSearch] = useState('');
    const [phaseFilter, setPhaseFilter] = useState<ActivePhaseFilter>('semua');
    const [riskFilter, setRiskFilter] = useState<RiskFilter>('semua');
    const [angkatanFilter, setAngkatanFilter] = useState('semua');
    const [concentrationFilter, setConcentrationFilter] = useState('semua');
    const [page, setPage] = useState(1);

    const phaseOptions: { label: string; value: ActivePhaseFilter }[] = [
        { label: 'Review Judul', value: 'title_review' },
        { label: 'Sempro', value: 'sempro' },
        { label: 'Penelitian', value: 'research' },
        { label: 'Sidang', value: 'sidang' },
        { label: 'Belum Ada Proyek', value: 'none' },
    ];

    const riskOptions: { label: string; value: RiskFilter }[] = [
        { label: 'Risiko Telat', value: 'high' },
        { label: 'Perlu Dipantau', value: 'medium' },
        { label: 'Terkendali', value: 'low' },
    ];

    const filtered = useMemo(() => {
        const query = search.trim().toLowerCase();

        return rows.filter((row) => {
            const matchesSearch =
                !query ||
                [
                    row.name,
                    row.nim,
                    row.phase,
                    row.title,
                    row.concentration ?? '',
                    row.progressRisk.label,
                    row.progressRisk.description,
                    row.progressRisk.lastActivityLabel,
                    ...row.progressRisk.signals,
                    ...row.advisors,
                ]
                    .join(' ')
                    .toLowerCase()
                    .includes(query);

            return (
                matchesSearch &&
                (phaseFilter === 'semua' || row.phaseKey === phaseFilter) &&
                (riskFilter === 'semua' ||
                    row.progressRisk.level === riskFilter) &&
                (angkatanFilter === 'semua' ||
                    String(row.angkatan ?? '') === angkatanFilter) &&
                (concentrationFilter === 'semua' ||
                    row.concentration === concentrationFilter)
            );
        });
    }, [
        angkatanFilter,
        concentrationFilter,
        phaseFilter,
        riskFilter,
        rows,
        search,
    ]);

    const safePage = Math.min(
        page,
        Math.max(1, Math.ceil(filtered.length / PAGE_SIZE)),
    );
    const paginated = filtered.slice(
        (safePage - 1) * PAGE_SIZE,
        safePage * PAGE_SIZE,
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
                <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
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
                                    key={row.id}
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
                                            {row.angkatan ?? '-'}
                                        </p>
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            {row.concentration ?? '-'}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-col items-start gap-1.5">
                                            <Badge>{row.phase}</Badge>
                                            <ProgressRiskBadge
                                                risk={row.progressRisk}
                                                className="lg:hidden"
                                            />
                                        </div>
                                    </td>
                                    <td className="hidden px-4 py-3 lg:table-cell">
                                        <ProgressRiskBadge
                                            risk={row.progressRisk}
                                        />
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
                                        <Link href={row.profileUrl}>
                                            <ChevronRight className="size-4" />
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <PaginationFooter
                        page={safePage}
                        total={filtered.length}
                        onPageChange={setPage}
                    />
                </div>
            ) : (
                <EmptyBlock icon={AlertCircle} text={emptyText} />
            )}
        </div>
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

function ArchiveTable({ rows }: { rows: ArchiveRow[] }) {
    const [search, setSearch] = useState('');
    const [stateFilter, setStateFilter] = useState<ArchiveFilter>('semua');
    const [page, setPage] = useState(1);

    const filtered = useMemo(() => {
        const query = search.trim().toLowerCase();

        return rows.filter((row) => {
            const matchesSearch =
                !query ||
                [row.student, row.nim ?? '', row.title, row.phase, row.state]
                    .join(' ')
                    .toLowerCase()
                    .includes(query);

            return (
                matchesSearch &&
                (stateFilter === 'semua' || row.state === stateFilter)
            );
        });
    }, [rows, search, stateFilter]);

    const safePage = Math.min(
        page,
        Math.max(1, Math.ceil(filtered.length / PAGE_SIZE)),
    );
    const paginated = filtered.slice(
        (safePage - 1) * PAGE_SIZE,
        safePage * PAGE_SIZE,
    );

    return (
        <div className="space-y-3">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div className="relative max-w-xs flex-1">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(event) => {
                            setSearch(event.target.value);
                            setPage(1);
                        }}
                        placeholder="Cari mahasiswa atau judul..."
                        className="h-8 pl-8 text-sm"
                    />
                </div>
                <div className="flex gap-1">
                    {(
                        ['semua', 'Selesai', 'Dibatalkan'] as ArchiveFilter[]
                    ).map((value) => (
                        <button
                            key={value}
                            type="button"
                            onClick={() => {
                                setStateFilter(value);
                                setPage(1);
                            }}
                            className={cn(
                                'rounded-full px-3 py-1 text-xs font-medium transition-colors',
                                stateFilter === value
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground',
                            )}
                        >
                            {value === 'semua' ? 'Semua' : value}
                        </button>
                    ))}
                </div>
            </div>

            {paginated.length > 0 ? (
                <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
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
                                    Status
                                </th>
                                <th className="hidden px-4 py-2.5 text-left text-xs font-medium text-muted-foreground lg:table-cell">
                                    Dokumen & Ujian
                                </th>
                                <th className="hidden px-4 py-2.5 text-left text-xs font-medium text-muted-foreground xl:table-cell">
                                    Tanggal
                                </th>
                                <th className="w-8 px-4 py-2.5" />
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {paginated.map((row) => (
                                <tr
                                    key={row.id}
                                    className="transition-colors hover:bg-muted/20"
                                >
                                    <td className="px-4 py-3">
                                        <Link href={row.profileUrl}>
                                            <p className="leading-snug font-medium">
                                                {row.student}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {row.nim ?? '-'}
                                            </p>
                                        </Link>
                                    </td>
                                    <td className="hidden max-w-[320px] px-4 py-3 md:table-cell">
                                        <p className="line-clamp-2 text-xs leading-relaxed">
                                            {row.title}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1.5">
                                            <Badge
                                                variant={
                                                    row.state === 'Selesai'
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                            >
                                                {row.state}
                                            </Badge>
                                            <Badge variant="outline">
                                                {row.phase}
                                            </Badge>
                                        </div>
                                    </td>
                                    <td className="hidden px-4 py-3 lg:table-cell">
                                        <p className="text-xs text-muted-foreground">
                                            {row.documentCount} dokumen -{' '}
                                            {row.defenseCount} ujian
                                        </p>
                                    </td>
                                    <td className="hidden px-4 py-3 text-xs text-muted-foreground xl:table-cell">
                                        {row.completedAt}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        <Link href={row.profileUrl}>
                                            <ChevronRight className="size-4" />
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <PaginationFooter
                        page={safePage}
                        total={filtered.length}
                        onPageChange={setPage}
                    />
                </div>
            ) : (
                <EmptyBlock icon={FileArchive} text="Belum ada arsip proyek" />
            )}
        </div>
    );
}

function PaginationFooter({
    page,
    total,
    onPageChange,
}: {
    page: number;
    total: number;
    onPageChange: (page: number) => void;
}) {
    const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));

    return (
        <div className="flex items-center justify-between border-t px-4 py-2.5">
            <p className="text-xs text-muted-foreground">
                {total === 0
                    ? 'Tidak ada item'
                    : `${(page - 1) * PAGE_SIZE + 1}-${Math.min(page * PAGE_SIZE, total)} dari ${total} item`}
            </p>
            {totalPages > 1 ? (
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        disabled={page <= 1}
                        onClick={() => onPageChange(Math.max(1, page - 1))}
                        className="rounded px-2 py-1 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-40"
                    >
                        Prev
                    </button>
                    <span className="text-xs text-muted-foreground">
                        Hal {page} / {totalPages}
                    </span>
                    <button
                        type="button"
                        disabled={page >= totalPages}
                        onClick={() =>
                            onPageChange(Math.min(totalPages, page + 1))
                        }
                        className="rounded px-2 py-1 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-40"
                    >
                        Next
                    </button>
                </div>
            ) : null}
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
