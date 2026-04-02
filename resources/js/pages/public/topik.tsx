import { router, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { Fragment, useEffect, useMemo, useState } from 'react';

import { PublicLayout } from '@/components/public/public-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DataTableContainer,
    DataTableEmptyState,
    DataTablePagination,
    DataTableToolbar,
} from '@/components/ui/data-table';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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

    const rangeStart =
        filteredTitles.length === 0
            ? 0
            : (topicPagination.currentPage - 1) * topicPagination.perPage + 1;
    const rangeEnd =
        filteredTitles.length === 0
            ? 0
            : rangeStart + filteredTitles.length - 1;
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
            description="Daftar topik tugas akhir yang sudah selesai sempro dan dapat ditelusuri berdasarkan program studi, judul, mahasiswa, atau kata kunci ringkasan."
        >
            <div className="space-y-6">
                <Card className="overflow-hidden py-0 shadow-sm">
                    <CardHeader className="border-b bg-muted/20 px-6 py-4">
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div className="space-y-1.5">
                                <CardTitle>Daftar Topik</CardTitle>
                                <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                    <span>
                                        Menampilkan {rangeStart}-{rangeEnd} dari
                                        halaman ini
                                    </span>
                                    <Badge variant="outline">
                                        Halaman {topicPagination.currentPage}
                                    </Badge>
                                </div>
                            </div>

                            <div className="w-full lg:max-w-xs">
                                <Select
                                    value={programFilter}
                                    onValueChange={(value) => {
                                        setProgramFilter(value);
                                        setOpenRowId(null);
                                    }}
                                >
                                    <SelectTrigger className="bg-background">
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
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4 pb-6">
                        <DataTableToolbar
                            search={search}
                            onSearchChange={(value) => {
                                setSearch(value);
                                setOpenRowId(null);
                            }}
                            searchPlaceholder="Cari judul, ringkasan, mahasiswa, atau NIM..."
                            filterGroups={[
                                {
                                    tabs: yearTabs,
                                    value: yearFilter,
                                    onChange: (value) => {
                                        setYearFilter(value);
                                        setOpenRowId(null);
                                    },
                                },
                            ]}
                        />

                        {filteredTitles.length > 0 ? (
                            <>
                                <DataTableContainer>
                                    <table className="w-full min-w-[980px] text-sm">
                                        <thead className="bg-muted/30 text-left">
                                            <tr>
                                                <th className="w-14 px-4 py-3 font-semibold">
                                                    Detail
                                                </th>
                                                <th className="px-4 py-3 font-semibold">
                                                    Mahasiswa
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
                                            {filteredTitles.map((item) => {
                                                const isOpen =
                                                    openRowId === item.id;

                                                return (
                                                    <Fragment key={item.id}>
                                                        <tr className="border-t align-top transition-colors hover:bg-muted/5">
                                                            <td className="px-4 py-3">
                                                                <Button
                                                                    type="button"
                                                                    size="icon"
                                                                    variant="ghost"
                                                                    className="size-8 rounded-full transition-transform duration-300"
                                                                    onClick={() =>
                                                                        toggleRow(
                                                                            item.id,
                                                                        )
                                                                    }
                                                                    aria-label="Buka detail topik"
                                                                >
                                                                    <ChevronRight
                                                                        className={`size-4 transition-transform duration-300 ${isOpen ? 'rotate-90' : ''}`}
                                                                    />
                                                                </Button>
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                <div className="space-y-1">
                                                                    <div className="font-medium text-foreground">
                                                                        {
                                                                            item.studentName
                                                                        }
                                                                    </div>
                                                                    <div className="text-muted-foreground">
                                                                        {
                                                                            item.studentNim
                                                                        }
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                <div className="max-w-md leading-6 font-medium text-foreground">
                                                                    {item.title}
                                                                </div>
                                                            </td>
                                                            <td className="px-4 py-3 text-muted-foreground">
                                                                {
                                                                    item.programStudi
                                                                }
                                                            </td>
                                                            <td className="px-4 py-3 text-muted-foreground">
                                                                {item.year}
                                                            </td>
                                                        </tr>

                                                        <tr className="border-t bg-background">
                                                            <td
                                                                colSpan={5}
                                                                className="p-0"
                                                            >
                                                                <div
                                                                    className={cn(
                                                                        'grid transition-all duration-300 ease-out',
                                                                        isOpen
                                                                            ? 'grid-rows-[1fr] opacity-100'
                                                                            : 'grid-rows-[0fr] opacity-0',
                                                                    )}
                                                                >
                                                                    <div className="overflow-hidden">
                                                                        <div
                                                                            className={cn(
                                                                                'px-6 transition-[padding,transform,opacity] duration-300 ease-out',
                                                                                isOpen
                                                                                    ? 'translate-y-0 py-5 opacity-100'
                                                                                    : '-translate-y-2 py-0 opacity-0',
                                                                            )}
                                                                        >
                                                                            <div className="rounded-b-2xl border-t bg-muted/20 px-5 py-5">
                                                                                <div className="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.9fr)]">
                                                                                    <div className="space-y-5">
                                                                                        <div className="space-y-2">
                                                                                            <p className="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                                                                                Ringkasan
                                                                                                Proposal
                                                                                            </p>
                                                                                            <p className="max-w-3xl text-sm leading-7 text-muted-foreground">
                                                                                                {item.summary ||
                                                                                                    '-'}
                                                                                            </p>
                                                                                        </div>

                                                                                        <div className="space-y-2">
                                                                                            <p className="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                                                                                Judul
                                                                                                Internasional
                                                                                            </p>
                                                                                            <p className="text-sm text-muted-foreground italic">
                                                                                                {item.titleEn ||
                                                                                                    '-'}
                                                                                            </p>
                                                                                        </div>
                                                                                    </div>

                                                                                    <div className="space-y-5 border-t pt-5 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-6">
                                                                                        <div className="space-y-2">
                                                                                            <p className="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                                                                                Pembimbing
                                                                                            </p>
                                                                                            <div className="flex flex-wrap gap-2">
                                                                                                {item
                                                                                                    .advisors
                                                                                                    .length >
                                                                                                0 ? (
                                                                                                    item.advisors.map(
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
                                                                                                    )
                                                                                                ) : (
                                                                                                    <span className="text-sm text-muted-foreground">
                                                                                                        Belum
                                                                                                        ada
                                                                                                        data
                                                                                                        pembimbing.
                                                                                                    </span>
                                                                                                )}
                                                                                            </div>
                                                                                        </div>

                                                                                        <div className="grid gap-3 text-sm text-muted-foreground sm:grid-cols-2 lg:grid-cols-1">
                                                                                            <div>
                                                                                                <span className="font-medium text-foreground">
                                                                                                    Tahun:
                                                                                                </span>{' '}
                                                                                                {
                                                                                                    item.year
                                                                                                }
                                                                                            </div>
                                                                                            <div>
                                                                                                <span className="font-medium text-foreground">
                                                                                                    Seminar
                                                                                                    selesai:
                                                                                                </span>{' '}
                                                                                                {item.seminarDate ||
                                                                                                    '-'}
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    </Fragment>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </DataTableContainer>

                                <DataTablePagination
                                    currentPage={topicPagination.currentPage}
                                    totalPages={Math.max(
                                        1,
                                        topicPagination.currentPage +
                                            (topicPagination.hasMorePages
                                                ? 1
                                                : 0),
                                    )}
                                    totalItems={filteredTitles.length}
                                    pageSize={topicPagination.perPage}
                                    onPageChange={visitPage}
                                    itemLabel="topik"
                                />
                            </>
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
                    </CardContent>
                </Card>
            </div>
        </PublicLayout>
    );
}
