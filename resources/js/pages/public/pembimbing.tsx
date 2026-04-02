import { usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import { PublicLayout } from '@/components/public/public-layout';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DataTableContainer,
    DataTableEmptyState,
    DataTableToolbar,
} from '@/components/ui/data-table';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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

const sectionCardClass = 'overflow-hidden py-0 shadow-sm';
const sectionCardHeaderClass = 'border-b bg-muted/20 px-6 py-4';

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
            <div className="space-y-6">
                <Card className="shadow-sm">
                    <CardHeader className="gap-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <CardTitle>Program Studi</CardTitle>
                            <div className="w-full sm:max-w-xs">
                                <Select
                                    value={programFilter}
                                    onValueChange={setProgramFilter}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih program studi" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {advisorPrograms.map((program) => (
                                            <SelectItem
                                                key={program.slug}
                                                value={program.slug}
                                            >
                                                {program.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </CardHeader>
                </Card>

                <DataTableToolbar
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Cari dosen atau konsentrasi..."
                />

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
                            <Card
                                key={concentration}
                                className={sectionCardClass}
                            >
                                <CardHeader className={sectionCardHeaderClass}>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <CardTitle>{concentration}</CardTitle>
                                        <Badge variant="outline">
                                            {advisors.length} dosen
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4 pb-6">
                                    <DataTableContainer>
                                        <table className="w-full min-w-[640px] text-sm">
                                            <thead className="bg-muted/30 text-left">
                                                <tr>
                                                    <th className="px-4 py-3 font-semibold">
                                                        Dosen Pembimbing
                                                    </th>
                                                    <th className="w-[8.5rem] px-4 py-3 text-center font-semibold whitespace-nowrap">
                                                        Pembimbing 1
                                                    </th>
                                                    <th className="w-[8.5rem] px-4 py-3 text-center font-semibold whitespace-nowrap">
                                                        Pembimbing 2
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {advisors.map((advisor) => (
                                                    <tr
                                                        key={advisor.id}
                                                        className="border-t"
                                                    >
                                                        <td className="px-4 py-3 font-medium text-foreground">
                                                            {advisor.name}
                                                        </td>
                                                        <td className="px-4 py-3 text-center font-medium text-muted-foreground tabular-nums">
                                                            {
                                                                advisor.primaryCount
                                                            }
                                                        </td>
                                                        <td className="px-4 py-3 text-center font-medium text-muted-foreground tabular-nums">
                                                            {
                                                                advisor.secondaryCount
                                                            }
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </DataTableContainer>

                                    <div className="flex justify-end text-sm text-muted-foreground">
                                        Total mahasiswa aktif pada konsentrasi
                                        ini:{' '}
                                        <span className="ml-1 font-semibold text-foreground">
                                            {totalActiveStudents}
                                        </span>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })
                ) : (
                    <Card className={sectionCardClass}>
                        <CardContent className="p-6">
                            <DataTableEmptyState
                                title="Tidak ada pembimbing"
                                description="Belum ada data pembimbing aktif pada filter ini."
                            />
                        </CardContent>
                    </Card>
                )}
            </div>
        </PublicLayout>
    );
}
