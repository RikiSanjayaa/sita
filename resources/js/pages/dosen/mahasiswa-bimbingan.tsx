import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    ChevronDown,
    FileText,
    MessageCircle,
    MessageSquareText,
    MessagesSquare,
    MoreHorizontal,
    NotebookPen,
    UserRound,
} from 'lucide-react';
import { type ReactNode, useMemo, useState } from 'react';

import {
    DataSection,
    DataToolbar,
    dataTh,
    stackedTable,
} from '@/components/patterns/data-section';
import { FilterPills } from '@/components/patterns/filter-pills';
import { PageHeader } from '@/components/patterns/page-header';
import { StatusBadge } from '@/components/patterns/status-badge';
import { StickyFilterBar } from '@/components/patterns/sticky-filter-bar';
import {
    type StudentProgress,
    StudentProgressPeek,
} from '@/components/profile/student-progress-peek';
import {
    type ProjectNotes,
    ThesisNotesPanel,
} from '@/components/thesis-notes-panel';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DataTableContainer,
    DataTableEmptyState,
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
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
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
type AttentionFilter = 'semua' | 'any' | 'inactive';

type MahasiswaRow = {
    studentUserId: number | null;
    projectId: number | null;
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
    mentorshipCompleted: number | null;
    stageLabel: string;
    stageDescription: string;
    phase: string | null;
    status: string;
    lastUpdate: string;
    chatUrl: string | null;
    whatsappUrl: string | null;
    progress: StudentProgress | null;
    attention: { key: string; label: string }[];
};

type MahasiswaBimbinganProps = {
    mahasiswaRows: MahasiswaRow[];
    historyRows: MahasiswaRow[];
    activeCount: number;
    relatedCount: number;
    capacityLimit: number;
    projectNotes: Record<string, ProjectNotes>;
};

const roleTabs: { label: string; value: RoleFilter }[] = [
    { label: 'Semua', value: 'semua' },
    { label: 'Pembimbing 1', value: 'Pembimbing 1' },
    { label: 'Pembimbing 2', value: 'Pembimbing 2' },
    { label: 'Penguji', value: 'penguji' },
];

const attentionTabs: { label: string; value: AttentionFilter }[] = [
    { label: 'Semua', value: 'semua' },
    { label: 'Perlu perhatian', value: 'any' },
    { label: 'Tanpa aktivitas 30 hari', value: 'inactive' },
];

function matchesAttention(row: MahasiswaRow, filter: AttentionFilter) {
    return (
        filter === 'semua' ||
        (filter === 'any' && row.attention.length > 0) ||
        row.attention.some((reason) => reason.key === filter)
    );
}

function matchesRole(row: MahasiswaRow, role: RoleFilter) {
    return (
        role === 'semua' ||
        (role === 'penguji' && row.relationType === 'penguji') ||
        (row.relationType === 'pembimbing' && row.advisorType === role)
    );
}

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
                    'border-transparent bg-warning-muted text-warning-muted-foreground',
            )}
        >
            {row.roleLabel}
        </Badge>
    );
}

