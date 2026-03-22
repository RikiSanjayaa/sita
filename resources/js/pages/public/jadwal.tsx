import { router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';

import { PublicLayout } from '@/components/public/public-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
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

const statusClassName: Record<ScheduleItem['statusTone'], string> = {
    default: 'bg-primary/10 text-primary hover:bg-primary/20',
    warning:
        'bg-amber-600/10 text-amber-700 hover:bg-amber-600/20 dark:text-amber-400',
    danger: 'bg-destructive/10 text-destructive hover:bg-destructive/20',
    muted: 'bg-muted text-muted-foreground hover:bg-muted',
};

const sectionCardClass = 'overflow-hidden py-0 shadow-sm';
const sectionCardHeaderClass = 'border-b bg-muted/20 px-6 py-4';

function ScheduleTable({
    emptyMessage,
    items,
    pagination,
    title,
    onPageChange,
}: {
    emptyMessage: string;
    items: ScheduleItem[];
    pagination: PaginationData;
    title: string;
    onPageChange: (page: number) => void;
}) {
    const rangeStart =
        items.length === 0
            ? 0
            : (pagination.currentPage - 1) * pagination.perPage + 1;
    const rangeEnd = items.length === 0 ? 0 : rangeStart + items.length - 1;

    return (
        <Card className={sectionCardClass}>
            <CardHeader
                className={`${sectionCardHeaderClass} gap-3 sm:flex-row sm:items-center sm:justify-between`}
            >
                <div>
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>
                        Jadwal yang tersedia pada bagian ini.
                    </CardDescription>
                </div>
                <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                    <span>
                        Menampilkan {rangeStart}-{rangeEnd}
                    </span>
                    <Badge variant="outline">
                        Halaman {pagination.currentPage}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="space-y-4 pb-6">
                {items.length > 0 ? (
                    <div className="space-y-3">
                        <div className="grid gap-3 md:hidden">
                            {items.map((item) => (
                                <div
                                    key={`${title}-${item.id}`}
                                    className="rounded-xl border bg-background p-4"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="space-y-2">
                                            <div className="flex flex-wrap gap-2">
                                                <Badge
                                                    className={
                                                        item.type === 'sempro'
                                                            ? 'bg-primary text-primary-foreground hover:bg-primary/90'
                                                            : 'bg-emerald-600 text-white hover:bg-emerald-600/90'
                                                    }
                                                >
                                                    {item.typeLabel}
                                                </Badge>
                                                <Badge
                                                    variant="soft"
                                                    className={
                                                        statusClassName[
                                                            item.statusTone
                                                        ]
                                                    }
                                                >
                                                    {item.statusLabel}
                                                </Badge>
                                            </div>
                                            <div>
                                                <p className="font-medium text-foreground">
                                                    {item.studentName}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {item.studentNim}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="text-right text-sm text-muted-foreground">
                                            {item.scheduledFor ?? '-'}
                                        </div>
                                    </div>

                                    <div className="mt-4 space-y-3 text-sm">
                                        <div>
                                            <p className="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                                Prodi
                                            </p>
                                            <p className="mt-1 text-foreground">
                                                {item.programStudi}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                                Lokasi
                                            </p>
                                            <p className="mt-1 text-muted-foreground">
                                                {item.location} ({item.mode})
                                            </p>
                                        </div>
                                        {item.statusDetail ? (
                                            <div>
                                                <p className="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                                    Detail
                                                </p>
                                                <p className="mt-1 leading-5 text-muted-foreground">
                                                    {item.statusDetail}
                                                </p>
                                            </div>
                                        ) : null}
                                    </div>
                                </div>
                            ))}
                        </div>

                        <ScrollArea className="hidden rounded-xl border md:block">
                            <table className="w-full min-w-[760px] text-sm">
                                <thead className="bg-muted/30 text-left">
                                    <tr>
                                        <th className="px-4 py-3 font-semibold">
                                            Jenis
                                        </th>
                                        <th className="px-4 py-3 font-semibold">
                                            Waktu
                                        </th>
                                        <th className="px-4 py-3 font-semibold">
                                            Mahasiswa
                                        </th>
                                        <th className="px-4 py-3 font-semibold">
                                            Prodi
                                        </th>
                                        <th className="px-4 py-3 font-semibold">
                                            Lokasi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {items.map((item) => (
                                        <tr
                                            key={`${title}-${item.id}`}
                                            className="border-t align-top"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="flex flex-col gap-2">
                                                    <Badge
                                                        className={
                                                            item.type ===
                                                            'sempro'
                                                                ? 'bg-primary text-primary-foreground hover:bg-primary/90'
                                                                : 'bg-emerald-600 text-white hover:bg-emerald-600/90'
                                                        }
                                                    >
                                                        {item.typeLabel}
                                                    </Badge>
                                                    <Badge
                                                        variant="soft"
                                                        className={
                                                            statusClassName[
                                                                item.statusTone
                                                            ]
                                                        }
                                                    >
                                                        {item.statusLabel}
                                                    </Badge>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {item.scheduledFor ?? '-'}
                                            </td>
                                            <td className="px-4 py-3">
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
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {item.programStudi}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {item.location} ({item.mode})
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </ScrollArea>
                    </div>
                ) : (
                    <div className="rounded-xl border border-dashed bg-muted/20 p-6 text-center text-sm text-muted-foreground">
                        {emptyMessage}
                    </div>
                )}

                <div className="flex flex-col-reverse items-center justify-center gap-2 sm:flex-row sm:flex-wrap">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={pagination.previousPage === null}
                        onClick={() => {
                            if (pagination.previousPage !== null) {
                                onPageChange(pagination.previousPage);
                            }
                        }}
                    >
                        Sebelumnya
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={pagination.nextPage === null}
                        onClick={() => {
                            if (pagination.nextPage !== null) {
                                onPageChange(pagination.nextPage);
                            }
                        }}
                    >
                        Berikutnya
                    </Button>
                </div>
            </CardContent>
        </Card>
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

    const hasActiveSearch = filters.search.trim() !== '';

    return (
        <PublicLayout
            active="jadwal"
            headTitle="Jadwal Sempro dan Sidang"
            pageTitle="Jadwal Sempro dan Sidang"
            description="Agenda publik yang akan datang, serta seminar terbaru yang masih memerlukan tindak lanjut seperti revisi atau pelengkapan nilai."
        >
            <div className="grid gap-6">
                <Card className="shadow-sm">
                    <CardHeader className="gap-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <CardTitle>Cari jadwal publik</CardTitle>
                            <div className="relative w-full max-w-md">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Cari mahasiswa, NIM, prodi, atau lokasi..."
                                    className="pl-9"
                                />
                            </div>
                        </div>
                    </CardHeader>
                </Card>

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
                />
            </div>
        </PublicLayout>
    );
}
