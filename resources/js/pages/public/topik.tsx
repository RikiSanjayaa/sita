import { router, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { Fragment, useEffect, useMemo, useState } from 'react';

import {
    DataSection,
    DataSelect,
    DataToolbar,
    dataTh,
    stackedTable,
} from '@/components/patterns/data-section';
import { FilterPills } from '@/components/patterns/filter-pills';
import { StatusBadge } from '@/components/patterns/status-badge';
import { PublicLayout } from '@/components/public/public-layout';
import { Button } from '@/components/ui/button';
import {
    DataTableContainer,
    DataTableEmptyState,
    DataTablePagination,
} from '@/components/ui/data-table';
import { neutralAcademicTerminology as n } from '@/lib/academic-terminology';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';

type SemproTitleItem = {
    id: number;
    programStudi: string;
    programSlug: string;
    studentName: string;
    studentNim: string;
    title: string;
    titleEn: string;
    summary: string;
    year: string;
    state: string;
    stateLabel: string;
    seminarDate: string | null;
    sidangDate: string | null;
    proposalExamLabel: string;
    finalExamLabel: string;
    advisors: Array<{
        name: string;
        label: string;
    }>;
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
        program: string;
    };
    semproTitles: SemproTitleItem[];
    topicPagination: PaginationData;
    topicPrograms: Array<{
        slug: string;
        name: string;
    }>;
};

export default function PublicTopicsPage() {
    const { filters, semproTitles, topicPagination, topicPrograms } = usePage<
        SharedData & PageProps
    >().props;

    return (
        <PublicTopicsContent
            key={`${filters.search}:${filters.program}`}
            filters={filters}
            semproTitles={semproTitles}
            topicPagination={topicPagination}
            topicPrograms={topicPrograms}
        />
    );
}

