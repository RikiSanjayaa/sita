import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    ChevronDown,
    FileText,
    MessageCircle,
    MessageSquareText,
    MessagesSquare,
    Search,
    UserRound,
} from 'lucide-react';
import { useMemo } from 'react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DataTableContainer,
    DataTablePagination,
    usePagination,
} from '@/components/ui/data-table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { useInitials } from '@/hooks/use-initials';
import { useUrlState } from '@/hooks/use-url-state';
import DosenLayout from '@/layouts/dosen-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dosen/dashboard' },
    { title: 'Mahasiswa Dosen', href: '/dosen/mahasiswa-bimbingan' },
];

const STUDENT_PAGE_SIZE = 15;

type RoleFilter = 'semua' | 'Pembimbing 1' | 'Pembimbing 2' | 'penguji';

type MahasiswaRow = {
    studentUserId: number | null;
    nim: string;
    name: string;
    avatar: string | null;
    profileUrl: string | null;
    advisorType: string;
    relationType: 'pembimbing' | 'penguji';
    roleLabel: string;
    contextLabel: string;
    contextDescription: string;
    otherAdvisors: string[];
    stageLabel: string;
    stageDescription: string;
    status: string;
    lastUpdate: string;
    chatUrl: string | null;
    whatsappUrl: string | null;
};

type MahasiswaBimbinganProps = {
    mahasiswaRows: MahasiswaRow[];
    historyRows: MahasiswaRow[];
    activeCount: number;
    relatedCount: number;
    capacityLimit: number;
};

const roleTabs: { label: string; value: RoleFilter }[] = [
    { label: 'Semua', value: 'semua' },
    { label: 'Pembimbing 1', value: 'Pembimbing 1' },
    { label: 'Pembimbing 2', value: 'Pembimbing 2' },
    { label: 'Penguji', value: 'penguji' },
];

function searchUrl(path: string, value: string) {
    return `${path}?search=${encodeURIComponent(value)}`;
}

function RelationBadge({ row }: { row: MahasiswaRow }) {
    return (
        <Badge
            variant="outline"
            className={cn(
                'rounded-full text-xs',
                row.relationType === 'penguji' &&
                    'border-amber-200 bg-amber-50 text-amber-700',
            )}
        >
            {row.roleLabel}
        </Badge>
    );
}

