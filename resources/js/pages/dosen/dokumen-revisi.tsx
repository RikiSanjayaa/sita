import { Head, Link, usePage } from '@inertiajs/react';
import { Download, Eye, FileText } from 'lucide-react';
import { useMemo } from 'react';

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
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useUrlState } from '@/hooks/use-url-state';
import DosenLayout from '@/layouts/dosen-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dosen/dashboard' },
    { title: 'Dokumen & Revisi', href: '/dosen/dokumen-revisi' },
];

type DocumentQueueItem = {
    id: number;
    mahasiswa: string;
    nim: string;
    title: string;
    file: string;
    uploadedAt: string;
    status: 'Perlu Review' | 'Perlu Revisi' | 'Disetujui';
    revisionNotes: string | null;
    fileUrl: string | null;
    detailUrl: string;
};

type DokumenRevisiProps = {
    documentQueue: DocumentQueueItem[];
};

type StatusFilter = 'semua' | 'Perlu Review' | 'Perlu Revisi' | 'Disetujui';

const PAGE_SIZE = 15;

const documentStatusKey: Record<DocumentQueueItem['status'], string> = {
    'Perlu Review': 'submitted',
    'Perlu Revisi': 'needs_revision',
    Disetujui: 'approved',
};

/**
 * Icon button on md and up (the tooltip names it); on phones the label shows
 * beside the icon because tooltips do not open on touch.
 */
const actionButtonClassName =
    'text-muted-foreground hover:text-foreground max-md:h-11 max-md:w-auto max-md:gap-2 max-md:border max-md:px-3 md:size-8';

