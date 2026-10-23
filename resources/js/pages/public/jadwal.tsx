import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import {
    DataToolbar,
    dataTh,
    stackedTable,
} from '@/components/patterns/data-section';
import { FilterPills } from '@/components/patterns/filter-pills';
import { PageHeader } from '@/components/patterns/page-header';
import { StatusBadge } from '@/components/patterns/status-badge';
import { StickyFilterBar } from '@/components/patterns/sticky-filter-bar';
import { PublicLayout } from '@/components/public/public-layout';
import { Badge } from '@/components/ui/badge';
import {
    DataTableContainer,
    DataTableEmptyState,
    DataTablePagination,
} from '@/components/ui/data-table';
import { neutralAcademicTerminology as n } from '@/lib/academic-terminology';
import { toneClassName, type StatusTone } from '@/lib/status-styles';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';

type ScheduleItem = {
    id: number;
    type: 'sempro' | 'sidang';
    typeLabel: string;
    studentName: string;
    studentNim: string;
    programStudi: string;
    scheduledFor: string | null;
    location: string;
    mode: string;
    statusLabel: string;
    statusTone: 'default' | 'warning' | 'danger' | 'muted';
    statusDetail: string | null;
};

type PaginationData = {
    currentPage: number;
    perPage: number;
    hasMorePages: boolean;
    nextPage: number | null;
    previousPage: number | null;
};

type ScheduleType = ScheduleItem['type'];

type TypeCounts = {
    semua: number;
    sempro: number;
    sidang: number;
};

type PageProps = {
    filters: {
        search: string;
        upcomingType: ScheduleType | '';
        followUpType: ScheduleType | '';
    };
    upcomingSchedules: ScheduleItem[];
    upcomingPagination: PaginationData;
    upcomingTypeCounts: TypeCounts;
    followUpSchedules: ScheduleItem[];
    followUpPagination: PaginationData;
    followUpTypeCounts: TypeCounts;
};

const scheduleToneStatus: Record<ScheduleItem['statusTone'], StatusTone> = {
    default: 'primary',
    warning: 'warning',
    danger: 'danger',
    muted: 'neutral',
};

function ScheduleBadges({ item }: { item: ScheduleItem }) {
    return (
        <div className="flex flex-wrap gap-2">
            <StatusBadge domain="phase" status={item.type}>
                {item.typeLabel}
            </StatusBadge>
            <Badge
                variant="outline"
                className={cn(
                    'rounded-full border-transparent',
                    toneClassName(scheduleToneStatus[item.statusTone]),
                )}
            >
                {item.statusLabel}
            </Badge>
        </div>
    );
}

