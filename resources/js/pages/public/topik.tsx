import { router, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { Fragment, useEffect, useState } from 'react';

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
import { PublicLayout } from '@/components/public/public-layout';
import { TopicDetailsRow } from '@/components/public/topic-details-row';
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
        year: string;
    };
    semproTitles: SemproTitleItem[];
    topicPagination: PaginationData;
    topicTotal: number;
    topicYearCounts: Array<{
        year: string;
        count: number;
    }>;
    topicPrograms: Array<{
        slug: string;
        name: string;
    }>;
};

export default function PublicTopicsPage() {
    const {
        filters,
        semproTitles,
        topicPagination,
        topicTotal,
        topicYearCounts,
        topicPrograms,
    } = usePage<SharedData & PageProps>().props;

    return (
        <PublicTopicsContent
            key={`${filters.search}:${filters.program}`}
            filters={filters}
            semproTitles={semproTitles}
            topicPagination={topicPagination}
            topicTotal={topicTotal}
            topicYearCounts={topicYearCounts}
            topicPrograms={topicPrograms}
        />
    );
}

function PublicTopicsContent({
    filters,
    semproTitles,
    topicPagination,
    topicTotal,
    topicYearCounts,
    topicPrograms,
}: PageProps) {
    const [search, setSearch] = useState(filters.search);
    const [programFilter, setProgramFilter] = useState(
        filters.program || 'semua',
    );
    const [openRowId, setOpenRowId] = useState<number | null>(null);
    // True only while the latest action is a pointer/touch disclosure toggle.
    const [animateDetails, setAnimateDetails] = useState(false);

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
                    year: filters.year || undefined,
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
        filters.year,
        programFilter,
        search,
        topicPagination.perPage,
    ]);

    const yearTabs = [
        { label: 'Semua', value: 'semua', count: topicTotal },
        ...topicYearCounts.map((item) => ({
            label: item.year,
            value: item.year,
            count: item.count,
        })),
    ];

    const hasActiveFilter =
        filters.search.trim() !== '' ||
        filters.program.trim() !== '' ||
        filters.year.trim() !== '';

    function toggleRow(rowId: number, animate: boolean) {
        setAnimateDetails(animate);
        setOpenRowId((current) => (current === rowId ? null : rowId));
    }

    function closeDetails() {
        setAnimateDetails(false);
        setOpenRowId(null);
    }

    function changeYear(value: string) {
        closeDetails();
        router.get(
            '/topik',
            {
                search: filters.search || undefined,
                program: filters.program || undefined,
                year: value === 'semua' ? undefined : value,
                per_page: topicPagination.perPage,
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    }

    function visitPage(page: number) {
        closeDetails();
        router.get(
            '/topik',
            {
                page,
                search: filters.search || undefined,
                program: filters.program || undefined,
                year: filters.year || undefined,
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
        closeDetails();
        router.get(
            '/topik',
            {
                page: 1,
                search: filters.search || undefined,
                program: filters.program || undefined,
                year: filters.year || undefined,
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
                <StickyFilterBar layout="public">
                    <DataToolbar
                        search={search}
                        onSearchChange={(value) => {
                            setSearch(value);
                            closeDetails();
                        }}
                        placeholder="Cari judul, ringkasan, mahasiswa, atau NIM..."
                    >
                        <DataSelect
                            label="Program studi"
                            value={programFilter}
                            onValueChange={(value) => {
                                setProgramFilter(value);
                                closeDetails();
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
                        value={filters.year || 'semua'}
                        onValueChange={changeYear}
                        itemClassName="max-md:h-11 max-md:px-4"
                        options={yearTabs}
                    />
                </StickyFilterBar>

                {semproTitles.length > 0 ? (
                    <DataTableContainer
                        pagination={
                            <DataTablePagination
                                currentPage={topicPagination.currentPage}
                                totalPages={Math.max(
                                    1,
                                    topicPagination.currentPage +
                                        (topicPagination.hasMorePages ? 1 : 0),
                                )}
                                currentItemCount={semproTitles.length}
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
                                {semproTitles.map((item) => {
                                    const isOpen = openRowId === item.id;
                                    const detailsId = `public-topic-details-${item.id}`;

                                    return (
                                        <Fragment key={item.id}>
                                            <tr
                                                className={cn(
                                                    stackedTable.tr,
                                                    'cursor-pointer align-top transition-colors hover:bg-muted/40 max-md:relative max-md:flex max-md:flex-wrap max-md:items-center max-md:gap-x-3 max-md:pr-14',
                                                    isOpen && 'bg-muted/40',
                                                )}
                                                // The whole row toggles for pointer users; the
                                                // chevron button stays the keyboard/AT control.
                                                onClick={(event) => {
                                                    if (
                                                        (
                                                            event.target as HTMLElement
                                                        ).closest(
                                                            'button, a',
                                                        ) ||
                                                        window
                                                            .getSelection()
                                                            ?.toString()
                                                    ) {
                                                        return;
                                                    }

                                                    toggleRow(item.id, true);
                                                }}
                                            >
                                                <td className="px-4 py-3 max-md:absolute max-md:top-1 max-md:right-1 max-md:p-0">
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="ghost"
                                                        className="public-topic-trigger size-11 rounded-full md:size-9"
                                                        // detail === 0 is a keyboard-generated click.
                                                        onClick={(event) =>
                                                            toggleRow(
                                                                item.id,
                                                                event.detail >
                                                                    0,
                                                            )
                                                        }
                                                        aria-label="Buka detail topik"
                                                        aria-expanded={isOpen}
                                                        aria-controls={
                                                            detailsId
                                                        }
                                                        data-motion={
                                                            animateDetails
                                                                ? 'on'
                                                                : 'off'
                                                        }
                                                    >
                                                        <ChevronRight
                                                            className="public-topic-chevron size-4"
                                                            data-open={isOpen}
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

                                            <TopicDetailsRow
                                                id={detailsId}
                                                open={isOpen}
                                                animate={animateDetails}
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
                                                                {item.advisors
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
                                                                        ada data
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
                                            </TopicDetailsRow>
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
                            hasActiveFilter
                                ? 'Tidak ada topik yang cocok dengan pencarian ini.'
                                : 'Belum ada topik yang dapat ditampilkan.'
                        }
                    />
                )}
            </DataSection>
        </PublicLayout>
    );
}
