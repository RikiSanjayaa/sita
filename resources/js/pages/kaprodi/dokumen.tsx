import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ChevronRight,
    Download,
    FileText,
    UserRound,
    UsersRound,
} from 'lucide-react';
import { useMemo, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DataTableContainer,
    DataTableEmptyState,
    DataTablePagination,
    DataTableToolbar,
    type FilterGroup,
    usePagination,
} from '@/components/ui/data-table';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import KaprodiLayout from '@/layouts/kaprodi-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/kaprodi/dashboard' },
    { title: 'Dokumen', href: '/kaprodi/dokumen' },
];

const PAGE_SIZE = 15;

type ProgramStudi = {
    name: string;
};

type DocumentReviewItem = {
    id: number;
    reviewer: string;
    status: string;
    revisionNotes: string | null;
    uploadedAt: string;
    reviewedAt: string | null;
};

type DocumentQueueItem = {
    id: string;
    source: 'Workspace' | 'Tugas Akhir';
    mahasiswa: string;
    nim: string | null;
    title: string;
    file: string | null;
    uploadedAt: string;
    status: string;
    reviewCount: number;
    pendingCount: number;
    revisionCount: number;
    approvedCount: number;
    fileUrl: string | null;
    profileUrl: string;
    reviews: DocumentReviewItem[];
};

type DokumenProps = {
    programStudi: ProgramStudi;
    documentQueue: DocumentQueueItem[];
};

type StatusFilter = 'semua' | 'Perlu Review' | 'Perlu Revisi' | 'Disetujui';