function PublicTopicsContent({
    filters,
    semproTitles,
    topicPagination,
    topicPrograms,
}: PageProps) {
    const [search, setSearch] = useState(filters.search);
    const [programFilter, setProgramFilter] = useState(
        filters.program || 'semua',
    );
    const [yearFilter, setYearFilter] = useState('semua');
    const [openRowId, setOpenRowId] = useState<number | null>(null);

    useEffect(() => {
        const normalizedProgram =
            programFilter === 'semua' ? '' : programFilter;
        const timeoutId = window.setTimeout(() => {
            if (
                search === filters.search &&
                normalizedProgram === filters.program
            ) {
                return;
            }

            router.get(
                '/topik',
                {
                    search: search || undefined,
                    program: normalizedProgram || undefined,
                    per_page: topicPagination.perPage,
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                },
            );
        }, 250);

        return () => window.clearTimeout(timeoutId);
    }, [
        filters.program,
        filters.search,
        programFilter,
        search,
        topicPagination.perPage,
    ]);

    const yearTabs = useMemo(() => {
        const years = Array.from(
            new Set(semproTitles.map((item) => item.year)),
        );

        return [
            { label: 'Semua', value: 'semua', count: semproTitles.length },
            ...years.map((year) => ({
                label: year,
                value: year,
                count: semproTitles.filter((item) => item.year === year).length,
            })),
        ];
    }, [semproTitles]);

    const filteredTitles = useMemo(() => {
        if (yearFilter === 'semua') {
            return semproTitles;
        }

        return semproTitles.filter((item) => item.year === yearFilter);
    }, [semproTitles, yearFilter]);

    const hasActiveFilter =
        filters.search.trim() !== '' || filters.program.trim() !== '';

    function toggleRow(rowId: number) {
        setOpenRowId((current) => (current === rowId ? null : rowId));
    }

    function visitPage(page: number) {
        router.get(
            '/topik',
            {
                page,
                search: filters.search || undefined,
                program: filters.program || undefined,
                per_page: topicPagination.perPage,
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    }

    function changePageSize(pageSize: number) {
        router.get(
            '/topik',
            {
                page: 1,
                search: filters.search || undefined,
                program: filters.program || undefined,
                per_page: pageSize,
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    }

    return (
        <PublicLayout
            active="topik"
            headTitle="Topik Karya Akhir"
            pageTitle="Topik Karya Akhir"
            description={`Daftar topik karya akhir yang sudah menyelesaikan ${n.proposalExam.toLowerCase()} dan dapat ditelusuri berdasarkan program studi, judul, mahasiswa, atau kata kunci ringkasan.`}
        >
            <DataSection title="Daftar Topik">
                <DataToolbar
                    search={search}
                    onSearchChange={(value) => {
                        setSearch(value);
                        setOpenRowId(null);
                    }}
                    placeholder="Cari judul, ringkasan, mahasiswa, atau NIM..."
                >
                    <DataSelect
                        label="Program studi"
                        value={programFilter}
                        onValueChange={(value) => {
                            setProgramFilter(value);
                            setOpenRowId(null);
                        }}
                        placeholder="Filter prodi"
                        options={[
                            { value: 'semua', label: 'Semua Prodi' },
                            ...topicPrograms.map((program) => ({
                                value: program.slug,
                                label: program.name,
                            })),
                        ]}
                    />
                </DataToolbar>

                <FilterPills
                    label="Tahun topik"
                    value={yearFilter}
                    onValueChange={(value) => {
                        setYearFilter(value);
                        setOpenRowId(null);
                    }}
                    itemClassName="max-md:h-11 max-md:px-4"
                    options={yearTabs}
                />

                {filteredTitles.length > 0 ? (
                    <DataTableContainer
                        pagination={
                            <DataTablePagination
                                currentPage={topicPagination.currentPage}
                                totalPages={Math.max(
                                    1,
                                    topicPagination.currentPage +
                                        (topicPagination.hasMorePages ? 1 : 0),
                                )}
                                currentItemCount={filteredTitles.length}
                                pageSize={topicPagination.perPage}
                                onPageChange={visitPage}
                                onPageSizeChange={changePageSize}
                                itemLabel="topik"
                            />
                        }
                    >
                        <table
                            className={cn(
                                stackedTable.table,
                                'md:min-w-[920px]',
                            )}
                        >
                            <thead className={stackedTable.thead}>
                                <tr className="border-b bg-muted/40">
                                    <th className={cn(dataTh, 'w-14')}>
                                        Detail
                                    </th>
                                    <th className={dataTh}>Mahasiswa</th>
                                    <th className={dataTh}>Judul</th>
                                    <th className={dataTh}>Prodi</th>
                                    <th className={dataTh}>Tahun</th>
                                    <th className={dataTh}>Status</th>
                                </tr>
                            </thead>
                            <tbody
                                className={cn(
                                    stackedTable.tbody,
                                    'md:divide-y',
                                )}
                            >
                                {filteredTitles.map((item) => {
                                    const isOpen = openRowId === item.id;

                                    return (
                                        <Fragment key={item.id}>
                                            <tr
                                                className={cn(
                                                    stackedTable.tr,
                                                    'align-top transition-colors hover:bg-muted/40 max-md:relative max-md:flex max-md:flex-wrap max-md:items-center max-md:gap-x-3 max-md:pr-14',
                                                    isOpen && 'bg-muted/40',
                                                )}
                                            >
                                                <td className="px-4 py-3 max-md:absolute max-md:top-1 max-md:right-1 max-md:p-0">
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="ghost"
                                                        className="size-11 rounded-full md:size-9"
                                                        onClick={() =>
                                                            toggleRow(item.id)
                                                        }
                                                        aria-label="Buka detail topik"
                                                        aria-expanded={isOpen}
                                                    >
                                                        <ChevronRight
                                                            className={cn(
                                                                'size-4 transition-transform',
                                                                isOpen &&
                                                                    'rotate-90',
                                                            )}
                                                        />
                                                    </Button>
                                                </td>
                                                <td
                                                    className={cn(
                                                        stackedTable.tdPlain,
                                                        'max-md:order-2 max-md:basis-full',
                                                    )}
                                                >
                                                    <div className="font-medium text-foreground">
                                                        {item.studentName}
                                                    </div>
                                                    <div className="text-muted-foreground">
                                                        {item.studentNim}
                                                    </div>
                                                </td>
                                                <td
                                                    className={cn(
                                                        stackedTable.tdPlain,
                                                        'max-md:order-1 max-md:basis-full',
                                                    )}
                                                >
                                                    <div className="max-w-md leading-6 font-medium text-foreground">
                                                        {item.title}
                                                    </div>
                                                </td>
                                                <td
                                                    className={cn(
                                                        stackedTable.tdPlain,
                                                        'text-muted-foreground max-md:order-3',
                                                    )}
                                                >
                                                    {item.programStudi}
                                                </td>
                                                <td
                                                    className={cn(
                                                        stackedTable.tdPlain,
                                                        'text-muted-foreground tabular-nums max-md:order-4',
                                                    )}
                                                >
                                                    {item.year}
                                                </td>
                                                <td
                                                    className={cn(
                                                        stackedTable.tdPlain,
                                                        'max-md:order-5',
                                                    )}
                                                >
                                                    <StatusBadge
                                                        domain="state"
                                                        status={item.state}
                                                    >
                                                        {item.stateLabel}
                                                    </StatusBadge>
                                                </td>
                                            </tr>

                                            {isOpen ? (
                                                <tr className="bg-muted/40 max-md:block">
                                                    <td
                                                        colSpan={6}
                                                        className="px-4 pt-1 pb-5 max-md:block"
                                                    >
                                                        <div className="grid gap-6 md:ml-14 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.9fr)]">
                                                            <div className="space-y-4">
                                                                <div className="space-y-1">
                                                                    <p className="text-xs font-medium text-muted-foreground">
                                                                        Ringkasan{' '}
                                                                        {
                                                                            n.proposalDocument
                                                                        }
                                                                    </p>
                                                                    <p className="max-w-3xl text-sm leading-6">
                                                                        {item.summary ||
                                                                            '-'}
                                                                    </p>
                                                                </div>
                                                                <div className="space-y-1">
                                                                    <p className="text-xs font-medium text-muted-foreground">
                                                                        Judul
                                                                        Internasional
                                                                    </p>
                                                                    <p className="text-sm italic">
                                                                        {item.titleEn ||
                                                                            '-'}
                                                                    </p>
                                                                </div>
                                                            </div>

                                                            <dl className="space-y-4 text-sm">
                                                                <div className="space-y-1">
                                                                    <dt className="text-xs font-medium text-muted-foreground">
                                                                        Pembimbing
                                                                    </dt>
                                                                    <dd>
                                                                        {item
                                                                            .advisors
                                                                            .length >
                                                                        0 ? (
                                                                            <ul className="space-y-0.5">
                                                                                {item.advisors.map(
                                                                                    (
                                                                                        advisor,
                                                                                    ) => (
                                                                                        <li
                                                                                            key={`${item.id}-${advisor.label}`}
                                                                                        >
                                                                                            <span className="text-muted-foreground">
                                                                                                {
                                                                                                    advisor.label
                                                                                                }

                                                                                                :
                                                                                            </span>{' '}
                                                                                            {
                                                                                                advisor.name
                                                                                            }
                                                                                        </li>
                                                                                    ),
                                                                                )}
                                                                            </ul>
                                                                        ) : (
                                                                            <span className="text-muted-foreground">
                                                                                Belum
                                                                                ada
                                                                                data
                                                                                pembimbing.
                                                                            </span>
                                                                        )}
                                                                    </dd>
                                                                </div>
                                                                <div className="space-y-1">
                                                                    <dt className="text-xs font-medium text-muted-foreground">
                                                                        {`${item.proposalExamLabel} selesai`}
                                                                    </dt>
                                                                    <dd>
                                                                        {item.seminarDate ||
                                                                            '-'}
                                                                    </dd>
                                                                </div>
                                                                <div className="space-y-1">
                                                                    <dt className="text-xs font-medium text-muted-foreground">
                                                                        {`${item.finalExamLabel} selesai`}
                                                                    </dt>
                                                                    <dd>
                                                                        {item.sidangDate ||
                                                                            '-'}
                                                                    </dd>
                                                                </div>
                                                            </dl>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ) : null}
                                        </Fragment>
                                    );
                                })}
                            </tbody>
                        </table>
                    </DataTableContainer>
                ) : (
                    <DataTableEmptyState
                        title="Tidak ada topik"
                        description={
                            hasActiveFilter || yearFilter !== 'semua'
                                ? 'Tidak ada topik yang cocok dengan pencarian ini.'
                                : 'Belum ada topik yang dapat ditampilkan.'
                        }
                    />
                )}
            </DataSection>
        </PublicLayout>
    );
}