function StudentTable({
    rows,
    emptyText,
    showActions = true,
    queryPrefix,
}: {
    rows: MahasiswaRow[];
    emptyText: string;
    showActions?: boolean;
    queryPrefix: string;
}) {
    const getInitials = useInitials();
    const [search, setSearch] = useUrlState(`${queryPrefix}Search`, '');
    const [roleFilter, setRoleFilter] = useUrlState<RoleFilter>(
        `${queryPrefix}Role`,
        'semua',
    );
    const pageState = useUrlState(`${queryPrefix}Page`, 1);

    function openPrivateChat(row: MahasiswaRow) {
        if (row.studentUserId === null) {
            return;
        }

        router.post(
            '/dosen/pesan/private',
            { recipient_id: row.studentUserId },
            { preserveScroll: true },
        );
    }

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();

        return rows.filter((row) => {
            const matchSearch =
                !q ||
                row.name.toLowerCase().includes(q) ||
                row.nim.toLowerCase().includes(q) ||
                row.stageLabel.toLowerCase().includes(q) ||
                row.roleLabel.toLowerCase().includes(q) ||
                row.contextLabel.toLowerCase().includes(q);
            const matchRole =
                roleFilter === 'semua' ||
                (roleFilter === 'penguji' && row.relationType === 'penguji') ||
                (row.relationType === 'pembimbing' &&
                    row.advisorType === roleFilter);

            return matchSearch && matchRole;
        });
    }, [roleFilter, rows, search]);
    const {
        page,
        setPage,
        pageSize,
        setPageSize,
        totalPages,
        paginated,
        totalItems,
    } = usePagination(
        filtered,
        STUDENT_PAGE_SIZE,
        [search, roleFilter, rows.length],
        pageState,
    );

    return (
        <div className="space-y-3">
            <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                <div className="relative max-w-xs flex-1">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Cari nama atau NIM..."
                        className="h-8 pl-8 text-sm"
                    />
                </div>
                <div className="flex flex-wrap gap-1">
                    {roleTabs.map((tab) => (
                        <button
                            key={tab.value}
                            type="button"
                            onClick={() => setRoleFilter(tab.value)}
                            className={cn(
                                'rounded-full px-3 py-1 text-xs font-medium transition-colors',
                                roleFilter === tab.value
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground',
                            )}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>
            </div>

            {filtered.length > 0 ? (
                <DataTableContainer
                    pagination={
                        <DataTablePagination
                            currentPage={page}
                            totalPages={totalPages}
                            totalItems={totalItems}
                            pageSize={pageSize}
                            onPageChange={setPage}
                            onPageSizeChange={setPageSize}
                            itemLabel="mahasiswa"
                        />
                    }
                >
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/30">
                                <th className="px-4 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                    Mahasiswa
                                </th>
                                <th className="hidden px-4 py-2.5 text-left text-xs font-medium text-muted-foreground md:table-cell">
                                    Peran
                                </th>
                                <th className="hidden px-4 py-2.5 text-left text-xs font-medium text-muted-foreground lg:table-cell">
                                    Tahap
                                </th>
                                <th className="hidden px-4 py-2.5 text-left text-xs font-medium text-muted-foreground xl:table-cell">
                                    Aktivitas Terbaru
                                </th>
                                {showActions && (
                                    <th className="px-4 py-2.5 text-right text-xs font-medium text-muted-foreground">
                                        Aksi
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {paginated.map((row) => (
                                <tr
                                    key={`${row.nim}-${row.relationType}-${row.roleLabel}-${row.contextLabel}`}
                                    className="transition-colors hover:bg-muted/20"
                                >
                                    <td className="px-4 py-3">
                                        <Link
                                            href={row.profileUrl ?? '#'}
                                            className={cn(
                                                'flex items-center gap-2.5',
                                                !row.profileUrl &&
                                                    'pointer-events-none',
                                            )}
                                        >
                                            <Avatar className="size-7 shrink-0 border">
                                                <AvatarImage
                                                    src={
                                                        row.avatar ?? undefined
                                                    }
                                                    alt={row.name}
                                                />
                                                <AvatarFallback className="bg-primary/10 text-[10px] text-primary">
                                                    {getInitials(row.name)}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div className="min-w-0">
                                                <p className="truncate leading-snug font-medium">
                                                    {row.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {row.nim}
                                                </p>
                                                <div className="mt-1 flex flex-wrap gap-1 md:hidden">
                                                    <RelationBadge row={row} />
                                                    <Badge
                                                        variant="secondary"
                                                        className="rounded-full text-xs"
                                                    >
                                                        {row.contextLabel}
                                                    </Badge>
                                                </div>
                                            </div>
                                        </Link>
                                    </td>

                                    <td className="hidden px-4 py-3 md:table-cell">
                                        <div className="flex flex-wrap gap-1.5">
                                            <RelationBadge row={row} />
                                            <Badge
                                                variant="secondary"
                                                className="rounded-full text-xs"
                                            >
                                                {row.contextLabel}
                                            </Badge>
                                        </div>
                                    </td>

                                    <td className="hidden px-4 py-3 lg:table-cell">
                                        <p className="text-xs font-medium">
                                            {row.relationType === 'penguji'
                                                ? row.contextDescription
                                                : row.stageLabel}
                                        </p>
                                        <p className="mt-0.5 max-w-[220px] truncate text-xs text-muted-foreground">
                                            {row.stageDescription}
                                        </p>
                                    </td>

                                    <td className="hidden px-4 py-3 text-xs text-muted-foreground xl:table-cell">
                                        {row.lastUpdate}
                                    </td>

                                    {showActions && (
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1.5">
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                    className="h-7 px-2.5 text-xs"
                                                >
                                                    <Link
                                                        href={searchUrl(
                                                            '/dosen/dokumen-revisi',
                                                            row.nim,
                                                        )}
                                                    >
                                                        <FileText className="size-3.5" />
                                                        Dokumen
                                                    </Link>
                                                </Button>
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                    className="h-7 px-2.5 text-xs"
                                                >
                                                    <Link
                                                        href={searchUrl(
                                                            '/dosen/seminar-proposal',
                                                            row.nim,
                                                        )}
                                                    >
                                                        <CalendarClock className="size-3.5" />
                                                        Ujian
                                                    </Link>
                                                </Button>
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            size="sm"
                                                            className="h-7 px-2.5 text-xs"
                                                            disabled={
                                                                row.studentUserId ===
                                                                    null &&
                                                                row.chatUrl ===
                                                                    null
                                                            }
                                                        >
                                                            <MessageSquareText className="size-3.5" />
                                                            Chat
                                                            <ChevronDown className="size-3" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent
                                                        align="end"
                                                        className="w-48"
                                                    >
                                                        <DropdownMenuLabel>
                                                            Pilih jenis chat
                                                        </DropdownMenuLabel>
                                                        {row.chatUrl ? (
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={
                                                                        row.chatUrl
                                                                    }
                                                                >
                                                                    <MessagesSquare className="size-4" />
                                                                    Chat grup
                                                                </Link>
                                                            </DropdownMenuItem>
                                                        ) : (
                                                            <DropdownMenuItem
                                                                disabled
                                                            >
                                                                <MessagesSquare className="size-4" />
                                                                Chat grup belum
                                                                ada
                                                            </DropdownMenuItem>
                                                        )}
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            disabled={
                                                                row.studentUserId ===
                                                                null
                                                            }
                                                            onClick={() =>
                                                                openPrivateChat(
                                                                    row,
                                                                )
                                                            }
                                                        >
                                                            <MessageSquareText className="size-4" />
                                                            Chat pribadi
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                                {row.whatsappUrl && (
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant="outline"
                                                        className="h-7 px-2.5 text-xs"
                                                    >
                                                        <a
                                                            href={
                                                                row.whatsappUrl
                                                            }
                                                            target="_blank"
                                                            rel="noreferrer"
                                                        >
                                                            <MessageCircle className="size-3.5" />
                                                            WA
                                                        </a>
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </DataTableContainer>
            ) : (
                <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-10 text-center">
                    <UserRound className="mb-2 size-8 text-muted-foreground/40" />
                    <p className="text-sm text-muted-foreground">{emptyText}</p>
                </div>
            )}

            {filtered.length > 0 && (
                <p className="text-right text-xs text-muted-foreground">
                    {filtered.length} dari {rows.length} mahasiswa
                </p>
            )}
        </div>
    );
}

export default function DosenMahasiswaBimbinganPage() {
    const {
        mahasiswaRows,
        historyRows,
        activeCount,
        relatedCount,
        capacityLimit,
    } = usePage<SharedData & MahasiswaBimbinganProps>().props;

    return (
        <DosenLayout
            breadcrumbs={breadcrumbs}
            title="Mahasiswa Dosen"
            subtitle="Daftar mahasiswa bimbingan dan mahasiswa yang Anda uji"
        >
            <Head title="Mahasiswa Dosen" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-10 px-4 py-6 md:px-6 lg:py-8">
                <section>
                    <div className="mb-4 flex items-center justify-between border-b pb-3">
                        <div>
                            <h2 className="text-base font-semibold">
                                Mahasiswa Aktif
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Membimbing{' '}
                                <span className="font-semibold text-foreground">
                                    {activeCount}
                                </span>{' '}
                                dari{' '}
                                <span className="font-semibold text-foreground">
                                    {capacityLimit}
                                </span>{' '}
                                kuota, dengan{' '}
                                <span className="font-semibold text-foreground">
                                    {relatedCount}
                                </span>{' '}
                                relasi mahasiswa aktif.
                            </p>
                        </div>
                    </div>
                    <StudentTable
                        rows={mahasiswaRows}
                        emptyText="Belum ada mahasiswa aktif"
                        showActions
                        queryPrefix="active"
                    />
                </section>

                <section>
                    <div className="mb-4 border-b pb-3">
                        <h2 className="text-base font-semibold">
                            Riwayat Mahasiswa
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Riwayat bimbingan dan ujian yang sudah selesai
                        </p>
                    </div>
                    <StudentTable
                        rows={historyRows}
                        emptyText="Belum ada riwayat mahasiswa"
                        showActions={false}
                        queryPrefix="history"
                    />
                </section>
            </div>
        </DosenLayout>
    );
}
