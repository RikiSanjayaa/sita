import { Head, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, Download, FileText, XCircle } from 'lucide-react';
import { useMemo, useState } from 'react';

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
import { Textarea } from '@/components/ui/textarea';
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
};

type DokumenRevisiProps = {
    documentQueue: DocumentQueueItem[];
    flashMessage?: string | null;
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
    const { documentQueue, flashMessage } = usePage<
        SharedData & DokumenRevisiProps
    >().props;

    const [search, setSearch] = useUrlState('search', '');
    const [statusFilter, setStatusFilter] = useUrlState<StatusFilter>(
        'status',
        'semua',
    );
    const pageState = useUrlState('page', 1);
    const [revisiDocDialog, setRevisiDocDialog] =
        useState<DocumentQueueItem | null>(null);

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
                {flashMessage && (
                    <div
                        role="status"
                        className="flex items-center gap-2 rounded-xl bg-success-muted px-5 py-3 text-sm font-medium text-success-muted-foreground"
                    >
                        <CheckCircle2 className="size-4 shrink-0" />
                        {flashMessage}
                    </div>
                )}

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
                                                data-label="File / Judul"
                                                className={stackedTable.td}
                                            >
                                                <div className="flex min-w-0 items-start gap-2.5">
                                                    <div
                                                        aria-hidden="true"
                                                        className="flex size-8 shrink-0 items-center justify-center rounded-md bg-primary-muted text-primary-muted-foreground"
                                                    >
                                                        <FileText className="size-4" />
                                                    </div>
                                                    <div className="min-w-0">
                                                        <p className="font-medium break-words md:max-w-56">
                                                            {doc.title}
                                                        </p>
                                                        <p className="text-xs break-all text-muted-foreground md:max-w-56">
                                                            {doc.file}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>

                                            <td
                                                data-label="Status"
                                                className={stackedTable.td}
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
                                                data-label="Waktu Upload"
                                                className={cn(
                                                    stackedTable.td,
                                                    'text-muted-foreground md:whitespace-nowrap',
                                                )}
                                            >
                                                {doc.uploadedAt}
                                            </td>

                                            <td
                                                data-label="Catatan Revisi"
                                                className={cn(
                                                    stackedTable.td,
                                                    'text-muted-foreground',
                                                )}
                                            >
                                                {doc.revisionNotes ? (
                                                    <p className="line-clamp-2 text-pretty md:max-w-56">
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

                                                    {doc.status ===
                                                        'Perlu Review' && (
                                                        <>
                                                            <Tooltip>
                                                                <TooltipTrigger
                                                                    asChild
                                                                >
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className={cn(
                                                                            actionButtonClassName,
                                                                            'text-warning-muted-foreground hover:bg-warning-muted hover:text-warning-muted-foreground',
                                                                        )}
                                                                        disabled={
                                                                            form.processing
                                                                        }
                                                                        onClick={() => {
                                                                            form.reset();
                                                                            form.clearErrors();
                                                                            setRevisiDocDialog(
                                                                                doc,
                                                                            );
                                                                        }}
                                                                    >
                                                                        <XCircle className="size-4" />
                                                                        <span className="md:sr-only">
                                                                            Kirim
                                                                            Catatan
                                                                            Revisi
                                                                        </span>
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    Kirim
                                                                    Catatan
                                                                    Revisi
                                                                </TooltipContent>
                                                            </Tooltip>

                                                            <Tooltip>
                                                                <TooltipTrigger
                                                                    asChild
                                                                >
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className={cn(
                                                                            actionButtonClassName,
                                                                            'text-success-muted-foreground hover:bg-success-muted hover:text-success-muted-foreground',
                                                                        )}
                                                                        disabled={
                                                                            form.processing
                                                                        }
                                                                        onClick={() =>
                                                                            submitApprove(
                                                                                doc.id,
                                                                            )
                                                                        }
                                                                    >
                                                                        <CheckCircle2 className="size-4" />
                                                                        <span className="md:sr-only">
                                                                            Setujui
                                                                            Dokumen
                                                                        </span>
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    Setujui
                                                                    Dokumen
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </>
                                                    )}
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

            <Dialog
                open={revisiDocDialog !== null}
                onOpenChange={(open) => !open && setRevisiDocDialog(null)}
            >
                <DialogContent className="sm:max-w-[500px]">
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
                            <Label htmlFor="revision_notes">
                                Catatan Revisi
                            </Label>
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
                                className="min-h-[120px] resize-none"
                                required
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
