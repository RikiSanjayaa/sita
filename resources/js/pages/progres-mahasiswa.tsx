import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import {
    DataSection,
    DataSelect,
    DataToolbar,
    dataTh,
    stackedTable,
} from '@/components/patterns/data-section';
import { FilterPills } from '@/components/patterns/filter-pills';
import { PageHeader } from '@/components/patterns/page-header';
import { StatusBadge } from '@/components/patterns/status-badge';
import { StickyFilterBar } from '@/components/patterns/sticky-filter-bar';
import {
    StudentCohort,
    StudentIdentity,
    StudentTitleAdvisors,
} from '@/components/patterns/student-cells';
import { type StudentProgress } from '@/components/profile/student-progress-peek';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DataTableContainer,
    DataTableEmptyState,
    DataTablePagination,
} from '@/components/ui/data-table';
import { useResponsiveServerPagination } from '@/hooks/use-responsive-server-pagination';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { progresMahasiswa } from '@/routes';
import { type BreadcrumbItem, type SharedData } from '@/types';

type ProgressMoment = {
    kind: string;
    label: string;
    at: string;
    relative: string;
};

type ProgressRow = {
    id: number;
    isSelf: boolean;
    projectId: number | null;
    name: string;
    nim: string | null;
    avatar: string | null;
    profileUrl: string;
    prodi: string | null;
    degreeLevel: string;
    angkatan: number | null;
    concentration: string | null;
    isObeCurriculum: boolean;
    phaseKey: string;
    phaseLabel: string;
    title: string | null;
    progress: StudentProgress | null;
    moment: ProgressMoment | null;
};

type AgendaItem = {
    id: number;
    when: string;
    type: string;
    name: string;
    prodi: string | null;
};

type Filters = {
    search: string | null;
    prodi: number | null;
    phase: string | null;
    angkatan: number | null;
    perPage: number;
};

type PageProps = {
    filters: Filters;
    filterOptions: {
        prodi: Array<{ value: number; label: string }>;
        angkatan: Array<{ value: number; label: string }>;
    };
    phases: Array<{ value: string; label: string; count: number }>;
    rows: ProgressRow[];
    pagination: {
        currentPage: number;
        lastPage: number;
        total: number;
        perPage: number;
    };
    agenda: AgendaItem[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Progress Seluruh Mahasiswa', href: progresMahasiswa().url },
];

export default function ProgresMahasiswaPage() {
    const props = usePage<SharedData & PageProps>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Progress Seluruh Mahasiswa" />
            <ProgresMahasiswaContent {...props} />
        </AppLayout>
    );
}

