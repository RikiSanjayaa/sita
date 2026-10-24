import { Head, Link, usePage } from '@inertiajs/react';
import { Download, FileText, UserRound } from 'lucide-react';
import { useMemo, useState } from 'react';

import { RowIconButton } from '@/components/kaprodi/row-icon-button';
import {
    DataSection,
    DataToolbar,
    dataTh,
    stackedTable,
} from '@/components/patterns/data-section';
import { FilterPills } from '@/components/patterns/filter-pills';
import { StatusBadge } from '@/components/patterns/status-badge';
import { StickyFilterBar } from '@/components/patterns/sticky-filter-bar';
import { Button } from '@/components/ui/button';
import {
    DataTableContainer,
    DataTableEmptyState,
    DataTablePagination,
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
import { useUrlState } from '@/hooks/use-url-state';
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

/** The backend sends localized status labels; the badge tone needs the key. */
const documentStatusKeys: Record<string, string> = {
    'Perlu Review': 'submitted',
    'Perlu Revisi': 'needs_revision',
    Disetujui: 'approved',
    Aktif: 'active',
};

function DocumentStatusBadge({
    status,
    className,
}: {
    status: string;
    className?: string;
}) {
    return (
        <StatusBadge
            domain="document"
            status={documentStatusKeys[status]}
            className={cn('whitespace-nowrap', className)}
        >
            {status}
        </StatusBadge>
    );
}

function openDocument(fileUrl: string | null) {
    if (fileUrl) {
        window.open(fileUrl, '_blank', 'noopener,noreferrer');
    }
}

function reviewSummary(doc: DocumentQueueItem): string {
    return [
        doc.pendingCount > 0 ? `${doc.pendingCount} menunggu` : null,
        doc.revisionCount > 0 ? `${doc.revisionCount} revisi` : null,
        doc.approvedCount > 0 ? `${doc.approvedCount} disetujui` : null,
    ]
        .filter((part): part is string => part !== null)
        .join(', ');
}

export default function KaprodiDokumenPage() {
    const { auth, programStudi, documentQueue } = usePage<
        SharedData & DokumenProps
    >().props;
    const canDownloadDocuments =
        auth.kaprodiCapabilities?.download_documents ?? true;
    const [search, setSearch] = useUrlState('search', '');
    const [statusFilter, setStatusFilter] = useUrlState<StatusFilter>(
        'status',
        'semua',
    );
    const pageState = useUrlState('page', 1);
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

    const {
        page,
        setPage,
        pageSize,
        setPageSize,
        totalPages,
        paginated,
        totalItems,
    } = usePagination(
        filteredDocuments,
        PAGE_SIZE,
        [search, statusFilter],
        pageState,
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

    return (
        <KaprodiLayout
            breadcrumbs={breadcrumbs}
            title="Dokumen"
            subtitle={`Monitoring dokumen mahasiswa ${programStudi.name}`}
        >
            <Head title="Dokumen Kaprodi" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6 lg:py-8">
                <DataSection
                    level={1}
                    title="Daftar Dokumen"
                    description={`Monitoring dokumen mahasiswa ${programStudi.name}, termasuk status review dosen.`}
                >
                    <StickyFilterBar>
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            placeholder="Cari mahasiswa, NIM, file, atau dosen..."
                        />

                        <FilterPills
                            label="Filter status dokumen"
                            value={statusFilter}
                            onValueChange={setStatusFilter}
                            itemClassName="max-md:h-11 max-md:px-4"
                            options={[
                                {
                                    label: 'Semua',
                                    value: 'semua',
                                    count: documentQueue.length,
                                },
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
                            ]}
                        />
                    </StickyFilterBar>

                    {totalItems > 0 ? (
                        <DataTableContainer
                            pagination={
                                <DataTablePagination
                                    currentPage={page}
                                    totalPages={totalPages}
                                    totalItems={totalItems}
                                    pageSize={pageSize}
                                    onPageChange={setPage}
                                    onPageSizeChange={setPageSize}
                                    itemLabel="dokumen"
                                />
                            }
                        >
                            <table
                                className={cn(
                                    stackedTable.table,
                                    'md:min-w-[860px]',
                                )}
                            >
                                <thead className={stackedTable.thead}>
                                    <tr className="border-b bg-muted/40">
                                        <th className={dataTh}>Mahasiswa</th>
                                        <th className={dataTh}>Dokumen</th>
                                        <th className={dataTh}>Status</th>
                                        <th className={dataTh}>Waktu upload</th>
                                        <th className={dataTh}>Review dosen</th>
                                        <th className={dataTh}>
                                            <span className="sr-only">
                                                Aksi
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    className={cn(
                                        stackedTable.tbody,
                                        'md:divide-y',
                                    )}
                                >
                                    {paginated.map((doc) => (
                                        <tr
                                            key={doc.id}
                                            className={cn(
                                                stackedTable.tr,
                                                'cursor-pointer align-top transition-colors hover:bg-muted/30',
                                            )}
                                            onClick={() =>
                                                setSelectedDocument(doc)
                                            }
                                        >
                                            <td
                                                className={stackedTable.tdPlain}
                                            >
                                                <Link
                                                    href={doc.profileUrl}
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                    className="font-medium hover:text-primary"
                                                >
                                                    {doc.mahasiswa}
                                                </Link>
                                                <p className="text-muted-foreground tabular-nums">
                                                    {doc.nim ?? '-'}
                                                </p>
                                            </td>

                                            <td
                                                className={cn(
                                                    stackedTable.tdDetail,
                                                    'md:max-w-xs',
                                                )}
                                            >
                                                <button
                                                    type="button"
                                                    onClick={(event) => {
                                                        event.stopPropagation();
                                                        setSelectedDocument(
                                                            doc,
                                                        );
                                                    }}
                                                    title={doc.title}
                                                    className="line-clamp-2 rounded-sm text-left font-medium hover:text-primary focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                                >
                                                    {doc.title}
                                                </button>
                                                <p
                                                    className="truncate text-xs text-muted-foreground"
                                                    title={`${doc.source}${doc.file ? ` · ${doc.file}` : ''}`}
                                                >
                                                    {doc.source}
                                                    {doc.file
                                                        ? ` · ${doc.file}`
                                                        : ''}
                                                </p>
                                            </td>

                                            <td
                                                className={stackedTable.tdAside}
                                            >
                                                <DocumentStatusBadge
                                                    status={doc.status}
                                                />
                                            </td>

                                            <td
                                                className={cn(
                                                    stackedTable.td,
                                                    'text-muted-foreground md:whitespace-nowrap',
                                                )}
                                            >
                                                {doc.uploadedAt}
                                            </td>

                                            <td className={stackedTable.td}>
                                                <p className="tabular-nums max-md:inline">
                                                    {doc.reviewCount} review
                                                </p>
                                                {reviewSummary(doc) ? (
                                                    <p className="text-xs text-muted-foreground max-md:ml-1 max-md:inline">
                                                        {reviewSummary(doc)}
                                                    </p>
                                                ) : null}
                                            </td>

                                            <td
                                                className={
                                                    stackedTable.tdActions
                                                }
                                            >
                                                <div
                                                    className="flex items-center justify-end gap-1"
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    {canDownloadDocuments ? (
                                                        <RowIconButton
                                                            label="Unduh dokumen"
                                                            disabled={
                                                                !doc.fileUrl
                                                            }
                                                            onClick={() =>
                                                                openDocument(
                                                                    doc.fileUrl,
                                                                )
                                                            }
                                                        >
                                                            <Download className="size-4" />
                                                        </RowIconButton>
                                                    ) : null}
                                                    <RowIconButton
                                                        asChild
                                                        label="Buka profil mahasiswa"
                                                    >
                                                        <Link
                                                            href={
                                                                doc.profileUrl
                                                            }
                                                        >
                                                            <UserRound className="size-4" />
                                                        </Link>
                                                    </RowIconButton>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
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
                </DataSection>
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
                                    <div className="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary-muted text-primary-muted-foreground">
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

                            <ScrollArea className="h-[calc(100dvh-5.5rem)]">
                                <div className="space-y-5 px-6 py-5">
                                    <div className="rounded-lg border bg-background">
                                        <dl className="grid gap-4 p-4 sm:grid-cols-2">
                                            <div>
                                                <dt className="text-xs font-medium text-muted-foreground">
                                                    Sumber
                                                </dt>
                                                <dd className="mt-1 text-sm font-medium">
                                                    {selectedDocument.source}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-xs font-medium text-muted-foreground">
                                                    Upload
                                                </dt>
                                                <dd className="mt-1 text-sm font-medium">
                                                    {
                                                        selectedDocument.uploadedAt
                                                    }
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-xs font-medium text-muted-foreground">
                                                    Status
                                                </dt>
                                                <dd className="mt-1">
                                                    <DocumentStatusBadge
                                                        status={
                                                            selectedDocument.status
                                                        }
                                                    />
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-xs font-medium text-muted-foreground">
                                                    Review
                                                </dt>
                                                <dd className="mt-1 text-sm font-medium tabular-nums">
                                                    {
                                                        selectedDocument.reviewCount
                                                    }{' '}
                                                    dosen
                                                </dd>
                                            </div>
                                        </dl>

                                        <div className="flex flex-wrap gap-2 border-t px-4 py-3">
                                            {canDownloadDocuments &&
                                            selectedDocument.fileUrl ? (
                                                <Button
                                                    size="sm"
                                                    className="max-md:h-11"
                                                    onClick={() =>
                                                        openDocument(
                                                            selectedDocument.fileUrl,
                                                        )
                                                    }
                                                >
                                                    <Download className="size-4" />
                                                    Unduh Dokumen
                                                </Button>
                                            ) : null}
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                                className="max-md:h-11"
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
                                                        <DocumentStatusBadge
                                                            status={
                                                                review.status
                                                            }
                                                            className="shrink-0"
                                                        />
                                                    </div>

                                                    {review.reviewedAt ? (
                                                        <p className="mt-3 text-xs text-muted-foreground">
                                                            Direview:{' '}
                                                            {review.reviewedAt}
                                                        </p>
                                                    ) : null}

                                                    <div className="mt-3 rounded-md bg-muted/40 p-3">
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