export default function DosenDokumenRevisiPage() {
    const { documentQueue } = usePage<SharedData & DokumenRevisiProps>().props;

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
                doc.nim.toLowerCase().includes(lower) ||
                doc.file.toLowerCase().includes(lower) ||
                doc.title.toLowerCase().includes(lower);
            return matchesStatus && matchesSearch;
        });
    }, [documentQueue, statusFilter, search]);

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

    const isFiltering = search !== '' || statusFilter !== 'semua';

    return (
        <DosenLayout
            breadcrumbs={breadcrumbs}
            title="Dokumen & Revisi"
            subtitle="Tinjau dokumen mahasiswa dan kirim catatan revisi"
        >
            <Head title="Dokumen & Revisi Dosen" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6 lg:py-8">
                <DataSection
                    level={1}
                    title="Daftar Dokumen"
                    description={`Tinjau dokumen mahasiswa dan kirim catatan revisi. ${
                        statusCounts.review > 0
                            ? `${statusCounts.review} dokumen perlu review.`
                            : 'Tidak ada dokumen yang menunggu review.'
                    }`}
                >
                    <StickyFilterBar>
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            placeholder="Cari mahasiswa, NIM, atau file..."
                        />

                        <FilterPills
                            label="Status dokumen"
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
                                    'md:min-w-[920px]',
                                )}
                            >
                                <thead className={stackedTable.thead}>
                                    <tr className="border-b bg-muted/40">
                                        <th className={dataTh}>Mahasiswa</th>
                                        <th className={dataTh}>File / Judul</th>
                                        <th className={dataTh}>Status</th>
                                        <th className={dataTh}>Waktu Upload</th>
                                        <th className={dataTh}>
                                            Catatan Revisi
                                        </th>
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
                                            key={`${doc.id}-${doc.file}`}
                                            className={cn(
                                                stackedTable.tr,
                                                'align-top',
                                            )}
                                        >
                                            <td
                                                className={stackedTable.tdPlain}
                                            >
                                                <p className="font-medium">
                                                    {doc.mahasiswa}
                                                </p>
                                                <p className="text-muted-foreground tabular-nums">
                                                    {doc.nim}
                                                </p>
                                            </td>

                                            <td
                                                className={
                                                    stackedTable.tdDetail
                                                }
                                            >
                                                <div className="flex min-w-0 items-start gap-2.5">
                                                    <div
                                                        aria-hidden="true"
                                                        className="flex size-8 shrink-0 items-center justify-center rounded-md bg-primary-muted text-primary-muted-foreground max-md:hidden"
                                                    >
                                                        <FileText className="size-4" />
                                                    </div>
                                                    <div className="min-w-0">
                                                        <Link
                                                            href={doc.detailUrl}
                                                            className="block font-medium break-words hover:text-primary max-md:line-clamp-2 md:max-w-56"
                                                            title={doc.title}
                                                        >
                                                            {doc.title}
                                                        </Link>
                                                        <p
                                                            className="text-xs break-all text-muted-foreground max-md:line-clamp-1 md:max-w-56"
                                                            title={doc.file}
                                                        >
                                                            {doc.file}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>

                                            <td
                                                className={stackedTable.tdAside}
                                            >
                                                <StatusBadge
                                                    domain="document"
                                                    status={
                                                        documentStatusKey[
                                                            doc.status
                                                        ]
                                                    }
                                                    className="whitespace-nowrap"
                                                >
                                                    {doc.status}
                                                </StatusBadge>
                                            </td>

                                            <td
                                                className={cn(
                                                    stackedTable.td,
                                                    'text-muted-foreground md:whitespace-nowrap',
                                                )}
                                            >
                                                {doc.uploadedAt}
                                            </td>

                                            <td
                                                className={cn(
                                                    stackedTable.tdDetail,
                                                    'text-muted-foreground max-md:text-xs',
                                                    !doc.revisionNotes &&
                                                        'max-md:hidden',
                                                )}
                                            >
                                                {doc.revisionNotes ? (
                                                    <p
                                                        className="text-pretty max-md:line-clamp-2 md:line-clamp-2 md:max-w-56"
                                                        title={
                                                            doc.revisionNotes
                                                        }
                                                    >
                                                        <span className="md:hidden">
                                                            Catatan:{' '}
                                                        </span>
                                                        {doc.revisionNotes}
                                                    </p>
                                                ) : (
                                                    '-'
                                                )}
                                            </td>

                                            <td
                                                className={cn(
                                                    stackedTable.tdActions,
                                                    'max-md:order-4 max-md:mt-2 max-md:basis-full',
                                                )}
                                            >
                                                <div className="flex items-center justify-end gap-1 max-md:flex-wrap max-md:justify-start max-md:gap-2">
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className={
                                                                    actionButtonClassName
                                                                }
                                                                disabled={
                                                                    !doc.fileUrl
                                                                }
                                                                onClick={() => {
                                                                    if (
                                                                        doc.fileUrl
                                                                    )
                                                                        window.open(
                                                                            doc.fileUrl,
                                                                            '_blank',
                                                                            'noopener,noreferrer',
                                                                        );
                                                                }}
                                                            >
                                                                <Download className="size-4" />
                                                                <span className="md:sr-only">
                                                                    Unduh
                                                                    Dokumen
                                                                </span>
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            Unduh Dokumen
                                                        </TooltipContent>
                                                    </Tooltip>

                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                asChild
                                                                variant="ghost"
                                                                size="icon"
                                                                className={
                                                                    actionButtonClassName
                                                                }
                                                            >
                                                                <Link
                                                                    href={
                                                                        doc.detailUrl
                                                                    }
                                                                    aria-label={`Detail dokumen ${doc.title}`}
                                                                >
                                                                    <Eye
                                                                        aria-hidden="true"
                                                                        className="size-4"
                                                                    />
                                                                    <span className="md:sr-only">
                                                                        Detail
                                                                    </span>
                                                                </Link>
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            Detail dokumen
                                                        </TooltipContent>
                                                    </Tooltip>
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
                                isFiltering
                                    ? 'Tidak ada dokumen yang sesuai'
                                    : 'Belum ada dokumen yang perlu direview'
                            }
                            description={
                                isFiltering
                                    ? 'Coba ubah kata kunci atau filter yang dipilih.'
                                    : 'Dokumen mahasiswa yang butuh persetujuan akan muncul di sini.'
                            }
                        />
                    )}
                </DataSection>
            </div>
        </DosenLayout>
    );
}