function ProgresMahasiswaContent({
    filters,
    filterOptions,
    phases,
    rows,
    pagination,
    agenda,
}: PageProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const selectPageSize = useResponsiveServerPagination([
        {
            key: 'per_page',
            pageKey: 'page',
            value: pagination.perPage,
            desktop: 15,
        },
    ]);

    function visit(next: Partial<Filters> & { page?: number }): void {
        const merged = { ...filters, ...next };

        router.get(
            progresMahasiswa().url,
            {
                search: merged.search || undefined,
                prodi: merged.prodi ?? undefined,
                phase:
                    merged.phase && merged.phase !== 'all'
                        ? merged.phase
                        : undefined,
                angkatan: merged.angkatan ?? undefined,
                per_page: merged.perPage,
                page: next.page,
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    }

    // Debounce keys on `search` only. `visit` is rebuilt each render and must not re-fire the request.
    useEffect(() => {
        const timeoutId = window.setTimeout(() => {
            if (search.trim() === (filters.search ?? '').trim()) {
                return;
            }

            visit({ search: search.trim() || null });
        }, 250);

        return () => window.clearTimeout(timeoutId);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const hasActiveFilter =
        filters.prodi !== null ||
        filters.phase !== null ||
        filters.angkatan !== null;
    const hasAnyFilter = hasActiveFilter || (filters.search ?? '') !== '';

    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-6 lg:py-8">
            <PageHeader
                title="Progress Seluruh Mahasiswa"
                description="Daftar progres lintas mahasiswa, bukan halaman progres pribadi. Baris Anda didahulukan jika sesuai filter; mahasiswa lain diurutkan dari progres terbaru. Halaman ini hanya untuk melihat."
            />

            <AgendaStrip agenda={agenda} />

            <DataSection
                title="Daftar Mahasiswa"
                summary={`${pagination.total} mahasiswa`}
            >
                <div className="space-y-4">
                    <StickyFilterBar
                        onApplyFilters={(values) =>
                            visit({
                                prodi:
                                    values['Program studi'] === 'semua'
                                        ? null
                                        : Number(values['Program studi']),
                                angkatan:
                                    values.Angkatan === 'semua'
                                        ? null
                                        : Number(values.Angkatan),
                                phase:
                                    values['Tahap mahasiswa'] === 'all'
                                        ? null
                                        : values['Tahap mahasiswa'],
                                page: undefined,
                            })
                        }
                    >
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            placeholder="Cari nama, NIM, atau prodi..."
                        >
                            <DataSelect
                                label="Program studi"
                                value={
                                    filters.prodi !== null
                                        ? String(filters.prodi)
                                        : 'semua'
                                }
                                onValueChange={(value) =>
                                    visit({
                                        prodi:
                                            value === 'semua'
                                                ? null
                                                : Number(value),
                                        page: undefined,
                                    })
                                }
                                placeholder="Semua prodi"
                                options={[
                                    { value: 'semua', label: 'Semua prodi' },
                                    ...filterOptions.prodi.map((option) => ({
                                        value: String(option.value),
                                        label: option.label,
                                    })),
                                ]}
                                className="md:w-56"
                            />
                            <DataSelect
                                label="Angkatan"
                                value={
                                    filters.angkatan !== null
                                        ? String(filters.angkatan)
                                        : 'semua'
                                }
                                onValueChange={(value) =>
                                    visit({
                                        angkatan:
                                            value === 'semua'
                                                ? null
                                                : Number(value),
                                        page: undefined,
                                    })
                                }
                                placeholder="Semua angkatan"
                                className="md:w-40"
                                options={[
                                    {
                                        value: 'semua',
                                        label: 'Semua angkatan',
                                    },
                                    ...filterOptions.angkatan.map((option) => ({
                                        value: String(option.value),
                                        label: option.label,
                                    })),
                                ]}
                            />
                        </DataToolbar>

                        <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                            <FilterPills
                                label="Tahap mahasiswa"
                                value={filters.phase ?? 'all'}
                                onValueChange={(value) =>
                                    visit({ phase: value, page: undefined })
                                }
                                itemClassName="max-md:h-11 max-md:px-4"
                                options={phases}
                            />
                            {hasActiveFilter ? (
                                <Button
                                    type="button"
                                    data-desktop-filter-reset
                                    variant="ghost"
                                    size="sm"
                                    className="text-muted-foreground max-md:h-11"
                                    onClick={() =>
                                        visit({
                                            prodi: null,
                                            phase: null,
                                            angkatan: null,
                                            page: undefined,
                                        })
                                    }
                                >
                                    Reset filter
                                </Button>
                            ) : null}
                        </div>
                    </StickyFilterBar>

                    {rows.length > 0 ? (
                        <DataTableContainer
                            pagination={
                                <DataTablePagination
                                    currentPage={pagination.currentPage}
                                    totalPages={pagination.lastPage}
                                    totalItems={pagination.total}
                                    pageSize={pagination.perPage}
                                    onPageChange={(page) => visit({ page })}
                                    onPageSizeChange={(size) =>
                                        selectPageSize('per_page', size)
                                    }
                                    itemLabel="mahasiswa"
                                />
                            }
                        >
                            <table
                                className={cn(
                                    stackedTable.table,
                                    'md:min-w-[880px]',
                                )}
                            >
                                <thead className={stackedTable.thead}>
                                    <tr className="border-b bg-muted/40">
                                        <th className={dataTh}>Mahasiswa</th>
                                        <th className={dataTh}>
                                            Prodi dan angkatan
                                        </th>
                                        <th className={dataTh}>Tahap</th>
                                        <th className={dataTh}>
                                            Aktivitas terakhir
                                        </th>
                                        <th className={dataTh}>
                                            Judul dan pembimbing
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    className={cn(
                                        stackedTable.tbody,
                                        'md:divide-y',
                                    )}
                                >
                                    {rows.map((row) => (
                                        <ProgressTableRow
                                            key={row.id}
                                            row={row}
                                        />
                                    ))}
                                </tbody>
                            </table>
                        </DataTableContainer>
                    ) : (
                        <DataTableEmptyState
                            title="Tidak ada mahasiswa yang cocok"
                            description={
                                hasAnyFilter
                                    ? 'Coba ubah filter atau kata kunci pencarian.'
                                    : 'Belum ada mahasiswa yang dapat ditampilkan.'
                            }
                        />
                    )}
                </div>
            </DataSection>
        </div>
    );
}

function ProgressTableRow({ row }: { row: ProgressRow }) {
    const detail = row.progress?.detail ?? null;

    return (
        <tr
            className={cn(
                stackedTable.tr,
                'align-top transition-colors',
                row.isSelf
                    ? 'bg-primary-muted/60 hover:bg-primary-muted'
                    : 'hover:bg-muted/30',
            )}
        >
            <td className={stackedTable.tdPlain}>
                <div className="flex flex-wrap items-center gap-2">
                    <StudentIdentity
                        name={row.name}
                        nim={row.nim}
                        avatar={row.avatar}
                        profileUrl={row.profileUrl}
                    />
                    {row.isSelf ? <Badge variant="soft">Anda</Badge> : null}
                </div>
            </td>
            <td className={stackedTable.td}>
                <StudentCohort
                    degreeLevel={row.degreeLevel}
                    angkatan={row.angkatan}
                    concentration={row.concentration}
                    isObeCurriculum={row.isObeCurriculum}
                    prodi={row.prodi}
                />
            </td>
            <td className={stackedTable.tdDetail}>
                <div className="flex flex-wrap items-center gap-2">
                    <StatusBadge domain="phase" status={row.phaseKey}>
                        {row.phaseLabel}
                    </StatusBadge>
                    {detail?.ticket.suspended ? (
                        <StatusBadge domain="ticket" status="overdue">
                            Masa aktif habis
                        </StatusBadge>
                    ) : null}
                </div>
                {detail?.statusLabel ? (
                    <p
                        className="mt-1 text-xs text-pretty text-muted-foreground max-md:line-clamp-2"
                        title={detail.statusLabel}
                    >
                        {detail.statusLabel}
                    </p>
                ) : null}
                {row.moment &&
                !detail?.statusLabel?.startsWith(row.moment.label) ? (
                    <p className="mt-1 text-xs text-pretty text-muted-foreground">
                        {row.moment.label} · {row.moment.relative}
                    </p>
                ) : null}
            </td>
            <td
                data-label="Aktivitas terakhir"
                data-label-hidden
                className={stackedTable.td}
            >
                {row.progress ? (
                    <p
                        className="tabular-nums"
                        title={detail?.lastActivityAt ?? undefined}
                    >
                        {detail?.lastActivityLabel ?? 'Belum ada aktivitas'}
                    </p>
                ) : (
                    <span className="text-muted-foreground">-</span>
                )}
            </td>
            <td className={cn(stackedTable.tdDetail, 'md:max-w-xs')}>
                <StudentTitleAdvisors
                    title={row.title}
                    advisors={
                        detail?.supervisors.map(
                            (supervisor) => supervisor.name,
                        ) ?? []
                    }
                />
            </td>
        </tr>
    );
}

/** Upcoming sempro and sidang, kept to a few short lines so the table stays the focus. */
function AgendaStrip({ agenda }: { agenda: AgendaItem[] }) {
    return (
        <section
            aria-labelledby="agenda-heading"
            className="rounded-lg border bg-card px-4 py-3"
        >
            <h2 id="agenda-heading" className="text-sm font-semibold">
                Agenda sempro dan sidang
            </h2>
            {agenda.length > 0 ? (
                <ol className="mt-2 grid gap-x-6 gap-y-1 text-sm md:grid-cols-2">
                    {agenda.map((item) => (
                        <li key={item.id} className="min-w-0 md:truncate">
                            <span className="text-muted-foreground tabular-nums">
                                {item.when}
                            </span>{' '}
                            <span className="font-medium">
                                {item.type}: {item.name}
                            </span>
                            {item.prodi ? (
                                <span className="text-muted-foreground">
                                    {' '}
                                    · {item.prodi}
                                </span>
                            ) : null}
                        </li>
                    ))}
                </ol>
            ) : (
                <p className="mt-1 text-sm text-muted-foreground">
                    Belum ada agenda sempro atau sidang yang dijadwalkan.
                </p>
            )}
        </section>
    );
}
