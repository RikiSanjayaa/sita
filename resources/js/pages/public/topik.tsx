import { router, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronRight, Search } from 'lucide-react';
import { Fragment, useEffect, useState } from 'react';

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
    seminarDate: string | null;
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

    const rangeStart =
        semproTitles.length === 0
            ? 0
            : (topicPagination.currentPage - 1) * topicPagination.perPage + 1;
    const rangeEnd =
        semproTitles.length === 0 ? 0 : rangeStart + semproTitles.length - 1;
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
            headTitle="Topik Tugas Akhir"
            pageTitle="Topik Tugas Akhir"
        >
            <Card className="shadow-sm">
                <CardHeader className="gap-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <CardTitle>Daftar Topik</CardTitle>
                        <div className="flex w-full flex-col gap-3 sm:max-w-2xl sm:flex-row">
                            <div className="w-full sm:max-w-xs">
                                <Select
                                    value={programFilter}
                                    onValueChange={(value) => {
                                        setProgramFilter(value);
                                        setOpenRowId(null);
                                    }}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Filter prodi" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="semua">
                                            Semua Prodi
                                        </SelectItem>
                                        {topicPrograms.map((program) => (
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
                                    onChange={(event) => {
                                        setSearch(event.target.value);
                                        setOpenRowId(null);
                                    }}
                                    placeholder="Cari judul, ringkasan, mahasiswa, atau NIM..."
                                    className="pl-9"
                                />
                            </div>
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="space-y-4">
                    {semproTitles.length > 0 ? (
                        <>
                            <div className="overflow-x-auto rounded-xl border">
                                <table className="w-full min-w-[980px] text-sm">
                                    <thead className="bg-muted/30 text-left">
                                        <tr>
                                            <th className="w-14 px-4 py-3 font-semibold">
                                                Detail
                                            </th>
                                            <th className="px-4 py-3 font-semibold">
                                                Judul
                                            </th>
                                            <th className="px-4 py-3 font-semibold">
                                                Prodi
                                            </th>
                                            <th className="px-4 py-3 font-semibold">
                                                Tahun
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {semproTitles.map((item) => {
                                            const isOpen =
                                                openRowId === item.id;

                                            return (
                                                <Fragment key={item.id}>
                                                    <tr className="border-t align-top">
                                                        <td className="px-4 py-3">
                                                            <Button
                                                                type="button"
                                                                size="icon"
                                                                variant="ghost"
                                                                className="size-8 rounded-full"
                                                                onClick={() =>
                                                                    toggleRow(
                                                                        item.id,
                                                                    )
                                                                }
                                                                aria-label="Buka detail topik"
                                                            >
                                                                {isOpen ? (
                                                                    <ChevronDown className="size-4" />
                                                                ) : (
                                                                    <ChevronRight className="size-4" />
                                                                )}
                                                            </Button>
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <div className="max-w-md leading-6 font-medium text-foreground">
                                                                {item.title}
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-3 text-muted-foreground">
                                                            {item.programStudi}
                                                        </td>
                                                        <td className="px-4 py-3 text-muted-foreground">
                                                            {item.year}
                                                        </td>
                                                    </tr>

                                                    {isOpen ? (
                                                        <tr className="border-t bg-muted/10">
                                                            <td
                                                                colSpan={4}
                                                                className="px-6 py-4"
                                                            >
                                                                <div className="grid gap-4 lg:grid-cols-[0.9fr_1.1fr_0.8fr]">
                                                                    <div className="rounded-xl border bg-background p-4">
                                                                        <p className="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                                                            Mahasiswa
                                                                        </p>
                                                                        <div className="mt-3 space-y-1 text-sm">
                                                                            <p className="font-medium text-foreground">
                                                                                {
                                                                                    item.studentName
                                                                                }
                                                                            </p>
                                                                            <p className="text-muted-foreground">
                                                                                NIM{' '}
                                                                                {
                                                                                    item.studentNim
                                                                                }
                                                                            </p>
                                                                            <p className="text-muted-foreground">
                                                                                {
                                                                                    item.programStudi
                                                                                }
                                                                            </p>
                                                                        </div>
                                                                    </div>

                                                                    <div className="space-y-4 rounded-xl border bg-background p-4">
                                                                        <div>
                                                                            <p className="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                                                                Judul
                                                                                Internasional
                                                                            </p>
                                                                            <p className="mt-1 text-sm text-muted-foreground italic">
                                                                                {item.titleEn ||
                                                                                    '-'}
                                                                            </p>
                                                                        </div>

                                                                        <div>
                                                                            <p className="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                                                                Ringkasan
                                                                                Proposal
                                                                            </p>
                                                                            <p className="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground">
                                                                                {item.summary ||
                                                                                    '-'}
                                                                            </p>
                                                                        </div>
                                                                    </div>

                                                                    <div className="space-y-3 rounded-xl border bg-background p-4">
                                                                        <p className="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                                                            Pembimbing
                                                                            Aktif
                                                                        </p>
                                                                        <div className="flex flex-wrap gap-2">
                                                                            {item.advisors.map(
                                                                                (
                                                                                    advisor,
                                                                                ) => (
                                                                                    <Badge
                                                                                        key={`${item.id}-${advisor.label}`}
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
                                                                        <div className="border-t pt-3 text-sm text-muted-foreground">
                                                                            <span className="font-medium text-foreground">
                                                                                Tahun:
                                                                            </span>{' '}
                                                                            {
                                                                                item.year
                                                                            }
                                                                        </div>
                                                                        <div className="text-sm text-muted-foreground">
                                                                            <span className="font-medium text-foreground">
                                                                                Seminar
                                                                                selesai:
                                                                            </span>{' '}
                                                                            {item.seminarDate ||
                                                                                '-'}
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    ) : null}
                                                </Fragment>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>

                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <p className="text-sm text-muted-foreground">
                                    Menampilkan {rangeStart}-{rangeEnd} dari
                                    halaman ini.
                                </p>

                                <div className="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={
                                            topicPagination.previousPage ===
                                            null
                                        }
                                        onClick={() => {
                                            if (
                                                topicPagination.previousPage !==
                                                null
                                            ) {
                                                visitPage(
                                                    topicPagination.previousPage,
                                                );
                                            }
                                        }}
                                    >
                                        Sebelumnya
                                    </Button>
                                    <Badge variant="outline">
                                        Halaman {topicPagination.currentPage}
                                    </Badge>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={!topicPagination.hasMorePages}
                                        onClick={() => {
                                            if (
                                                topicPagination.nextPage !==
                                                null
                                            ) {
                                                visitPage(
                                                    topicPagination.nextPage,
                                                );
                                            }
                                        }}
                                    >
                                        Berikutnya
                                    </Button>
                                </div>
                            </div>
                        </>
                    ) : (
                        <div className="rounded-xl border border-dashed bg-muted/20 p-6 text-center text-sm text-muted-foreground">
                            {hasActiveFilter
                                ? 'Tidak ada topik yang cocok dengan pencarian ini.'
                                : 'Belum ada topik yang dapat ditampilkan.'}
                        </div>
                    )}
                </CardContent>
            </Card>
        </PublicLayout>
    );
}