export default function KaprodiDokumenPage() {
    const { programStudi, documentQueue } = usePage<SharedData & DokumenProps>()
        .props;
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState<StatusFilter>('semua');
    const [selectedDocument, setSelectedDocument] =
        useState<DocumentQueueItem | null>(null);

    const filteredDocuments = useMemo(() => {
        const lower = search.toLowerCase();

        return documentQueue.filter((doc) => {
            const matchesStatus =
                statusFilter === 'semua' || doc.status === statusFilter;
            const matchesSearch =
                lower === '' ||
                doc.mahasiswa.toLowerCase().includes(lower) ||
                (doc.nim ?? '').toLowerCase().includes(lower) ||
                (doc.file ?? '').toLowerCase().includes(lower) ||
                doc.title.toLowerCase().includes(lower) ||
                doc.reviews.some(
                    (review) =>
                        review.reviewer.toLowerCase().includes(lower) ||
                        review.status.toLowerCase().includes(lower) ||
                        (review.revisionNotes ?? '')
                            .toLowerCase()
                            .includes(lower),
                );

            return matchesStatus && matchesSearch;
        });
    }, [documentQueue, search, statusFilter]);

    const { page, setPage, totalPages, paginated, totalItems } = usePagination(
        filteredDocuments,
        PAGE_SIZE,
        [search, statusFilter],
    );

    const statusCounts = useMemo(
        () => ({
            review: documentQueue.filter((d) => d.status === 'Perlu Review')
                .length,
            revisi: documentQueue.filter((d) => d.status === 'Perlu Revisi')
                .length,
            disetujui: documentQueue.filter((d) => d.status === 'Disetujui')
                .length,
        }),
        [documentQueue],
    );

    const filterTabs: FilterGroup[] = [
        {
            value: statusFilter,
            onChange: (value) => setStatusFilter(value as StatusFilter),
            tabs: [
                { label: 'Semua', value: 'semua' },
                {
                    label: 'Perlu Review',
                    value: 'Perlu Review',
                    count: statusCounts.review,
                },
                {
                    label: 'Perlu Revisi',
                    value: 'Perlu Revisi',
                    count: statusCounts.revisi,
                },
                {
                    label: 'Disetujui',
                    value: 'Disetujui',
                    count: statusCounts.disetujui,
                },
            ],
        },
    ];

    return (
        <KaprodiLayout
            breadcrumbs={breadcrumbs}
            title="Dokumen"
            subtitle={`Monitoring dokumen mahasiswa ${programStudi.name}`}
        >
            <Head title="Dokumen Kaprodi" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6 lg:gap-8 lg:py-8">
                <section>
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h2 className="text-base font-semibold">
                                Daftar Dokumen
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Semua upload mahasiswa prodi, termasuk status
                                review dosen.
                            </p>
                        </div>
                        {statusCounts.review > 0 ? (
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-600/10 px-3 py-1 text-xs font-bold text-amber-700 dark:text-amber-400">
                                <AlertTriangle className="size-3" />
                                {statusCounts.review} perlu review
                            </span>
                        ) : null}
                    </div>

                    <DataTableToolbar
                        search={search}
                        onSearchChange={setSearch}
                        searchPlaceholder="Cari mahasiswa, NIM, file, atau dosen..."
                        filterGroups={filterTabs}
                        className="mb-3"
                    />

                    {totalItems > 0 ? (
                        <DataTableContainer>
                            <table className="w-full text-left text-sm">
                                <thead className="border-b bg-muted/30 text-xs text-muted-foreground">
                                    <tr>
                                        <th className="px-5 py-3 font-medium">
                                            Mahasiswa
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            File / Judul
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Sumber
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Status
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Waktu Upload
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Review Dosen
                                        </th>
                                        <th className="px-5 py-3 text-right font-medium">
                                            Aksi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y bg-background">
                                    {paginated.map((doc) => {
                                        const isPending =
                                            doc.status === 'Perlu Review';

                                        return (
                                            <tr
                                                key={doc.id}
                                                role="button"
                                                tabIndex={0}
                                                className={cn(
                                                    'cursor-pointer transition-colors hover:bg-muted/20',
                                                    isPending &&
                                                        'bg-amber-50/40 dark:bg-amber-950/10',
                                                )}
                                                onClick={() =>
                                                    setSelectedDocument(doc)
                                                }
                                                onKeyDown={(event) => {
                                                    if (
                                                        event.key === 'Enter' ||
                                                        event.key === ' '
                                                    ) {
                                                        event.preventDefault();
                                                        setSelectedDocument(
                                                            doc,
                                                        );
                                                    }
                                                }}
                                            >
                                                <td className="px-5 py-3.5 align-middle">
                                                    <Link
                                                        href={doc.profileUrl}
                                                        onClick={(event) =>
                                                            event.stopPropagation()
                                                        }
                                                        className="text-[14px] font-semibold hover:text-primary"
                                                    >
                                                        {doc.mahasiswa}
                                                    </Link>
                                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                                        {doc.nim ?? '-'}
                                                    </p>
                                                </td>

                                                <td className="px-5 py-3.5 align-middle">
                                                    <div className="flex min-w-0 items-center gap-2.5">
                                                        <div className="flex size-8 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                                                            <FileText className="size-4" />
                                                        </div>
                                                        <div className="min-w-0">
                                                            <div className="max-w-[220px] truncate text-sm font-medium">
                                                                {doc.title}
                                                            </div>
                                                            <div className="max-w-[220px] truncate text-xs text-muted-foreground">
                                                                {doc.file ??
                                                                    '-'}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>

                                                <td className="px-5 py-3.5 align-middle">
                                                    <Badge variant="outline">
                                                        {doc.source}
                                                    </Badge>
                                                </td>

                                                <td className="px-5 py-3.5 align-middle">
                                                    <Badge
                                                        variant="soft"
                                                        className={cn(
                                                            'whitespace-nowrap',
                                                            doc.status ===
                                                                'Disetujui'
                                                                ? 'bg-emerald-600/10 text-emerald-700 dark:text-emerald-400'
                                                                : doc.status ===
                                                                    'Perlu Revisi'
                                                                  ? 'bg-amber-600/10 text-amber-700 dark:text-amber-400'
                                                                  : 'bg-muted text-muted-foreground',
                                                        )}
                                                    >
                                                        {doc.status}
                                                    </Badge>
                                                </td>

                                                <td className="px-5 py-3.5 align-middle text-xs whitespace-nowrap text-muted-foreground">
                                                    {doc.uploadedAt}
                                                </td>

                                                <td className="px-5 py-3.5 align-middle">
                                                    <div className="flex flex-wrap items-center gap-1.5">
                                                        <Badge
                                                            variant="outline"
                                                            className="gap-1"
                                                        >
                                                            <UsersRound className="size-3" />
                                                            {doc.reviewCount}{' '}
                                                            review
                                                        </Badge>
                                                        {doc.pendingCount >
                                                        0 ? (
                                                            <Badge
                                                                variant="soft"
                                                                className="bg-muted text-muted-foreground"
                                                            >
                                                                {
                                                                    doc.pendingCount
                                                                }{' '}
                                                                menunggu
                                                            </Badge>
                                                        ) : null}
                                                        {doc.revisionCount >
                                                        0 ? (
                                                            <Badge
                                                                variant="soft"
                                                                className="bg-amber-600/10 text-amber-700 dark:text-amber-400"
                                                            >
                                                                {
                                                                    doc.revisionCount
                                                                }{' '}
                                                                revisi
                                                            </Badge>
                                                        ) : null}
                                                        {doc.approvedCount >
                                                        0 ? (
                                                            <Badge
                                                                variant="soft"
                                                                className="bg-emerald-600/10 text-emerald-700 dark:text-emerald-400"
                                                            >
                                                                {
                                                                    doc.approvedCount
                                                                }{' '}
                                                                disetujui
                                                            </Badge>
                                                        ) : null}
                                                    </div>
                                                </td>

                                                <td className="px-5 py-3.5 align-middle">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                asChild
                                                            >
                                                                <Button
                                                                    size="icon"
                                                                    variant="ghost"
                                                                    className="size-8 text-muted-foreground hover:text-foreground"
                                                                    disabled={
                                                                        !doc.fileUrl
                                                                    }
                                                                    onClick={(
                                                                        event,
                                                                    ) => {
                                                                        event.stopPropagation();
                                                                        if (
                                                                            doc.fileUrl
                                                                        ) {
                                                                            window.open(
                                                                                doc.fileUrl,
                                                                                '_blank',
                                                                                'noopener,noreferrer',
                                                                            );
                                                                        }
                                                                    }}
                                                                >
                                                                    <Download className="size-4" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                Unduh Dokumen
                                                            </TooltipContent>
                                                        </Tooltip>
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                asChild
                                                            >
                                                                <Button
                                                                    asChild
                                                                    size="icon"
                                                                    variant="ghost"
                                                                    className="size-8 text-muted-foreground hover:text-foreground"
                                                                >
                                                                    <Link
                                                                        href={
                                                                            doc.profileUrl
                                                                        }
                                                                        onClick={(
                                                                            event,
                                                                        ) =>
                                                                            event.stopPropagation()
                                                                        }
                                                                    >
                                                                        <UserRound className="size-4" />
                                                                    </Link>
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                Buka Profil
                                                            </TooltipContent>
                                                        </Tooltip>
                                                        <ChevronRight className="size-4 text-muted-foreground" />
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                            <DataTablePagination
                                currentPage={page}
                                totalPages={totalPages}
                                totalItems={totalItems}
                                pageSize={PAGE_SIZE}
                                onPageChange={setPage}
                                itemLabel="dokumen"
                            />
                        </DataTableContainer>
                    ) : (
                        <DataTableEmptyState
                            icon={FileText}
                            title={
                                search || statusFilter !== 'semua'
                                    ? 'Tidak ada dokumen yang sesuai'
                                    : 'Belum ada dokumen'
                            }
                            description={
                                search || statusFilter !== 'semua'
                                    ? 'Coba ubah kata kunci atau filter yang dipilih.'
                                    : 'Upload mahasiswa prodi akan muncul di sini.'
                            }
                        />
                    )}
                </section>
            </div>

            <Sheet
                open={selectedDocument !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelectedDocument(null);
                    }
                }}
            >
                <SheetContent
                    side="right"
                    className="w-full gap-0 p-0 sm:max-w-xl"
                >
                    {selectedDocument ? (
                        <>
                            <SheetHeader className="border-b bg-muted/20 px-6 py-4">
                                <div className="flex items-start gap-3 pr-8">
                                    <div className="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                                        <FileText className="size-5" />
                                    </div>
                                    <div className="min-w-0">
                                        <SheetTitle className="line-clamp-2 text-base">
                                            {selectedDocument.title}
                                        </SheetTitle>
                                        <SheetDescription className="mt-1">
                                            {selectedDocument.mahasiswa} -{' '}
                                            {selectedDocument.file ?? 'Dokumen'}
                                        </SheetDescription>
                                    </div>
                                </div>
                            </SheetHeader>

                            <ScrollArea className="h-[calc(100vh-5.5rem)]">
                                <div className="space-y-5 px-6 py-5">
                                    <div className="rounded-lg border bg-background">
                                        <div className="grid gap-4 p-4 sm:grid-cols-2">
                                            <div>
                                                <p className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                                    Sumber
                                                </p>
                                                <p className="mt-1 text-sm font-medium">
                                                    {selectedDocument.source}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                                    Upload
                                                </p>
                                                <p className="mt-1 text-sm font-medium">
                                                    {
                                                        selectedDocument.uploadedAt
                                                    }
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                                    Status
                                                </p>
                                                <Badge
                                                    variant="soft"
                                                    className={cn(
                                                        'mt-1',
                                                        selectedDocument.status ===
                                                            'Disetujui'
                                                            ? 'bg-emerald-600/10 text-emerald-700 dark:text-emerald-400'
                                                            : selectedDocument.status ===
                                                                'Perlu Revisi'
                                                              ? 'bg-amber-600/10 text-amber-700 dark:text-amber-400'
                                                              : 'bg-muted text-muted-foreground',
                                                    )}
                                                >
                                                    {selectedDocument.status}
                                                </Badge>
                                            </div>
                                            <div>
                                                <p className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                                    Review
                                                </p>
                                                <p className="mt-1 text-sm font-medium">
                                                    {
                                                        selectedDocument.reviewCount
                                                    }{' '}
                                                    dosen
                                                </p>
                                            </div>
                                        </div>

                                        <div className="flex flex-wrap gap-2 border-t px-4 py-3">
                                            {selectedDocument.fileUrl ? (
                                                <Button
                                                    size="sm"
                                                    onClick={() => {
                                                        const fileUrl =
                                                            selectedDocument.fileUrl;

                                                        if (!fileUrl) {
                                                            return;
                                                        }

                                                        window.open(
                                                            fileUrl,
                                                            '_blank',
                                                            'noopener,noreferrer',
                                                        );
                                                    }}
                                                >
                                                    <Download className="size-4" />
                                                    Unduh Dokumen
                                                </Button>
                                            ) : null}
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Link
                                                    href={
                                                        selectedDocument.profileUrl
                                                    }
                                                >
                                                    <UserRound className="size-4" />
                                                    Buka Profil Mahasiswa
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>

                                    <div>
                                        <h3 className="text-sm font-semibold">
                                            Detail Review Dosen
                                        </h3>
                                        <p className="text-sm text-muted-foreground">
                                            Catatan dan status dari tiap dosen
                                            reviewer untuk dokumen ini.
                                        </p>
                                    </div>

                                    <div className="space-y-3">
                                        {selectedDocument.reviews.map(
                                            (review) => (
                                                <article
                                                    key={review.id}
                                                    className="rounded-lg border bg-background p-4"
                                                >
                                                    <div className="flex items-start justify-between gap-3">
                                                        <div className="min-w-0">
                                                            <p className="truncate text-sm font-semibold">
                                                                {
                                                                    review.reviewer
                                                                }
                                                            </p>
                                                            <p className="mt-1 text-xs text-muted-foreground">
                                                                Diupload:{' '}
                                                                {
                                                                    review.uploadedAt
                                                                }
                                                            </p>
                                                        </div>
                                                        <Badge
                                                            variant="soft"
                                                            className={cn(
                                                                'shrink-0',
                                                                review.status ===
                                                                    'Disetujui'
                                                                    ? 'bg-emerald-600/10 text-emerald-700 dark:text-emerald-400'
                                                                    : review.status ===
                                                                        'Perlu Revisi'
                                                                      ? 'bg-amber-600/10 text-amber-700 dark:text-amber-400'
                                                                      : 'bg-muted text-muted-foreground',
                                                            )}
                                                        >
                                                            {review.status}
                                                        </Badge>
                                                    </div>

                                                    {review.reviewedAt ? (
                                                        <p className="mt-3 text-xs text-muted-foreground">
                                                            Direview:{' '}
                                                            {review.reviewedAt}
                                                        </p>
                                                    ) : null}

                                                    <div className="mt-3 rounded-md bg-muted/30 p-3">
                                                        <p className="text-xs leading-relaxed text-muted-foreground">
                                                            {review.revisionNotes ??
                                                                'Belum ada catatan review.'}
                                                        </p>
                                                    </div>
                                                </article>
                                            ),
                                        )}
                                    </div>
                                </div>
                            </ScrollArea>
                        </>
                    ) : null}
                </SheetContent>
            </Sheet>
        </KaprodiLayout>
    );
}
