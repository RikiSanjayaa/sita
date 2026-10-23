import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

import {
    DataSection,
    DataToolbar,
    dataTh,
    stackedTable,
} from '@/components/patterns/data-section';
import { FilterPills } from '@/components/patterns/filter-pills';
import { StatusBadge } from '@/components/patterns/status-badge';
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

type PageProps = {
    filters: {
        search: string;
    };
    upcomingSchedules: ScheduleItem[];
    upcomingPagination: PaginationData;
    followUpSchedules: ScheduleItem[];
    followUpPagination: PaginationData;
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
    onPageChange,
    onPageSizeChange,
    title,
}: {
    emptyMessage: string;
    items: ScheduleItem[];
    pagination: PaginationData;
    onPageChange: (page: number) => void;
    onPageSizeChange: (pageSize: number) => void;
    title: string;
}) {
    const [typeFilter, setTypeFilter] = useState<
        'semua' | ScheduleItem['type']
    >('semua');

    const filteredItems = useMemo(() => {
        if (typeFilter === 'semua') {
            return items;
        }

        return items.filter((item) => item.type === typeFilter);
    }, [items, typeFilter]);

    return (
        <DataSection title={title}>
            {items.length > 0 ? (
                <FilterPills
                    label={`Jenis ujian, ${title.toLowerCase()}`}
                    value={typeFilter}
                    onValueChange={setTypeFilter}
                    itemClassName="max-md:h-11 max-md:px-4"
                    options={[
                        { label: 'Semua', value: 'semua', count: items.length },
                        {
                            label: n.proposalExamShort,
                            value: 'sempro',
                            count: items.filter(
                                (item) => item.type === 'sempro',
                            ).length,
                        },
                        {
                            label: n.finalExam,
                            value: 'sidang',
                            count: items.filter(
                                (item) => item.type === 'sidang',
                            ).length,
                        },
                    ]}
                />
            ) : null}

            {filteredItems.length > 0 ? (
                <DataTableContainer
                    pagination={
                        <DataTablePagination
                            currentPage={pagination.currentPage}
                            totalPages={Math.max(
                                1,
                                pagination.currentPage +
                                    (pagination.hasMorePages ? 1 : 0),
                            )}
                            currentItemCount={filteredItems.length}
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
                            {filteredItems.map((item) => (
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
        </DataSection>
    );
}

export default function PublicSchedulesPage() {
    const {
        filters,
        upcomingSchedules,
        upcomingPagination,
        followUpSchedules,
        followUpPagination,
    } = usePage<SharedData & PageProps>().props;

    return (
        <PublicSchedulesContent
            key={filters.search}
            filters={filters}
            upcomingSchedules={upcomingSchedules}
            upcomingPagination={upcomingPagination}
            followUpSchedules={followUpSchedules}
            followUpPagination={followUpPagination}
        />
    );
}

function PublicSchedulesContent({
    filters,
    upcomingSchedules,
    upcomingPagination,
    followUpSchedules,
    followUpPagination,
}: PageProps) {
    const [search, setSearch] = useState(filters.search);

    useEffect(() => {
        const timeoutId = window.setTimeout(() => {
            if (search === filters.search) {
                return;
            }

            router.get(
                '/jadwal',
                {
                    search: search || undefined,
                    upcoming_per_page: upcomingPagination.perPage,
                    follow_up_per_page: followUpPagination.perPage,
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                },
            );
        }, 250);

        return () => window.clearTimeout(timeoutId);
    }, [filters.search, search]);

    function visitPage(key: 'upcoming_page' | 'follow_up_page', page: number) {
        router.get(
            '/jadwal',
            {
                search: filters.search || undefined,
                upcoming_per_page: upcomingPagination.perPage,
                follow_up_per_page: followUpPagination.perPage,
                [key]: page,
                ...(key === 'upcoming_page'
                    ? { follow_up_page: followUpPagination.currentPage }
                    : { upcoming_page: upcomingPagination.currentPage }),
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    }

    function changePageSize(
        key: 'upcoming_per_page' | 'follow_up_per_page',
        pageSize: number,
    ) {
        router.get(
            '/jadwal',
            {
                search: filters.search || undefined,
                upcoming_page:
                    key === 'upcoming_per_page'
                        ? 1
                        : upcomingPagination.currentPage,
                follow_up_page:
                    key === 'follow_up_per_page'
                        ? 1
                        : followUpPagination.currentPage,
                upcoming_per_page:
                    key === 'upcoming_per_page'
                        ? pageSize
                        : upcomingPagination.perPage,
                follow_up_per_page:
                    key === 'follow_up_per_page'
                        ? pageSize
                        : followUpPagination.perPage,
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    }

    const hasActiveSearch = filters.search.trim() !== '';

    return (
        <PublicLayout
            active="jadwal"
            headTitle={`Jadwal ${n.proposalExamShort} dan ${n.finalExam}`}
            pageTitle={`Jadwal ${n.proposalExamShort} dan ${n.finalExam}`}
            description="Agenda publik yang akan datang, serta seminar terbaru yang masih memerlukan tindak lanjut seperti revisi atau pelengkapan nilai."
        >
            <div className="space-y-8">
                <DataToolbar
                    search={search}
                    onSearchChange={setSearch}
                    placeholder="Cari mahasiswa, NIM, prodi, atau lokasi..."
                />
                <ScheduleTable
                    title="Jadwal Akan Datang"
                    items={upcomingSchedules}
                    pagination={upcomingPagination}
                    emptyMessage={
                        hasActiveSearch
                            ? 'Tidak ada jadwal mendatang yang cocok dengan pencarian ini.'
                            : 'Belum ada jadwal mendatang pada bagian ini.'
                    }
                    onPageChange={(page) => visitPage('upcoming_page', page)}
                    onPageSizeChange={(pageSize) =>
                        changePageSize('upcoming_per_page', pageSize)
                    }
                />
                <ScheduleTable
                    title="Tindak Lanjut Terbaru"
                    items={followUpSchedules}
                    pagination={followUpPagination}
                    emptyMessage={
                        hasActiveSearch
                            ? 'Tidak ada tindak lanjut yang cocok dengan pencarian ini.'
                            : 'Belum ada data pada bagian ini.'
                    }
                    onPageChange={(page) => visitPage('follow_up_page', page)}
                    onPageSizeChange={(pageSize) =>
                        changePageSize('follow_up_per_page', pageSize)
                    }
                />
            </div>
        </PublicLayout>
    );
}
