import { Head, Link, router, usePage } from '@inertiajs/react';
import { Download, Eye, FileText } from 'lucide-react';
import { useMemo } from 'react';

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
import {
    DataTableContainer,
    DataTableEmptyState,
    DataTablePagination,
    usePagination,
} from '@/components/ui/data-table';
import { useUrlState } from '@/hooks/use-url-state';
import KaprodiLayout from '@/layouts/kaprodi-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/kaprodi/dashboard' },
    { title: 'Dokumen Prodi', href: '/kaprodi/dokumen' },
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
    completedRevisionCount: number;
    fileUrl: string | null;
    profileUrl: string;
    detailUrl: string;
    reviews: DocumentReviewItem[];
};

type DokumenProps = {
    programStudi: ProgramStudi;
    documentQueue: DocumentQueueItem[];
};

type StatusFilter =
    | 'semua'
    | 'Perlu Review'
    | 'Perlu Revisi'
    | 'Revisi selesai'
    | 'Disetujui';

/** The backend sends localized status labels; the badge tone needs the key. */
const documentStatusKeys: Record<string, string> = {
    'Perlu Review': 'submitted',
    'Perlu Revisi': 'needs_revision',
    'Revisi selesai': 'revision_completed',
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
        doc.completedRevisionCount > 0
            ? `${doc.completedRevisionCount} revisi selesai`
            : null,
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
            title="Dokumen Prodi"
            subtitle={`Monitoring dokumen mahasiswa ${programStudi.name}`}
        >
            <Head title="Dokumen Prodi" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6 lg:py-8">
                <DataSection
                    level={1}
                    title="Dokumen Prodi"
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
                                    label: 'Revisi selesai',
                                    value: 'Revisi selesai',
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
                                                router.visit(doc.detailUrl)
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
                                                <Link
                                                    href={doc.detailUrl}
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                    title={doc.title}
                                                    className="line-clamp-2 rounded-sm text-left font-medium hover:text-primary focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                                >
                                                    {doc.title}
                                                </Link>
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
                                                    <RowIconButton
                                                        asChild
                                                        label={`Detail dokumen ${doc.title}`}
                                                    >
                                                        <Link
                                                            href={doc.detailUrl}
                                                        >
                                                            <Eye
                                                                aria-hidden="true"
                                                                className="size-4"
                                                            />
                                                        </Link>
                                                    </RowIconButton>
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
        </KaprodiLayout>
    );
}
