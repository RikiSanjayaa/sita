import { usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import { PublicLayout } from '@/components/public/public-layout';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
};

export default function PublicAdvisorsPage() {
    const { advisorDirectory, advisorPrograms } = usePage<
        SharedData & PageProps
    >().props;
    const [programFilter, setProgramFilter] = useState<string>(
        advisorPrograms[0]?.slug ?? 'semua',
    );

    const visibleAdvisors = useMemo(() => {
        if (programFilter === 'semua') {
            return advisorDirectory;
        }

        return advisorDirectory.filter(
            (advisor) => advisor.programSlug === programFilter,
        );
    }, [advisorDirectory, programFilter]);

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
            title="Direktori Pembimbing"
            description="Daftar ini hanya menampilkan dosen pembimbing aktif. Gunakan filter program studi, lalu lihat tabel pembimbing yang dikelompokkan per konsentrasi."
        >
            <div className="space-y-6">
                <Card className="shadow-sm">
                    <CardHeader>
                        <CardTitle>Filter Program Studi</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="max-w-xs">
                            <Select
                                value={programFilter}
                                onValueChange={setProgramFilter}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih program studi" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="semua">
                                        Semua Program Studi
                                    </SelectItem>
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
                    </CardContent>
                </Card>

                {advisorGroups.length > 0 ? (
                    advisorGroups.map(([concentration, advisors]) => {
                        const totalActiveStudents = advisors.reduce(
                            (total, advisor) =>
                                total + advisor.totalActiveCount,
                            0,
                        );

                        return (
                            <Card key={concentration} className="shadow-sm">
                                <CardHeader>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <CardTitle>{concentration}</CardTitle>
                                        <Badge variant="outline">
                                            {advisors.length} dosen
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="overflow-x-auto rounded-xl border">
                                        <table className="w-full min-w-[640px] text-sm">
                                            <thead className="bg-muted/30 text-left">
                                                <tr>
                                                    <th className="px-4 py-3 font-semibold">
                                                        Dosen Pembimbing
                                                    </th>
                                                    <th className="px-4 py-3 font-semibold">
                                                        Pembimbing 1
                                                    </th>
                                                    <th className="px-4 py-3 font-semibold">
                                                        Pembimbing 2
                                                    </th>
                                                    <th className="px-4 py-3 font-semibold">
                                                        Total Aktif
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
                                                        <td className="px-4 py-3 text-muted-foreground">
                                                            {
                                                                advisor.primaryCount
                                                            }
                                                        </td>
                                                        <td className="px-4 py-3 text-muted-foreground">
                                                            {
                                                                advisor.secondaryCount
                                                            }
                                                        </td>
                                                        <td className="px-4 py-3 font-medium text-foreground">
                                                            {
                                                                advisor.totalActiveCount
                                                            }
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>

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
                    <Card className="shadow-sm">
                        <CardContent className="p-6 text-center text-sm text-muted-foreground">
                            Belum ada data pembimbing aktif pada filter ini.
                        </CardContent>
                    </Card>
                )}
            </div>
        </PublicLayout>
    );
}
