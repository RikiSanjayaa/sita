import { usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import {
    DataSection,
    DataSelect,
    DataToolbar,
    dataTh,
} from '@/components/patterns/data-section';
import { PublicLayout } from '@/components/public/public-layout';
import {
    DataTableContainer,
    DataTableEmptyState,
    DataTablePagination,
    usePagination,
} from '@/components/ui/data-table';
import { type SharedData } from '@/types';

type AdvisorDirectoryItem = {
    id: number;
    name: string;
    programStudi: string;
    programSlug: string;
    concentration: string;
    primaryCount: number;
    secondaryCount: number;
    totalActiveCount: number;
};

type AdvisorProgram = {
    slug: string;
    name: string;
};

type PageProps = {
    advisorDirectory: AdvisorDirectoryItem[];
    advisorPrograms: AdvisorProgram[];
    concentrationStudentTotals: Record<string, Record<string, number>>;
};

const ADVISOR_PAGE_SIZE = 10;

function AdvisorGroupTable({
    advisors,
    concentration,
    totalActiveStudents,
}: {
    advisors: AdvisorDirectoryItem[];
    concentration: string;
    totalActiveStudents: number;
}) {
    const {
        page,
        setPage,
        pageSize,
        setPageSize,
        totalPages,
        paginated,
        totalItems,
    } = usePagination(advisors, ADVISOR_PAGE_SIZE, [
        concentration,
        advisors.length,
    ]);

    return (
        <DataSection title={concentration} summary={`${advisors.length} dosen`}>
            <DataTableContainer
                pagination={
                    <DataTablePagination
                        currentPage={page}
                        totalPages={totalPages}
                        totalItems={totalItems}
                        pageSize={pageSize}
                        onPageChange={setPage}
                        onPageSizeChange={setPageSize}
                        itemLabel="dosen"
                    />
                }
            >
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b bg-muted/40">
                            <th className={dataTh}>Dosen Pembimbing</th>
                            <th
                                className={`${dataTh} w-24 text-center md:w-36`}
                            >
                                Pembimbing 1
                            </th>
                            <th
                                className={`${dataTh} w-24 text-center md:w-36`}
                            >
                                Pembimbing 2
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {paginated.map((advisor) => (
                            <tr key={advisor.id}>
                                <td className="px-4 py-3 font-medium text-foreground">
                                    {advisor.name}
                                </td>
                                <td className="px-4 py-3 text-center text-muted-foreground tabular-nums">
                                    {advisor.primaryCount}
                                </td>
                                <td className="px-4 py-3 text-center text-muted-foreground tabular-nums">
                                    {advisor.secondaryCount}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </DataTableContainer>

            <p className="text-sm text-muted-foreground">
                Total mahasiswa aktif pada konsentrasi ini:{' '}
                <span className="font-semibold text-foreground tabular-nums">
                    {totalActiveStudents}
                </span>
            </p>
        </DataSection>
    );
}

export default function PublicAdvisorsPage() {
    const { advisorDirectory, advisorPrograms, concentrationStudentTotals } =
        usePage<SharedData & PageProps>().props;
    const [programFilter, setProgramFilter] = useState<string>(
        advisorPrograms[0]?.slug ?? '',
    );
    const [search, setSearch] = useState('');

    const visibleAdvisors = useMemo(() => {
        const normalizedSearch = search.trim().toLowerCase();

        return advisorDirectory.filter((advisor) => {
            const matchProgram = advisor.programSlug === programFilter;
            const matchSearch =
                normalizedSearch === '' ||
                advisor.name.toLowerCase().includes(normalizedSearch) ||
                advisor.programStudi.toLowerCase().includes(normalizedSearch) ||
                advisor.concentration.toLowerCase().includes(normalizedSearch);

            return matchProgram && matchSearch;
        });
    }, [advisorDirectory, programFilter, search]);

    const advisorGroups = useMemo(() => {
        const grouped = visibleAdvisors.reduce<
            Record<string, AdvisorDirectoryItem[]>
        >((carry, advisor) => {
            const key = advisor.concentration || 'Umum';

            carry[key] ??= [];
            carry[key].push(advisor);

            return carry;
        }, {});

        return Object.entries(grouped).sort(([left], [right]) =>
            left.localeCompare(right),
        );
    }, [visibleAdvisors]);

    return (
        <PublicLayout
            active="pembimbing"
            headTitle="Direktori Pembimbing"
            pageTitle="Direktori Pembimbing"
            description="Daftar dosen pembimbing aktif yang dikelompokkan per konsentrasi dan dapat difilter berdasarkan program studi."
        >
            <div className="space-y-8">
                <DataToolbar
                    search={search}
                    onSearchChange={setSearch}
                    placeholder="Cari dosen atau konsentrasi..."
                >
                    <DataSelect
                        label="Program studi"
                        value={programFilter}
                        onValueChange={setProgramFilter}
                        placeholder="Pilih program studi"
                        options={advisorPrograms.map((program) => ({
                            value: program.slug,
                            label: program.name,
                        }))}
                    />
                </DataToolbar>

                {advisorGroups.length > 0 ? (
                    advisorGroups.map(([concentration, advisors]) => {
                        const totalActiveStudents =
                            concentrationStudentTotals[programFilter]?.[
                                concentration
                            ] ??
                            advisors.reduce(
                                (total, advisor) =>
                                    total + advisor.totalActiveCount,
                                0,
                            );

                        return (
                            <AdvisorGroupTable
                                key={concentration}
                                advisors={advisors}
                                concentration={concentration}
                                totalActiveStudents={totalActiveStudents}
                            />
                        );
                    })
                ) : (
                    <DataTableEmptyState
                        title="Tidak ada pembimbing"
                        description="Belum ada data pembimbing aktif pada filter ini."
                    />
                )}
            </div>
        </PublicLayout>
    );
}