function ScheduleTable({
    emptyMessage,
    items,
    pagination,
    typeFilter,
    typeCounts,
    onTypeChange,
    onPageChange,
    onPageSizeChange,
    title,
    search,
    onSearchChange,
}: {
    emptyMessage: string;
    items: ScheduleItem[];
    pagination: PaginationData;
    typeFilter: ScheduleType | '';
    typeCounts: TypeCounts;
    onTypeChange: (type: ScheduleType | '') => void;
    onPageChange: (page: number) => void;
    onPageSizeChange: (pageSize: number) => void;
    title: string;
    search: string;
    onSearchChange: (value: string) => void;
}) {
    return (
        <section aria-label={title} className="space-y-4">
            <StickyFilterBar layout="public">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:gap-6">
                    <PageHeader title={title} level={2} className="shrink-0" />
                    <div className="min-w-0 flex-1">
                        <DataToolbar
                            search={search}
                            onSearchChange={onSearchChange}
                            placeholder="Cari mahasiswa, NIM, prodi, atau lokasi..."
                        >
                            {typeCounts.semua > 0 ? (
                                <FilterPills
                                    label={`Jenis ujian, ${title.toLowerCase()}`}
                                    value={typeFilter || 'semua'}
                                    onValueChange={(value) =>
                                        onTypeChange(
                                            value === 'semua' ? '' : value,
                                        )
                                    }
                                    itemClassName="max-md:h-11 max-md:px-4"
                                    options={[
                                        {
                                            label: 'Semua',
                                            value: 'semua',
                                            count: typeCounts.semua,
                                        },
                                        {
                                            label: n.proposalExamShort,
                                            value: 'sempro',
                                            count: typeCounts.sempro,
                                        },
                                        {
                                            label: n.finalExam,
                                            value: 'sidang',
                                            count: typeCounts.sidang,
                                        },
                                    ]}
                                />
                            ) : null}
                        </DataToolbar>
                    </div>
                </div>
            </StickyFilterBar>

            {items.length > 0 ? (
                <DataTableContainer
                    pagination={
                        <DataTablePagination
                            currentPage={pagination.currentPage}
                            totalPages={Math.max(
                                1,
                                pagination.currentPage +
                                    (pagination.hasMorePages ? 1 : 0),
                            )}
                            currentItemCount={items.length}
                            pageSize={pagination.perPage}
                            onPageChange={onPageChange}
                            onPageSizeChange={onPageSizeChange}
                            itemLabel="jadwal"
                        />
                    }
                >
                    <table
                        className={cn(stackedTable.table, 'md:min-w-[760px]')}
                    >
                        <thead className={stackedTable.thead}>
                            <tr className="border-b bg-muted/40">
                                <th className={dataTh}>Jenis</th>
                                <th className={dataTh}>Waktu</th>
                                <th className={dataTh}>Mahasiswa</th>
                                <th className={dataTh}>Prodi</th>
                                <th className={dataTh}>Lokasi</th>
                            </tr>
                        </thead>
                        <tbody
                            className={cn(stackedTable.tbody, 'md:divide-y')}
                        >
                            {items.map((item) => (
                                <tr
                                    key={`${title}-${item.id}`}
                                    className={cn(stackedTable.tr, 'align-top')}
                                >
                                    <td className={stackedTable.tdPlain}>
                                        <ScheduleBadges item={item} />
                                    </td>
                                    <td
                                        className={cn(
                                            stackedTable.tdPlain,
                                            'text-muted-foreground',
                                        )}
                                    >
                                        {item.scheduledFor ?? '-'}
                                    </td>
                                    <td className={stackedTable.tdPlain}>
                                        <div className="font-medium text-foreground">
                                            {item.studentName}
                                        </div>
                                        <div className="text-muted-foreground">
                                            {item.studentNim}
                                        </div>
                                        {item.statusDetail ? (
                                            <div className="mt-1 max-w-xs text-xs leading-5 text-muted-foreground">
                                                {item.statusDetail}
                                            </div>
                                        ) : null}
                                    </td>
                                    <td
                                        className={cn(
                                            stackedTable.tdPlain,
                                            'text-muted-foreground',
                                        )}
                                    >
                                        {item.programStudi}
                                    </td>
                                    <td
                                        data-label="Lokasi"
                                        className={cn(
                                            stackedTable.td,
                                            'text-muted-foreground',
                                        )}
                                    >
                                        {item.location} ({item.mode})
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </DataTableContainer>
            ) : (
                <DataTableEmptyState
                    title="Tidak ada jadwal"
                    description={emptyMessage}
                />
            )}
        </section>
    );
}

export default function PublicSchedulesPage() {
    const {
        filters,
        upcomingSchedules,
        upcomingPagination,
        upcomingTypeCounts,
        followUpSchedules,
        followUpPagination,
        followUpTypeCounts,
    } = usePage<SharedData & PageProps>().props;

    return (
        <PublicSchedulesContent
            key={filters.search}
            filters={filters}
            upcomingSchedules={upcomingSchedules}
            upcomingPagination={upcomingPagination}
            upcomingTypeCounts={upcomingTypeCounts}
            followUpSchedules={followUpSchedules}
            followUpPagination={followUpPagination}
            followUpTypeCounts={followUpTypeCounts}
        />
    );
}

function PublicSchedulesContent({
    filters,
    upcomingSchedules,
    upcomingPagination,
    upcomingTypeCounts,
    followUpSchedules,
    followUpPagination,
    followUpTypeCounts,
}: PageProps) {
    const [search, setSearch] = useState(filters.search);

    type ScheduleQuery = {
        search: string;
        upcomingType: ScheduleType | '';
        followUpType: ScheduleType | '';
        upcomingPage: number;
        followUpPage: number;
        upcomingPerPage: number;
        followUpPerPage: number;
    };

    function visit(overrides: Partial<ScheduleQuery>) {
        const query: ScheduleQuery = {
            search: filters.search,
            upcomingType: filters.upcomingType,
            followUpType: filters.followUpType,
            upcomingPage: upcomingPagination.currentPage,
            followUpPage: followUpPagination.currentPage,
            upcomingPerPage: upcomingPagination.perPage,
            followUpPerPage: followUpPagination.perPage,
            ...overrides,
        };

        router.get(
            '/jadwal',
            {
                search: query.search || undefined,
                upcoming_type: query.upcomingType || undefined,
                follow_up_type: query.followUpType || undefined,
                upcoming_page: query.upcomingPage,
                follow_up_page: query.followUpPage,
                upcoming_per_page: query.upcomingPerPage,
                follow_up_per_page: query.followUpPerPage,
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    }

    useEffect(() => {
        const timeoutId = window.setTimeout(() => {
            if (search === filters.search) {
                return;
            }

            visit({ search, upcomingPage: 1, followUpPage: 1 });
        }, 250);

        return () => window.clearTimeout(timeoutId);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [filters.search, search]);

    const hasActiveSearch = filters.search.trim() !== '';

    return (
        <PublicLayout
            active="jadwal"
            headTitle={`Jadwal ${n.proposalExamShort} dan ${n.finalExam}`}
            pageTitle={`Jadwal ${n.proposalExamShort} dan ${n.finalExam}`}
            description="Agenda publik yang akan datang, serta seminar terbaru yang masih memerlukan tindak lanjut seperti revisi atau pelengkapan nilai."
        >
            <div className="space-y-8">
                <ScheduleTable
                    title="Jadwal Akan Datang"
                    search={search}
                    onSearchChange={setSearch}
                    items={upcomingSchedules}
                    pagination={upcomingPagination}
                    emptyMessage={
                        hasActiveSearch || filters.upcomingType !== ''
                            ? 'Tidak ada jadwal mendatang yang cocok dengan filter ini.'
                            : 'Belum ada jadwal mendatang pada bagian ini.'
                    }
                    typeFilter={filters.upcomingType}
                    typeCounts={upcomingTypeCounts}
                    onTypeChange={(upcomingType) =>
                        visit({ upcomingType, upcomingPage: 1 })
                    }
                    onPageChange={(upcomingPage) => visit({ upcomingPage })}
                    onPageSizeChange={(upcomingPerPage) =>
                        visit({ upcomingPerPage, upcomingPage: 1 })
                    }
                />
                <ScheduleTable
                    title="Tindak Lanjut Terbaru"
                    search={search}
                    onSearchChange={setSearch}
                    items={followUpSchedules}
                    pagination={followUpPagination}
                    emptyMessage={
                        hasActiveSearch || filters.followUpType !== ''
                            ? 'Tidak ada tindak lanjut yang cocok dengan filter ini.'
                            : 'Belum ada data pada bagian ini.'
                    }
                    typeFilter={filters.followUpType}
                    typeCounts={followUpTypeCounts}
                    onTypeChange={(followUpType) =>
                        visit({ followUpType, followUpPage: 1 })
                    }
                    onPageChange={(followUpPage) => visit({ followUpPage })}
                    onPageSizeChange={(followUpPerPage) =>
                        visit({ followUpPerPage, followUpPage: 1 })
                    }
                />
            </div>
        </PublicLayout>
    );
}