function StudentTable({
    title,
    summary,
    rows,
    emptyText,
    showActions = true,
    queryPrefix,
    projectNotes,
}: {
    title: string;
    summary: ReactNode;
    rows: MahasiswaRow[];
    emptyText: string;
    showActions?: boolean;
    queryPrefix: string;
    projectNotes: Record<string, ProjectNotes>;
}) {
    const getInitials = useInitials();
    const [notesRow, setNotesRow] = useState<MahasiswaRow | null>(null);
    const [search, setSearch] = useUrlState(`${queryPrefix}Search`, '');
    const [roleFilter, setRoleFilter] = useUrlState<RoleFilter>(
        `${queryPrefix}Role`,
        'semua',
    );
    const [attentionFilter, setAttentionFilter] = useUrlState<AttentionFilter>(
        `${queryPrefix}Attention`,
        'semua',
    );
    const pageState = useUrlState(`${queryPrefix}Page`, 1);
    const hasAttention = rows.some((row) => row.attention.length > 0);

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

            return (
                matchSearch &&
                matchesRole(row, roleFilter) &&
                matchesAttention(row, attentionFilter)
            );
        });
    }, [attentionFilter, roleFilter, rows, search]);
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
        [search, roleFilter, attentionFilter, rows.length],
        pageState,
    );

    return (
        <DataSection title={title} summary={summary}>
            <StickyFilterBar>
                <DataToolbar
                    search={search}
                    onSearchChange={setSearch}
                    placeholder="Cari nama atau NIM..."
                />

                <FilterPills
                    label={`Peran, ${title.toLowerCase()}`}
                    value={roleFilter}
                    onValueChange={setRoleFilter}
                    itemClassName="max-md:h-11 max-md:px-4"
                    options={roleTabs.map((tab) => ({
                        ...tab,
                        count: rows.filter((row) => matchesRole(row, tab.value))
                            .length,
                    }))}
                />

                {hasAttention ? (
                    <FilterPills
                        label={`Perhatian, ${title.toLowerCase()}`}
                        value={attentionFilter}
                        onValueChange={setAttentionFilter}
                        itemClassName="max-md:h-11 max-md:px-4"
                        options={attentionTabs.map((tab) => ({
                            ...tab,
                            count: rows.filter((row) =>
                                matchesAttention(row, tab.value),
                            ).length,
                        }))}
                    />
                ) : null}
            </StickyFilterBar>

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
                    <table
                        className={cn(
                            stackedTable.table,
                            showActions
                                ? 'md:min-w-[940px]'
                                : 'md:min-w-[820px]',
                        )}
                    >
                        <thead className={stackedTable.thead}>
                            <tr className="border-b bg-muted/40">
                                <th className={dataTh}>Mahasiswa</th>
                                <th className={dataTh}>Peran</th>
                                <th className={dataTh}>Tahap</th>
                                <th className={dataTh}>Bimbingan</th>
                                <th className={dataTh}>Aktivitas Terbaru</th>
                                {showActions && (
                                    <th className={dataTh}>
                                        <span className="sr-only">Aksi</span>
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody
                            className={cn(stackedTable.tbody, 'md:divide-y')}
                        >
                            {paginated.map((row) => (
                                <tr
                                    key={`${row.nim}-${row.relationType}-${row.roleLabel}-${row.contextLabel}`}
                                    className={cn(stackedTable.tr, 'align-top')}
                                >
                                    <td className={stackedTable.tdPlain}>
                                        <Link
                                            href={row.profileUrl ?? '#'}
                                            className={cn(
                                                'flex items-center gap-3 rounded-md max-md:min-h-11',
                                                !row.profileUrl &&
                                                    'pointer-events-none',
                                            )}
                                        >
                                            <Avatar className="size-8 shrink-0 border">
                                                <AvatarImage
                                                    src={
                                                        row.avatar ?? undefined
                                                    }
                                                    alt={row.name}
                                                />
                                                <AvatarFallback className="bg-primary-muted text-xs text-primary-muted-foreground">
                                                    {getInitials(row.name)}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div className="min-w-0">
                                                <p className="leading-snug font-medium">
                                                    {row.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground tabular-nums">
                                                    {row.nim}
                                                </p>
                                            </div>
                                        </Link>
                                        {row.attention.length > 0 ? (
                                            <ul
                                                aria-label="Perlu perhatian"
                                                className="mt-2 flex flex-wrap gap-1"
                                            >
                                                {row.attention.map((reason) => (
                                                    <li key={reason.key}>
                                                        <StatusBadge
                                                            domain="ticket"
                                                            status="open"
                                                        >
                                                            {reason.label}
                                                        </StatusBadge>
                                                    </li>
                                                ))}
                                            </ul>
                                        ) : null}
                                    </td>

                                    <td
                                        className={cn(
                                            stackedTable.td,
                                            'max-md:flex max-md:items-center max-md:gap-1.5',
                                        )}
                                    >
                                        <RelationBadge row={row} />
                                        <p className="mt-1 text-xs text-muted-foreground max-md:mt-0">
                                            {row.contextLabel}
                                        </p>
                                    </td>

                                    <td className={stackedTable.tdDetail}>
                                        <div className="flex min-w-0 flex-col items-start gap-1">
                                            <StatusBadge
                                                domain="phase"
                                                status={row.phase}
                                            >
                                                {row.relationType === 'penguji'
                                                    ? row.contextDescription
                                                    : row.stageLabel}
                                            </StatusBadge>
                                            <p
                                                className="text-xs text-pretty text-muted-foreground max-md:line-clamp-2"
                                                title={row.stageDescription}
                                            >
                                                {row.stageDescription}
                                            </p>
                                            {row.progress ? (
                                                <StudentProgressPeek
                                                    progress={row.progress}
                                                    className="mt-1"
                                                    hideActivityOnPhone
                                                />
                                            ) : null}
                                        </div>
                                    </td>

                                    <td
                                        data-label="Bimbingan"
                                        data-label-hidden
                                        className={cn(
                                            stackedTable.td,
                                            'tabular-nums',
                                        )}
                                    >
                                        {row.mentorshipCompleted === null ? (
                                            <span className="text-muted-foreground">
                                                -
                                            </span>
                                        ) : (
                                            `${row.mentorshipCompleted} bimbingan`
                                        )}
                                    </td>

                                    <td
                                        data-label="Aktivitas Terbaru"
                                        className={cn(
                                            stackedTable.td,
                                            'text-muted-foreground',
                                        )}
                                    >
                                        {row.lastUpdate}
                                    </td>

                                    {showActions && (
                                        <td className={stackedTable.tdActions}>
                                            <div className="flex items-center justify-end gap-1 max-md:gap-2">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            size="sm"
                                                            className="max-md:h-11 max-md:px-4"
                                                            disabled={
                                                                row.studentUserId ===
                                                                    null &&
                                                                row.chatUrl ===
                                                                    null
                                                            }
                                                        >
                                                            <MessageSquareText className="size-4" />
                                                            Chat
                                                            <ChevronDown className="size-3.5" />
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
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="icon"
                                                            className="max-md:size-11 md:size-8"
                                                            aria-label="Aksi lainnya"
                                                        >
                                                            <MoreHorizontal className="size-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent
                                                        align="end"
                                                        className="w-44"
                                                    >
                                                        <DropdownMenuItem
                                                            disabled={
                                                                row.projectId ===
                                                                null
                                                            }
                                                            onSelect={() =>
                                                                setNotesRow(row)
                                                            }
                                                        >
                                                            <NotebookPen className="size-4" />
                                                            Catatan
                                                            {row.projectId !==
                                                                null &&
                                                                (projectNotes[
                                                                    row
                                                                        .projectId
                                                                ]?.notes
                                                                    .length ??
                                                                    0) > 0 && (
                                                                    <span className="tabular-nums">
                                                                        (
                                                                        {
                                                                            projectNotes[
                                                                                row
                                                                                    .projectId
                                                                            ]
                                                                                .notes
                                                                                .length
                                                                        }
                                                                        )
                                                                    </span>
                                                                )}
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            asChild
                                                        >
                                                            <Link
                                                                href={searchUrl(
                                                                    '/dosen/dokumen-revisi',
                                                                    row.nim,
                                                                )}
                                                            >
                                                                <FileText className="size-4" />
                                                                Dokumen
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            asChild
                                                        >
                                                            <Link
                                                                href={searchUrl(
                                                                    '/dosen/seminar-proposal',
                                                                    row.nim,
                                                                )}
                                                            >
                                                                <CalendarClock className="size-4" />
                                                                Ujian
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        {row.whatsappUrl && (
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <a
                                                                    href={
                                                                        row.whatsappUrl
                                                                    }
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                >
                                                                    <MessageCircle className="size-4" />
                                                                    WA
                                                                </a>
                                                            </DropdownMenuItem>
                                                        )}
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </div>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </DataTableContainer>
            ) : (
                <DataTableEmptyState
                    icon={UserRound}
                    title={
                        rows.length === 0
                            ? emptyText
                            : 'Tidak ada mahasiswa yang sesuai'
                    }
                    description={
                        rows.length === 0
                            ? undefined
                            : 'Coba ubah kata kunci atau filter yang dipilih.'
                    }
                />
            )}

            <Sheet
                open={notesRow !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setNotesRow(null);
                    }
                }}
            >
                <SheetContent
                    side="right"
                    className="w-full gap-0 overflow-y-auto p-0 sm:max-w-xl"
                >
                    <SheetHeader className="border-b px-6 py-4">
                        <SheetTitle>Catatan</SheetTitle>
                        <SheetDescription>
                            {notesRow?.name} ({notesRow?.nim})
                        </SheetDescription>
                    </SheetHeader>
                    <div className="p-6">
                        <ThesisNotesPanel
                            projectId={notesRow?.projectId ?? null}
                            data={
                                notesRow?.projectId == null
                                    ? undefined
                                    : projectNotes[notesRow.projectId]
                            }
                        />
                    </div>
                </SheetContent>
            </Sheet>
        </DataSection>
    );
}

export default function DosenMahasiswaBimbinganPage() {
    const {
        mahasiswaRows,
        historyRows,
        activeCount,
        relatedCount,
        capacityLimit,
        projectNotes,
    } = usePage<SharedData & MahasiswaBimbinganProps>().props;

    return (
        <DosenLayout
            breadcrumbs={breadcrumbs}
            title="Mahasiswa Dosen"
            subtitle="Daftar mahasiswa bimbingan dan mahasiswa yang Anda uji"
        >
            <Head title="Mahasiswa Dosen" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-6 lg:py-8">
                <PageHeader
                    title="Mahasiswa Dosen"
                    description="Daftar mahasiswa bimbingan dan mahasiswa yang Anda uji"
                />

                <div className="flex flex-col gap-10">
                    <StudentTable
                        title="Mahasiswa Aktif"
                        summary={`Membimbing ${activeCount} dari ${capacityLimit} kuota, dengan ${relatedCount} relasi mahasiswa aktif.`}
                        rows={mahasiswaRows}
                        emptyText="Belum ada mahasiswa aktif"
                        showActions
                        queryPrefix="active"
                        projectNotes={projectNotes}
                    />

                    <StudentTable
                        title="Riwayat Mahasiswa"
                        summary="Riwayat bimbingan dan ujian yang sudah selesai"
                        rows={historyRows}
                        emptyText="Belum ada riwayat mahasiswa"
                        showActions={false}
                        queryPrefix="history"
                        projectNotes={projectNotes}
                    />
                </div>
            </div>
        </DosenLayout>
    );
}
