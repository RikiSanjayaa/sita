import { router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';

import { PublicLayout } from '@/components/public/public-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { type SharedData } from '@/types';

type StudentRow = {
    id: number;
    name: string;
    nim: string;
    programStudi: string;
    programSlug: string;
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
    studentPrograms: Array<{
        slug: string;
        name: string;
    }>;
};

const sectionCardClass = 'overflow-hidden py-0 shadow-sm';
const sectionCardHeaderClass = 'border-b bg-muted/20 px-6 py-4';

export default function PublicStudentsPage() {
    const { filters, activeStudents, studentPagination, studentPrograms } =
        usePage<SharedData & PageProps>().props;

    return (
        <PublicStudentsContent
            key={`${filters.search}:${filters.program}`}
            filters={filters}
            activeStudents={activeStudents}
            studentPagination={studentPagination}
            studentPrograms={studentPrograms}
        />
    );
}

function PublicStudentsContent({
    filters,
    activeStudents,
    studentPagination,
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
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                },
            );
        }, 250);

        return () => window.clearTimeout(timeoutId);
    }, [filters.program, filters.search, programFilter, search]);

    function visitPage(page: number) {
        router.get(
            '/mahasiswa-aktif',
            {
                page,
                search: filters.search || undefined,
                program: filters.program || undefined,
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    }

    const rangeStart =
        activeStudents.length === 0
            ? 0
            : (studentPagination.currentPage - 1) * studentPagination.perPage +
              1;
    const rangeEnd =
        activeStudents.length === 0
            ? 0
            : rangeStart + activeStudents.length - 1;
    const hasActiveFilter =
        filters.search.trim() !== '' || filters.program.trim() !== '';

    return (
        <PublicLayout
            active="mahasiswa"
            headTitle="Mahasiswa Aktif"
            pageTitle="Mahasiswa Tugas Akhir Aktif"
            description="Daftar mahasiswa yang masih aktif menjalani proses tugas akhir, termasuk mahasiswa baru terdaftar, sempro, bimbingan aktif, hingga sidang yang masih berjalan."
        >
            <div className="space-y-6">
                <Card className="shadow-sm">
                    <CardHeader className="gap-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <CardTitle>Filter Mahasiswa</CardTitle>
                            <div className="flex w-full flex-col gap-3 sm:max-w-2xl sm:flex-row">
                                <div className="w-full sm:max-w-xs">
                                    <Select
                                        value={programFilter}
                                        onValueChange={setProgramFilter}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Filter prodi" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="semua">
                                                Semua Prodi
                                            </SelectItem>
                                            {studentPrograms.map((program) => (
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

                                <div className="relative w-full max-w-md">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        placeholder="Cari nama, NIM, atau prodi..."
                                        className="pl-9"
                                    />
                                </div>
                            </div>
                        </div>
                    </CardHeader>
                </Card>

                <Card className={sectionCardClass}>
                    <CardHeader
                        className={`${sectionCardHeaderClass} gap-3 sm:flex-row sm:items-center sm:justify-between`}
                    >
                        <CardTitle>Daftar Mahasiswa</CardTitle>
                        <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <span>
                                Menampilkan {rangeStart}-{rangeEnd} dari{' '}
                                {studentPagination.total} mahasiswa
                            </span>
                            <Badge variant="outline">
                                Halaman {studentPagination.currentPage} /{' '}
                                {studentPagination.lastPage}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4 pb-6">
                        {activeStudents.length > 0 ? (
                            <div className="overflow-x-auto rounded-xl border">
                                <table className="w-full min-w-[980px] text-sm">
                                    <thead className="bg-muted/30 text-left">
                                        <tr>
                                            <th className="px-4 py-3 font-semibold">
                                                Mahasiswa
                                            </th>
                                            <th className="px-4 py-3 font-semibold">
                                                Prodi
                                            </th>
                                            <th className="px-4 py-3 font-semibold">
                                                Tahap
                                            </th>
                                            <th className="px-4 py-3 font-semibold">
                                                Pembimbing Aktif
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {activeStudents.map((student) => (
                                            <tr
                                                key={student.id}
                                                className="border-t align-top"
                                            >
                                                <td className="px-4 py-3">
                                                    <div className="space-y-1">
                                                        <div className="font-medium text-foreground">
                                                            {student.name}
                                                        </div>
                                                        <div className="text-muted-foreground">
                                                            {student.nim}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {student.programStudi}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="space-y-2">
                                                        <Badge variant="outline">
                                                            {student.stageLabel}
                                                        </Badge>
                                                        <p className="max-w-xs text-xs leading-5 text-muted-foreground">
                                                            {
                                                                student.stageDescription
                                                            }
                                                        </p>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    {student.advisors.length >
                                                    0 ? (
                                                        <div className="flex max-w-xs flex-wrap gap-2">
                                                            {student.advisors.map(
                                                                (advisor) => (
                                                                    <Badge
                                                                        key={`${student.id}-${advisor.label}-${advisor.name}`}
                                                                        variant="secondary"
                                                                    >
                                                                        {
                                                                            advisor.label
                                                                        }
                                                                        :{' '}
                                                                        {
                                                                            advisor.name
                                                                        }
                                                                    </Badge>
                                                                ),
                                                            )}
                                                        </div>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            Belum ada pembimbing
                                                            aktif
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <div className="rounded-xl border border-dashed bg-muted/20 p-6 text-center text-sm text-muted-foreground">
                                {hasActiveFilter
                                    ? 'Tidak ada mahasiswa aktif yang cocok dengan filter ini.'
                                    : 'Belum ada mahasiswa aktif yang dapat ditampilkan.'}
                            </div>
                        )}

                        <div className="flex items-center justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={
                                    studentPagination.previousPage === null
                                }
                                onClick={() => {
                                    if (
                                        studentPagination.previousPage !== null
                                    ) {
                                        visitPage(
                                            studentPagination.previousPage,
                                        );
                                    }
                                }}
                            >
                                Sebelumnya
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={studentPagination.nextPage === null}
                                onClick={() => {
                                    if (studentPagination.nextPage !== null) {
                                        visitPage(studentPagination.nextPage);
                                    }
                                }}
                            >
                                Berikutnya
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </PublicLayout>
    );
}
