import { Head, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Download,
    FileText,
    XCircle,
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

export default function DosenDokumenRevisiPage() {
    const { documentQueue, flashMessage } = usePage<
        SharedData & DokumenRevisiProps
    >().props;

    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState<StatusFilter>('semua');
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
                doc.file.toLowerCase().includes(lower) ||
                doc.title.toLowerCase().includes(lower);
            return matchesStatus && matchesSearch;
        });
    }, [documentQueue, statusFilter, search]);

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
            onChange: (v) => setStatusFilter(v as StatusFilter),
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
                { label: 'Disetujui', value: 'Disetujui' },
            ],
        },
    ];

    const pendingCount = statusCounts.review;

    return (
        <DosenLayout
            breadcrumbs={breadcrumbs}
            title="Dokumen & Revisi"
            subtitle="Tinjau dokumen mahasiswa dan kirim catatan revisi"
        >
            <Head title="Dokumen & Revisi Dosen" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6 lg:gap-8 lg:py-8">
                {/* Flash message */}
                {flashMessage && (
                    <div className="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-500/10 px-5 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-800 dark:text-emerald-400">
                        <CheckCircle2 className="size-4 shrink-0" />
                        {flashMessage}
                    </div>
                )}

                {/* Section header */}
                <section>
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h2 className="text-base font-semibold">
                                Daftar Dokumen
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Klik aksi untuk menyetujui atau meminta revisi
                            </p>
                        </div>
                        {pendingCount > 0 && (
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-600/10 px-3 py-1 text-xs font-bold text-amber-700 dark:text-amber-400">
                                <AlertTriangle className="size-3" />
                                {pendingCount} perlu review
                            </span>
                        )}
                    </div>

                    {/* Toolbar: search (left) + filter pills (right) */}
                    <DataTableToolbar
                        search={search}
                        onSearchChange={setSearch}
                        searchPlaceholder="Cari mahasiswa atau file..."
                        filterGroups={filterTabs}
                        className="mb-3"
                    />

                    {/* Table */}
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
                                            Status
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Waktu Upload
                                        </th>
                                        <th className="w-[22%] px-5 py-3 font-medium">
                                            Catatan Revisi
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
                                                key={`${doc.id}-${doc.file}`}
                                                className={cn(
                                                    'transition-colors hover:bg-muted/20',
                                                    isPending &&
                                                        'bg-amber-50/40 dark:bg-amber-950/10',
                                                )}
                                            >
                                                {/* Mahasiswa */}
                                                <td className="px-5 py-3.5 align-middle">
                                                    <span className="text-[14px] font-semibold">
                                                        {doc.mahasiswa}
                                                    </span>
                                                </td>

                                                {/* File / Judul */}
                                                <td className="px-5 py-3.5 align-middle">
                                                    <div className="flex min-w-0 items-center gap-2.5">
                                                        <div className="flex size-8 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                                                            <FileText className="size-4" />
                                                        </div>
                                                        <div className="min-w-0">
                                                            <div className="max-w-[200px] truncate text-sm font-medium">
                                                                {doc.title}
                                                            </div>
                                                            <div className="max-w-[200px] truncate text-xs text-muted-foreground">
                                                                {doc.file}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>

                                                {/* Status */}
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

                                                {/* Waktu */}
                                                <td className="px-5 py-3.5 align-middle text-xs whitespace-nowrap text-muted-foreground">
                                                    {doc.uploadedAt}
                                                </td>

                                                {/* Catatan */}
                                                <td className="px-5 py-3.5 align-middle">
                                                    {doc.revisionNotes ? (
                                                        <p className="line-clamp-2 max-w-[200px] text-xs leading-relaxed text-muted-foreground">
                                                            {doc.revisionNotes}
                                                        </p>
                                                    ) : (
                                                        <span className="text-xs text-muted-foreground italic">
                                                            —
                                                        </span>
                                                    )}
                                                </td>

                                                {/* Aksi */}
                                                <td className="px-5 py-3.5 align-middle">
                                                    <div className="flex items-center justify-end gap-1">
                                                        {/* Download */}
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
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                Unduh Dokumen
                                                            </TooltipContent>
                                                        </Tooltip>

                                                        {doc.status ===
                                                            'Perlu Review' && (
                                                            <>
                                                                {/* Revisi */}
                                                                <Tooltip>
                                                                    <TooltipTrigger
                                                                        asChild
                                                                    >
                                                                        <Button
                                                                            size="icon"
                                                                            variant="ghost"
                                                                            className="size-8 text-amber-600 hover:bg-amber-600/10 hover:text-amber-700"
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
                                                                        </Button>
                                                                    </TooltipTrigger>
                                                                    <TooltipContent>
                                                                        Kirim
                                                                        Catatan
                                                                        Revisi
                                                                    </TooltipContent>
                                                                </Tooltip>

                                                                {/* Setujui */}
                                                                <Tooltip>
                                                                    <TooltipTrigger
                                                                        asChild
                                                                    >
                                                                        <Button
                                                                            size="icon"
                                                                            variant="ghost"
                                                                            className="size-8 text-emerald-600 hover:bg-emerald-600/10 hover:text-emerald-700"
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
                                    : 'Belum ada dokumen yang perlu direview'
                            }
                            description={
                                search || statusFilter !== 'semua'
                                    ? 'Coba ubah kata kunci atau filter yang dipilih.'
                                    : 'Dokumen mahasiswa yang butuh persetujuan akan muncul di sini.'
                            }
                        />
                    )}
                </section>
            </div>

            {/* Dialog Revisi */}
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
                                className="bg-amber-600 text-white hover:bg-amber-700"
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
