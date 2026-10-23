import { Head, Link, router, usePage } from '@inertiajs/react';
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
import { SectionCard } from '@/components/patterns/section-card';
import { StatusBadge } from '@/components/patterns/status-badge';
import {
    StudentProgressPeek,
    SupervisorCounts,
    type StudentProgress,
} from '@/components/profile/student-progress-peek';
import { Button } from '@/components/ui/button';
import {
    DataTableContainer,
    DataTableEmptyState,
    DataTablePagination,
} from '@/components/ui/data-table';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import AppLayout from '@/layouts/app-layout';
import { progresMahasiswa } from '@/routes';
import { type BreadcrumbItem, type SharedData } from '@/types';

type ProgressRow = {
    id: number;
    name: string;
    nim: string | null;
    profileUrl: string;
    prodi: string | null;
    angkatan: number | null;
    title: string | null;
    progress: StudentProgress;
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
    { title: 'Progres Mahasiswa', href: progresMahasiswa().url },
];

export default function ProgresMahasiswaPage() {
    const props = usePage<SharedData & PageProps>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Progres Mahasiswa" />
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
    const [selected, setSelected] = useState<ProgressRow | null>(null);

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

    const phaseOptions = [
        {
            value: 'all',
            label: 'Semua',
            count: phases.find((phase) => phase.value === 'all')?.count,
        },
        ...phases.filter((phase) => phase.value !== 'all'),
    ];
    const totalCount = pagination.total;
    const hasActiveFilter =
        (filters.search ?? '') !== '' ||
        filters.prodi !== null ||
        filters.phase !== null ||
        filters.angkatan !== null;

    return (
        <div className="space-y-6 p-4 md:p-6">
            <PageHeader
                title="Progres Mahasiswa"
                description="Pantau tahap, pembimbing, dan aktivitas terakhir seluruh mahasiswa aktif. Halaman ini hanya untuk melihat, tanpa tindakan."
            />

            <SectionCard
                title="Agenda sempro dan sidang"
                description="Jadwal terdekat yang masih akan berlangsung."
            >
                {agenda.length > 0 ? (
                    <ol className="space-y-4">
                        {agenda.map((item) => (
                            <li key={item.id} className="space-y-0.5">
                                <div className="text-xs text-muted-foreground tabular-nums">
                                    {item.when}
                                </div>
                                <div className="font-medium">
                                    {item.type}: {item.name}
                                </div>
                                {item.prodi ? (
                                    <div className="text-sm text-muted-foreground">
                                        {item.prodi}
                                    </div>
                                ) : null}
                            </li>
                        ))}
                    </ol>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Belum ada agenda sempro atau sidang yang dijadwalkan.
                    </p>
                )}
            </SectionCard>
            <DataSection
                title="Daftar mahasiswa"
                summary={`${totalCount} mahasiswa`}
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
                                prodi: value === 'semua' ? null : Number(value),
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
                                    value === 'semua' ? null : Number(value),
                                page: undefined,
                            })
                        }
                        placeholder="Semua angkatan"
                        className="md:w-44"
                        options={[
                            { value: 'semua', label: 'Semua angkatan' },
                            ...filterOptions.angkatan.map((option) => ({
                                value: String(option.value),
                                label: option.label,
                            })),
                        ]}
                    />
                </DataToolbar>

                <FilterPills
                    label="Tahap mahasiswa"
                    value={filters.phase ?? 'all'}
                    onValueChange={(value) =>
                        visit({ phase: value, page: undefined })
                    }
                    itemClassName="max-md:h-11 max-md:px-4"
                    options={phaseOptions}
                />

                {rows.length > 0 ? (
                    <DataTableContainer
                        pagination={
                            <DataTablePagination
                                currentPage={pagination.currentPage}
                                totalPages={pagination.lastPage}
                                totalItems={pagination.total}
                                pageSize={pagination.perPage}
                                onPageChange={(page) => visit({ page })}
                                onPageSizeChange={(perPage) =>
                                    visit({ perPage, page: undefined })
                                }
                                itemLabel="mahasiswa"
                            />
                        }
                    >
                        <table
                            className={stackedTable.table + ' md:min-w-[900px]'}
                        >
                            <thead className={stackedTable.thead}>
                                <tr className="border-b bg-muted/40">
                                    <th className={dataTh}>Mahasiswa</th>
                                    <th className={dataTh}>Prodi</th>
                                    <th className={dataTh}>Tahap</th>
                                    <th className={dataTh}>Pembimbing</th>
                                    <th className={dataTh}>
                                        Aktivitas terakhir
                                    </th>
                                    <th className={dataTh}>
                                        <span className="sr-only">Detail</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody className={stackedTable.tbody}>
                                {rows.map((row) => (
                                    <tr
                                        key={row.id}
                                        className={stackedTable.tr}
                                    >
                                        <td className={stackedTable.tdPlain}>
                                            <Link
                                                href={row.profileUrl}
                                                className="font-medium text-foreground underline-offset-4 hover:underline"
                                            >
                                                {row.name}
                                            </Link>
                                            <div className="text-muted-foreground">
                                                {row.nim ?? '-'}
                                            </div>
                                        </td>
                                        <td
                                            className={
                                                stackedTable.tdPlain +
                                                ' text-muted-foreground'
                                            }
                                        >
                                            {row.prodi ?? '-'}
                                            {row.angkatan ? (
                                                <div className="tabular-nums">
                                                    Angkatan {row.angkatan}
                                                </div>
                                            ) : null}
                                        </td>
                                        <td className={stackedTable.tdPlain}>
                                            <div className="font-medium">
                                                {row.progress.phaseLabel}
                                            </div>
                                            {row.progress.detail
                                                ?.statusLabel ? (
                                                <p className="mt-1 max-w-xs text-xs leading-5 text-muted-foreground">
                                                    {
                                                        row.progress.detail
                                                            .statusLabel
                                                    }
                                                </p>
                                            ) : null}
                                        </td>
                                        <td className={stackedTable.tdPlain}>
                                            <SupervisorCounts
                                                supervisors={
                                                    row.progress.detail
                                                        ?.supervisors ?? []
                                                }
                                            />
                                        </td>
                                        <td className={stackedTable.tdPlain}>
                                            <div className="tabular-nums">
                                                {row.progress.detail
                                                    ?.lastActivityAt ??
                                                    'Belum ada'}
                                            </div>
                                            {row.progress.detail?.isInactive ? (
                                                <div className="mt-1">
                                                    <StatusBadge
                                                        domain="active"
                                                        status="inactive"
                                                    >
                                                        Tidak aktif
                                                    </StatusBadge>
                                                </div>
                                            ) : null}
                                        </td>
                                        <td className={stackedTable.tdPlain}>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="h-11 md:h-9"
                                                onClick={() => setSelected(row)}
                                            >
                                                Detail
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </DataTableContainer>
                ) : (
                    <DataTableEmptyState
                        title="Tidak ada mahasiswa"
                        description={
                            hasActiveFilter
                                ? 'Tidak ada mahasiswa aktif yang cocok dengan filter ini.'
                                : 'Belum ada mahasiswa aktif yang dapat ditampilkan.'
                        }
                    />
                )}
            </DataSection>

            <Sheet
                open={selected !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelected(null);
                    }
                }}
            >
                <SheetContent
                    side="right"
                    className="w-full overflow-y-auto sm:max-w-md"
                >
                    {selected ? (
                        <>
                            <SheetHeader>
                                <SheetTitle>{selected.name}</SheetTitle>
                                <SheetDescription>
                                    {[selected.nim, selected.prodi]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </SheetDescription>
                            </SheetHeader>
                            <div className="space-y-5 px-4 pb-6">
                                <div className="space-y-1">
                                    <h3 className="text-sm font-medium text-muted-foreground">
                                        Judul
                                    </h3>
                                    <p>
                                        {selected.title ??
                                            'Judul belum dapat ditampilkan.'}
                                    </p>
                                </div>
                                <StudentProgressPeek
                                    progress={selected.progress}
                                />
                                <Button
                                    asChild
                                    variant="outline"
                                    className="h-11 w-full"
                                >
                                    <Link href={selected.profileUrl}>
                                        Buka profil
                                    </Link>
                                </Button>
                            </div>
                        </>
                    ) : null}
                </SheetContent>
            </Sheet>
        </div>
    );
}
