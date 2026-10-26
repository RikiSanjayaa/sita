import { Head, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, Download, Eye, FileText, XCircle } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';

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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
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

export default function DosenDokumenRevisiPage() {
    const { documentQueue } = usePage<SharedData & DokumenRevisiProps>().props;

    const [search, setSearch] = useUrlState('search', '');
    const [statusFilter, setStatusFilter] = useUrlState<StatusFilter>(
        'status',
        'semua',
    );
    const pageState = useUrlState('page', 1);
    const [revisiDocDialog, setRevisiDocDialog] =
        useState<DocumentQueueItem | null>(null);
    const [selectedDocumentId, setSelectedDocumentId] = useState<number | null>(
        null,
    );
    const selectedDocument = documentQueue.find(
        (doc) => doc.id === selectedDocumentId,
    );
    const detailTrigger = useRef<HTMLButtonElement | null>(null);
    const [expandedRevision, setExpandedRevision] = useState(false);

    const form = useForm({
        status: 'needs_revision' as 'needs_revision' | 'approved',
        revision_notes: '',
    });

    function submitApprove(documentId: number) {
        form.transform((data) => ({
            ...data,
            status: 'approved',
            revision_notes: null,
        }));
        form.post(`/dosen/dokumen-revisi/${documentId}/review`, {
            preserveScroll: true,
        });
    }

    function submitRevisi(e: React.FormEvent) {
        e.preventDefault();
        if (!revisiDocDialog) return;

        form.transform((data) => ({
            ...data,
            status: 'needs_revision',
            revision_notes: data.revision_notes || null,
        }));

        form.post(`/dosen/dokumen-revisi/${revisiDocDialog.id}/review`, {
            preserveScroll: true,
            onSuccess: () => {
                setRevisiDocDialog(null);
                form.reset();
            },
        });
    }

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
                                                        <p
                                                            className="font-medium break-words max-md:line-clamp-2 md:max-w-56"
                                                            title={doc.title}
                                                        >
                                                            {doc.title}
                                                        </p>
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
                                                className={
                                                    stackedTable.tdActions
                                                }
                                            >
                                                <div className="flex items-center justify-end gap-1">
                                                    <RowIconButton
                                                        label={`Lihat dokumen ${doc.title} - ${doc.mahasiswa}`}
                                                        onClick={(event) => {
                                                            detailTrigger.current =
                                                                event.currentTarget;
                                                            setSelectedDocumentId(
                                                                doc.id,
                                                            );
                                                        }}
                                                    >
                                                        <Eye className="size-4" />
                                                    </RowIconButton>
                                                    <RowIconButton
                                                        label={`Unduh dokumen ${doc.title}`}
                                                        disabled={!doc.fileUrl}
                                                        onClick={() => {
                                                            if (doc.fileUrl) {
                                                                window.open(
                                                                    doc.fileUrl,
                                                                    '_blank',
                                                                    'noopener,noreferrer',
                                                                );
                                                            }
                                                        }}
                                                    >
                                                        <Download className="size-4" />
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

            <Sheet
                open={selectedDocument !== undefined}
                onOpenChange={(open) => !open && setSelectedDocumentId(null)}
            >
                <SheetContent
                    className="w-full gap-0 p-0 sm:max-w-xl"
                    onCloseAutoFocus={(event) => {
                        event.preventDefault();
                        detailTrigger.current?.focus();
                    }}
                >
                    {selectedDocument && (
                        <>
                            <SheetHeader className="border-b px-6 py-4 pr-12">
                                <SheetTitle className="break-words">
                                    {selectedDocument.title}
                                </SheetTitle>
                                <SheetDescription className="break-words">
                                    {selectedDocument.mahasiswa} -{' '}
                                    {selectedDocument.nim}
                                </SheetDescription>
                            </SheetHeader>
                            <div className="min-h-0 flex-1 space-y-6 overflow-y-auto overscroll-contain px-6 py-5">
                                <dl className="grid gap-4 text-sm">
                                    <div>
                                        <dt className="text-muted-foreground">
                                            File
                                        </dt>
                                        <dd className="break-all">
                                            {selectedDocument.file}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Diupload
                                        </dt>
                                        <dd>{selectedDocument.uploadedAt}</dd>
                                    </div>
                                    <div>
                                        <dt className="mb-1 text-muted-foreground">
                                            Status
                                        </dt>
                                        <dd>
                                            <StatusBadge
                                                domain="document"
                                                status={
                                                    documentStatusKey[
                                                        selectedDocument.status
                                                    ]
                                                }
                                            >
                                                {selectedDocument.status}
                                            </StatusBadge>
                                        </dd>
                                    </div>
                                </dl>
                                <section aria-label="Catatan revisi">
                                    <h3 className="mb-2 text-sm font-semibold">
                                        Catatan revisi yang dikirim
                                    </h3>
                                    <p className="text-sm leading-relaxed break-words whitespace-pre-wrap text-muted-foreground">
                                        {selectedDocument.revisionNotes ??
                                            'Belum ada catatan revisi.'}
                                    </p>
                                </section>
                                <div className="flex flex-wrap gap-2 border-t pt-4">
                                    {selectedDocument.fileUrl && (
                                        <Button
                                            variant="outline"
                                            asChild
                                            className="max-md:min-h-11"
                                        >
                                            <a
                                                href={selectedDocument.fileUrl}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <Download />
                                                Unduh dokumen
                                            </a>
                                        </Button>
                                    )}
                                    {selectedDocument.status ===
                                        'Perlu Review' && (
                                        <>
                                            <Button
                                                variant="outline"
                                                className="max-md:min-h-11"
                                                disabled={form.processing}
                                                onClick={() => {
                                                    form.reset();
                                                    form.clearErrors();
                                                    setExpandedRevision(false);
                                                    setRevisiDocDialog(
                                                        selectedDocument,
                                                    );
                                                }}
                                            >
                                                <XCircle />
                                                Kirim catatan revisi
                                            </Button>
                                            <Button
                                                className="max-md:min-h-11"
                                                disabled={form.processing}
                                                onClick={() =>
                                                    submitApprove(
                                                        selectedDocument.id,
                                                    )
                                                }
                                            >
                                                <CheckCircle2 />
                                                Setujui dokumen
                                            </Button>
                                        </>
                                    )}
                                </div>
                            </div>
                        </>
                    )}
                </SheetContent>
            </Sheet>

            <Dialog
                open={revisiDocDialog !== null}
                onOpenChange={(open) => !open && setRevisiDocDialog(null)}
            >
                <DialogContent
                    className={cn(
                        'max-h-[90dvh] overflow-y-auto',
                        expandedRevision ? 'sm:max-w-3xl' : 'sm:max-w-xl',
                    )}
                >
                    <DialogHeader>
                        <DialogTitle>Kirim Catatan Revisi</DialogTitle>
                        <DialogDescription>
                            Tulis detail revisi yang perlu diperbaiki oleh
                            mahasiswa untuk dokumen{' '}
                            <span className="font-medium text-foreground">
                                {revisiDocDialog?.file}
                            </span>
                            .
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        onSubmit={submitRevisi}
                        className="flex flex-col gap-4 py-2"
                    >
                        <div className="grid gap-2">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <Label htmlFor="revision_notes">
                                    Catatan Revisi
                                </Label>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    aria-expanded={expandedRevision}
                                    aria-controls="revision_notes"
                                    onClick={() =>
                                        setExpandedRevision(
                                            (expanded) => !expanded,
                                        )
                                    }
                                >
                                    {expandedRevision
                                        ? 'Ciutkan kolom catatan'
                                        : 'Perluas kolom catatan'}
                                </Button>
                            </div>
                            <Textarea
                                id="revision_notes"
                                value={form.data.revision_notes}
                                onChange={(e) =>
                                    form.setData(
                                        'revision_notes',
                                        e.target.value,
                                    )
                                }
                                placeholder="Tuliskan masukan atau rincian perbaikan di sini..."
                                className={cn(
                                    'min-h-40 resize-y',
                                    expandedRevision && 'min-h-[45dvh]',
                                )}
                                required
                                aria-invalid={Boolean(
                                    form.errors.revision_notes,
                                )}
                            />
                            {form.errors.revision_notes && (
                                <p className="text-sm font-medium text-destructive">
                                    {form.errors.revision_notes}
                                </p>
                            )}
                        </div>

                        <DialogFooter className="pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setRevisiDocDialog(null)}
                                disabled={form.processing}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    !form.data.revision_notes?.trim()
                                }
                            >
                                {form.processing
                                    ? 'Menyimpan...'
                                    : 'Kirim Catatan'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </DosenLayout>
    );
}
