import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import {
    DataSection,
    DataSelect,
    DataToolbar,
    dataTh,
    stackedTable,
} from '@/components/patterns/data-section';
import { FilterPills } from '@/components/patterns/filter-pills';
import { StickyFilterBar } from '@/components/patterns/sticky-filter-bar';
import { PublicLayout } from '@/components/public/public-layout';
import { Badge } from '@/components/ui/badge';
import {
    DataTableContainer,
    DataTableEmptyState,
    DataTablePagination,
} from '@/components/ui/data-table';
import { neutralAcademicTerminology as n } from '@/lib/academic-terminology';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';

type StudentRow = {
    id: number;
    name: string;
    nim: string;
    programStudi: string;
    programSlug: string;
    stage: string;
    stageLabel: string;
    stageDescription: string;
    advisors: Array<{
        name: string;
        label: string;
    }>;
};

type PageProps = {
    filters: {
        search: string;
        program: string;
        stage: string;
    };
    activeStudents: StudentRow[];
    studentPagination: {
        currentPage: number;
        perPage: number;
        total: number;
        lastPage: number;
        hasMorePages: boolean;
        nextPage: number | null;
        previousPage: number | null;
    };
    studentTotal: number;
    stageCounts: Array<{
        value: string;
        label: string;
        count: number;
    }>;
    studentPrograms: Array<{
        slug: string;
        name: string;
    }>;
};

export default function PublicStudentsPage() {
    const {
        filters,
        activeStudents,
        studentPagination,
        studentTotal,
        stageCounts,
        studentPrograms,
    } = usePage<SharedData & PageProps>().props;

    return (
        <PublicStudentsContent
            key={`${filters.search}:${filters.program}`}
            filters={filters}
            activeStudents={activeStudents}
            studentPagination={studentPagination}
            studentTotal={studentTotal}
            stageCounts={stageCounts}
            studentPrograms={studentPrograms}
        />
    );
}

function PublicStudentsContent({
    filters,
    activeStudents,
    studentPagination,
    studentTotal,
    stageCounts,
    studentPrograms,
}: PageProps) {
    const [search, setSearch] = useState(filters.search);
    const [programFilter, setProgramFilter] = useState(
        filters.program || 'semua',
    );

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
                '/mahasiswa-aktif',
                {
                    search: search || undefined,
                    program: normalizedProgram || undefined,
                    stage: filters.stage || undefined,
                    per_page: studentPagination.perPage,
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
        filters.stage,
        programFilter,
        search,
        studentPagination.perPage,
    ]);

    const stageTabs = [
        { label: 'Semua', value: 'semua', count: studentTotal },
        ...stageCounts,
    ];

    function changeStage(value: string) {
        router.get(
            '/mahasiswa-aktif',
            {
                search: filters.search || undefined,
                program: filters.program || undefined,
                stage: value === 'semua' ? undefined : value,
                per_page: studentPagination.perPage,
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    }

    function visitPage(page: number) {
        router.get(
            '/mahasiswa-aktif',
            {
                page,
                search: filters.search || undefined,
                program: filters.program || undefined,
                stage: filters.stage || undefined,
                per_page: studentPagination.perPage,
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
            '/mahasiswa-aktif',
            {
                page: 1,
                search: filters.search || undefined,
                program: filters.program || undefined,
                stage: filters.stage || undefined,
                per_page: pageSize,
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    }

    const hasActiveFilter =
        filters.search.trim() !== '' ||
        filters.program.trim() !== '' ||
        filters.stage.trim() !== '';

    return (
        <PublicLayout
            active="mahasiswa"
            headTitle="Mahasiswa Aktif"
            pageTitle="Mahasiswa Karya Akhir Aktif"
            description={`Daftar mahasiswa yang masih aktif menjalani proses karya akhir, termasuk mahasiswa baru terdaftar, ${n.proposalExam.toLowerCase()}, bimbingan aktif, hingga ${n.finalExam.toLowerCase()} yang masih berjalan.`}
        >
            <DataSection title="Daftar Mahasiswa">
                <StickyFilterBar layout="public">
                    <DataToolbar
                        search={search}
                        onSearchChange={setSearch}
                        placeholder="Cari nama, NIM, atau prodi..."
                    >
                        <DataSelect
                            label="Program studi"
                            value={programFilter}
                            onValueChange={setProgramFilter}
                            placeholder="Filter prodi"
                            options={[
                                { value: 'semua', label: 'Semua Prodi' },
                                ...studentPrograms.map((program) => ({
                                    value: program.slug,
                                    label: program.name,
                                })),
                            ]}
                        />
                    </DataToolbar>

                    <FilterPills
                        label="Tahap mahasiswa"
                        value={filters.stage || 'semua'}
                        onValueChange={changeStage}
                        itemClassName="max-md:h-11 max-md:px-4"
                        options={stageTabs}
                    />
                </StickyFilterBar>

                {activeStudents.length > 0 ? (
                    <DataTableContainer
                        pagination={
                            <DataTablePagination
                                currentPage={studentPagination.currentPage}
                                totalPages={studentPagination.lastPage}
                                totalItems={studentPagination.total}
                                pageSize={studentPagination.perPage}
                                onPageChange={visitPage}
                                onPageSizeChange={changePageSize}
                                itemLabel="mahasiswa"
                            />
                        }
                    >
                        <table
                            className={cn(
                                stackedTable.table,
                                'md:min-w-[860px]',
                            )}
                        >
                            <thead className={stackedTable.thead}>
                                <tr className="border-b bg-muted/40">
                                    <th className={dataTh}>Mahasiswa</th>
                                    <th className={dataTh}>Prodi</th>
                                    <th className={dataTh}>Tahap</th>
                                    <th className={dataTh}>Pembimbing Aktif</th>
                                </tr>
                            </thead>
                            <tbody
                                className={cn(
                                    stackedTable.tbody,
                                    'md:divide-y',
                                )}
                            >
                                {activeStudents.map((student) => (
                                    <tr
                                        key={student.id}
                                        className={cn(
                                            stackedTable.tr,
                                            'align-top',
                                        )}
                                    >
                                        <td className={stackedTable.tdPlain}>
                                            <div className="font-medium text-foreground">
                                                {student.name}
                                            </div>
                                            <div className="text-muted-foreground">
                                                {student.nim}
                                            </div>
                                        </td>
                                        <td
                                            className={cn(
                                                stackedTable.tdPlain,
                                                'text-muted-foreground',
                                            )}
                                        >
                                            {student.programStudi}
                                        </td>
                                        <td className={stackedTable.tdPlain}>
                                            <Badge variant="outline">
                                                {student.stageLabel}
                                            </Badge>
                                            <p className="mt-1.5 max-w-xs text-xs leading-5 text-muted-foreground">
                                                {student.stageDescription}
                                            </p>
                                        </td>
                                        <td className={stackedTable.tdPlain}>
                                            {student.advisors.length > 0 ? (
                                                <ul className="space-y-1">
                                                    {student.advisors.map(
                                                        (advisor) => (
                                                            <li
                                                                key={`${student.id}-${advisor.label}-${advisor.name}`}
                                                            >
                                                                <span className="text-muted-foreground">
                                                                    {
                                                                        advisor.label
                                                                    }
                                                                    :
                                                                </span>{' '}
                                                                {advisor.name}
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    Belum ada pembimbing aktif
                                                </span>
                                            )}
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
        </PublicLayout>
    );
}
