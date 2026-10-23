import { Head, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    CircleAlert,
    Clock,
    Download,
    Eye,
    FileText,
    Inbox,
    Trash2,
    Upload,
} from 'lucide-react';
import { useMemo, useState } from 'react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useUrlState } from '@/hooks/use-url-state';
import AppLayout from '@/layouts/app-layout';
import { statusClassName } from '@/lib/status-styles';
import { cn } from '@/lib/utils';
import * as routes from '@/routes';
import * as mahasiswaUploadDokumenRoutes from '@/routes/mahasiswa/upload-dokumen';
import { type BreadcrumbItem, type SharedData } from '@/types';

type DokumenStatus =
    | 'Menunggu Review'
    | 'Sebagian direview'
    | 'Disetujui'
    | 'Perlu Revisi'
    | 'Tersimpan';

type UploadedDokumenReview = {
    lecturerName: string;
    role: string;
    status: string;
    notes: string | null;
    reviewedAt: string | null;
};

type UploadedDokumenRow = {
    id: number;
    title: string;
    category: string;
    version: string | null;
    uploadedAt: string;
    fileName: string;
    size: string;
    status: DokumenStatus;
    reviews: UploadedDokumenReview[];
    downloadUrl: string;
    canDelete: boolean;
    deleteBlockedReason?: string | null;
};

type UploadDokumenProps = {
    uploadedDocuments: UploadedDokumenRow[];
    flashMessage?: string | null;
    errorMessage?: string | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: routes.dashboard().url },
    { title: 'Upload Dokumen', href: routes.uploadDokumen().url },
];

const panduanItems = [
    'Format file yang didukung: PDF, DOC, DOCX',
    'Ukuran maksimal file: 10 MB',
    'Setiap upload membuat versi baru dokumen',
    'Dosen pembimbing mendapat notifikasi realtime di group chat',
];

function StatusBadge({ status }: { status: DokumenStatus }) {
    if (status === 'Tersimpan') {
        return (
            <Badge
                variant="outline"
                className="gap-1 border-slate-300 bg-slate-50 whitespace-nowrap text-slate-700 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-300"
            >
                <Inbox className="size-3" />
                Tersimpan
            </Badge>
        );
    }

    if (status === 'Disetujui') {
        return (
            <Badge className="gap-1 bg-emerald-600 whitespace-nowrap text-white hover:bg-emerald-600/90 dark:bg-emerald-500 dark:hover:bg-emerald-500/90">
                <CheckCircle2 className="size-3" />
                Disetujui
            </Badge>
        );
    }
    if (status === 'Perlu Revisi') {
        return (
            <Badge variant="destructive" className="gap-1 whitespace-nowrap">
                <CircleAlert className="size-3" />
                Perlu Revisi
            </Badge>
        );
    }
    if (status === 'Sebagian direview') {
        return (
            <Badge
                className={cn(
                    'gap-1 whitespace-nowrap',
                    statusClassName('document', 'submitted'),
                )}
            >
                <Clock className="size-3 text-muted-foreground" />
                Sebagian direview
            </Badge>
        );
    }
    return (
        <Badge
            variant="secondary"
            className="gap-1 bg-muted whitespace-nowrap text-foreground"
        >
            <Clock className="size-3 text-muted-foreground" />
            Menunggu Review
        </Badge>
    );
}

const REVIEW_STATUS_LABELS: Record<string, string> = {
    approved: 'Disetujui',
    needs_revision: 'Perlu Revisi',
};

type StatusFilter = 'semua' | DokumenStatus;
const PAGE_SIZE = 15;

export default function UploadDokumenPage() {
    const page = usePage<SharedData & UploadDokumenProps>();
    const terms = page.props.academicTerminology;
    const query = page.url.split('?')[1] ?? '';
    const [isUploadOpen, setIsUploadOpen] = useState(
        new URLSearchParams(query).get('open') === 'unggah',
    );

    const [search, setSearch] = useUrlState('search', '');
    const [statusFilter, setStatusFilter] = useUrlState<StatusFilter>(
        'status',
        'semua',
    );
    const pageState = useUrlState('page', 1);
    const [documentPendingDelete, setDocumentPendingDelete] =
        useState<UploadedDokumenRow | null>(null);
    const [detailDocument, setDetailDocument] =
        useState<UploadedDokumenRow | null>(null);

    const form = useForm<{
        title: string;
        category: string;
        document: File | null;
    }>({
        title: '',
        category: 'draft-tugas-akhir',
        document: null,
    });

    const deleteForm = useForm({});

    function submitUpload() {
        form.post(mahasiswaUploadDokumenRoutes.store().url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setIsUploadOpen(false);
            },
        });
    }

    function submitDelete() {
        if (documentPendingDelete === null) {
            return;
        }

        deleteForm.delete(
            mahasiswaUploadDokumenRoutes.destroy(documentPendingDelete.id).url,
            {
                preserveScroll: true,
                onFinish: () => setDocumentPendingDelete(null),
            },
        );
    }

    const filteredDocuments = useMemo(() => {
        const lower = search.toLowerCase();
        return page.props.uploadedDocuments.filter((doc) => {
            const matchesStatus =
                statusFilter === 'semua' || doc.status === statusFilter;
            const matchesSearch =
                lower === '' ||
                doc.title.toLowerCase().includes(lower) ||
                doc.fileName.toLowerCase().includes(lower) ||
                doc.category.toLowerCase().includes(lower);
            return matchesStatus && matchesSearch;
        });
    }, [page.props.uploadedDocuments, statusFilter, search]);

    const statusCounts = useMemo(
        () => ({
            review: page.props.uploadedDocuments.filter(
                (d) => d.status === 'Menunggu Review',
            ).length,
            partial: page.props.uploadedDocuments.filter(
                (d) => d.status === 'Sebagian direview',
            ).length,
            revisi: page.props.uploadedDocuments.filter(
                (d) => d.status === 'Perlu Revisi',
            ).length,
            disetujui: page.props.uploadedDocuments.filter(
                (d) => d.status === 'Disetujui',
            ).length,
        }),
        [page.props.uploadedDocuments],
    );

    const {
        page: currentPage,
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

    const filterGroups: FilterGroup[] = [
        {
            value: statusFilter,
            onChange: (v) => setStatusFilter(v as StatusFilter),
            tabs: [
                { label: 'Semua', value: 'semua' },
                {
                    label: 'Menunggu Review',
                    value: 'Menunggu Review',
                    count: statusCounts.review,
                },
                {
                    label: 'Sebagian direview',
                    value: 'Sebagian direview',
                    count: statusCounts.partial,
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

    const pendingRevisiCount = statusCounts.revisi;

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Upload Dokumen"
            subtitle={`Kelola dan upload dokumen ${terms.finalWorkLower} Anda`}
        >
            <Head title="Upload Dokumen" />

            {/* Upload Dialog */}
            <Dialog
                open={isUploadOpen}
                onOpenChange={(open) => {
                    if (form.processing) {
                        return;
                    }

                    setIsUploadOpen(open);
                }}
            >
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Upload Dokumen</DialogTitle>
                        <DialogDescription>
                            Pilih kategori dan file yang akan diupload
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        className="grid gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            submitUpload();
                        }}
                    >
                        <div className="grid gap-2 border-b pb-4">
                            <Label htmlFor="title">Judul Dokumen</Label>
                            <Input
                                id="title"
                                value={form.data.title}
                                onChange={(event) =>
                                    form.setData('title', event.target.value)
                                }
                                placeholder="Contoh: Draft Bab 3 Metodologi"
                            />
                            {form.errors.title && (
                                <p className="text-xs text-destructive">
                                    {form.errors.title}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2 border-b pb-4">
                            <Label htmlFor="category">Kategori Dokumen</Label>
                            <Select
                                value={form.data.category}
                                onValueChange={(value) =>
                                    form.setData('category', value)
                                }
                            >
                                <SelectTrigger id="category">
                                    <SelectValue placeholder="Pilih kategori dokumen" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="draft-tugas-akhir">
                                        Draft {terms.finalWork}
                                    </SelectItem>
                                    <SelectItem value="proposal">
                                        {terms.proposalDocument}
                                    </SelectItem>
                                    <SelectItem value="final-manuscript">
                                        Naskah Akhir
                                    </SelectItem>
                                    <SelectItem value="laporan">
                                        Laporan
                                    </SelectItem>
                                    <SelectItem value="lampiran">
                                        Lampiran
                                    </SelectItem>
                                    <SelectItem value="lampiran-sidang">
                                        Lampiran {terms.finalExam}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            {form.errors.category && (
                                <p className="text-xs text-destructive">
                                    {form.errors.category}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="document">File Dokumen</Label>
                            <Input
                                id="document"
                                type="file"
                                accept=".pdf,.doc,.docx"
                                onChange={(event) =>
                                    form.setData(
                                        'document',
                                        event.currentTarget.files?.[0] ?? null,
                                    )
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                Format: PDF, DOC, DOCX. Ukuran maksimal: 10 MB.
                            </p>
                            {form.errors.document && (
                                <p className="text-xs text-destructive">
                                    {form.errors.document}
                                </p>
                            )}
                        </div>

                        {form.processing && (
                            <div className="rounded-lg border bg-muted/40 p-3">
                                <div className="flex items-center justify-between gap-3 text-sm">
                                    <div className="flex items-center gap-2 font-medium">
                                        <Spinner className="size-4" />
                                        Mengunggah dokumen...
                                    </div>
                                    {form.progress && (
                                        <span className="text-xs text-muted-foreground">
                                            {form.progress.percentage}%
                                        </span>
                                    )}
                                </div>
                                {form.progress && (
                                    <div className="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                                        <div
                                            className="h-full rounded-full bg-primary transition-[width] duration-200"
                                            style={{
                                                width: `${form.progress.percentage}%`,
                                            }}
                                        />
                                    </div>
                                )}
                            </div>
                        )}

                        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full sm:w-auto"
                                onClick={() => setIsUploadOpen(false)}
                                disabled={form.processing}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                className="bg-primary text-primary-foreground hover:bg-primary/90"
                                disabled={
                                    form.processing ||
                                    form.data.title.trim() === '' ||
                                    form.data.document === null
                                }
                            >
                                {form.processing ? (
                                    <>
                                        <Spinner className="size-4" />
                                        Mengunggah...
                                    </>
                                ) : (
                                    'Upload'
                                )}
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={documentPendingDelete !== null}
                onOpenChange={(open) => {
                    if (!open && !deleteForm.processing) {
                        setDocumentPendingDelete(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Hapus Dokumen</DialogTitle>
                        <DialogDescription>
                            Apakah Anda yakin ingin menghapus dokumen ini?
                        </DialogDescription>
                    </DialogHeader>

                    {documentPendingDelete && (
                        <div className="rounded-lg border bg-muted/30 p-3 text-sm">
                            <p className="font-medium">
                                {documentPendingDelete.title}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {documentPendingDelete.fileName}
                                {documentPendingDelete.version
                                    ? ` - ${documentPendingDelete.version}`
                                    : ''}
                            </p>
                        </div>
                    )}

                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDocumentPendingDelete(null)}
                            disabled={deleteForm.processing}
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={submitDelete}
                            disabled={deleteForm.processing}
                        >
                            {deleteForm.processing ? (
                                <>
                                    <Spinner className="size-4" />
                                    Menghapus...
                                </>
                            ) : (
                                'Ya, Hapus'
                            )}
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>

            <Sheet
                open={detailDocument !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDetailDocument(null);
                    }
                }}
            >
                <SheetContent
                    side="right"
                    className="w-full gap-0 p-0 sm:max-w-xl"
                    data-testid="dokumen-detail-drawer"
                >
                    {detailDocument ? (
                        <>
                            <SheetHeader className="border-b bg-muted/20 px-6 py-4">
                                <div className="flex items-start gap-3 pr-8">
                                    <div className="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                                        <FileText className="size-5" />
                                    </div>
                                    <div className="min-w-0">
                                        <SheetTitle className="line-clamp-2 text-base break-words">
                                            {detailDocument.title}
                                        </SheetTitle>
                                        <SheetDescription className="mt-1 break-all">
                                            {detailDocument.fileName}
                                            {detailDocument.version
                                                ? ` — ${detailDocument.version}`
                                                : ''}
                                        </SheetDescription>
                                    </div>
                                </div>
                            </SheetHeader>

                            <div className="min-h-0 flex-1 space-y-5 overflow-y-auto px-6 py-5">
                                <div className="grid gap-4 rounded-lg border bg-background p-4 sm:grid-cols-2">
                                    <div>
                                        <p className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                            Kategori
                                        </p>
                                        <p className="mt-1 text-sm font-medium">
                                            {detailDocument.category}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                            Status
                                        </p>
                                        <div className="mt-1">
                                            <StatusBadge
                                                status={detailDocument.status}
                                            />
                                        </div>
                                    </div>
                                    <div>
                                        <p className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                            Tanggal Upload
                                        </p>
                                        <p className="mt-1 text-sm font-medium">
                                            {detailDocument.uploadedAt}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                            Ukuran
                                        </p>
                                        <p className="mt-1 text-sm font-medium">
                                            {detailDocument.size}
                                        </p>
                                    </div>
                                </div>

                                <div className="space-y-3">
                                    <h3 className="text-sm font-semibold">
                                        Review Dosen
                                    </h3>
                                    {detailDocument.reviews.length > 0 ? (
                                        detailDocument.reviews.map(
                                            (review, index) => (
                                                <div
                                                    key={`${review.lecturerName}-${index}`}
                                                    className="space-y-2 rounded-lg border bg-background p-4"
                                                >
                                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                                        <div className="min-w-0">
                                                            <p className="text-sm font-medium break-words">
                                                                {
                                                                    review.lecturerName
                                                                }
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {review.role}
                                                            </p>
                                                        </div>
                                                        <Badge
                                                            variant="outline"
                                                            className={cn(
                                                                'whitespace-nowrap',
                                                                statusClassName(
                                                                    'document',
                                                                    review.status,
                                                                ),
                                                            )}
                                                        >
                                                            {REVIEW_STATUS_LABELS[
                                                                review.status
                                                            ] ??
                                                                'Menunggu Review'}
                                                        </Badge>
                                                    </div>
                                                    {review.notes ? (
                                                        <p className="text-sm break-words whitespace-pre-wrap">
                                                            {review.notes}
                                                        </p>
                                                    ) : (
                                                        <p className="text-xs text-muted-foreground italic">
                                                            Tidak ada catatan.
                                                        </p>
                                                    )}
                                                    {review.reviewedAt ? (
                                                        <p className="text-xs text-muted-foreground">
                                                            Direview{' '}
                                                            {review.reviewedAt}
                                                        </p>
                                                    ) : null}
                                                </div>
                                            ),
                                        )
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            Belum ada review dari dosen.
                                        </p>
                                    )}
                                </div>

                                <Button asChild variant="outline">
                                    <a href={detailDocument.downloadUrl}>
                                        <Download className="size-4" />
                                        Unduh Dokumen
                                    </a>
                                </Button>
                            </div>
                        </>
                    ) : null}
                </SheetContent>
            </Sheet>

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6">
                {/* Page header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Upload Dokumen
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Kelola dan upload dokumen {terms.finalWorkLower}{' '}
                            Anda
                        </p>
                    </div>
                    <Button
                        type="button"
                        className="h-10 w-full gap-2 bg-primary text-primary-foreground hover:bg-primary/90 sm:w-auto"
                        onClick={() => setIsUploadOpen(true)}
                    >
                        <Upload className="size-4" />
                        Upload Dokumen
                    </Button>
                </div>

                {/* Flash Message */}
                {(page.props.flashMessage || page.props.errorMessage) && (
                    <Alert
                        variant={
                            page.props.errorMessage ? 'destructive' : 'default'
                        }
                    >
                        <AlertTitle>
                            {page.props.errorMessage ? 'Error' : 'Berhasil'}
                        </AlertTitle>
                        <AlertDescription>
                            {page.props.errorMessage || page.props.flashMessage}
                        </AlertDescription>
                    </Alert>
                )}

                {/* Panduan Upload */}
                <div className="overflow-hidden rounded-xl border bg-card py-0 shadow-sm">
                    <div className="border-b px-6 py-4">
                        <h3 className="text-sm font-semibold">
                            Panduan Upload
                        </h3>
                        <p className="text-xs text-muted-foreground">
                            Hal yang perlu diperhatikan sebelum mengunggah
                            dokumen.
                        </p>
                    </div>
                    <div className="grid gap-3 px-6 py-4">
                        {panduanItems.map((item) => (
                            <div
                                key={item}
                                className="flex items-start gap-3 text-sm"
                            >
                                <span className="mt-0.5 inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-green-50 text-green-700 ring-1 ring-green-200 dark:bg-green-500/15 dark:text-green-300 dark:ring-green-500/40">
                                    <CheckCircle2 className="size-4" />
                                </span>
                                <span className="text-muted-foreground">
                                    {item}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Dokumen Table */}
                <section>
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h2 className="text-base font-semibold">
                                Dokumen yang Diupload
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Daftar dokumen beserta riwayat versi
                            </p>
                        </div>
                        {pendingRevisiCount > 0 && (
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-destructive/10 px-3 py-1 text-xs font-bold text-destructive">
                                <AlertTriangle className="size-3" />
                                {pendingRevisiCount} perlu revisi
                            </span>
                        )}
                    </div>

                    {/* Toolbar */}
                    <DataTableToolbar
                        search={search}
                        onSearchChange={setSearch}
                        searchPlaceholder="Cari judul atau file..."
                        filterGroups={filterGroups}
                        className="mb-3"
                    />

                    {/* Table */}
                    {totalItems > 0 ? (
                        <DataTableContainer
                            pagination={
                                <DataTablePagination
                                    currentPage={currentPage}
                                    totalPages={totalPages}
                                    totalItems={totalItems}
                                    pageSize={pageSize}
                                    onPageChange={setPage}
                                    onPageSizeChange={setPageSize}
                                    itemLabel="dokumen"
                                />
                            }
                        >
                            <table className="w-full min-w-[800px] text-left text-sm">
                                <thead className="border-b bg-muted/30 text-xs text-muted-foreground">
                                    <tr>
                                        <th className="px-5 py-3 font-medium">
                                            Nama Dokumen
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Kategori
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Tanggal Upload
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Ukuran
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Status
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Review
                                        </th>
                                        <th className="px-5 py-3 text-right font-medium">
                                            Aksi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y bg-background">
                                    {paginated.map((row) => {
                                        const hasRevision =
                                            row.status === 'Perlu Revisi';
                                        return (
                                            <tr
                                                key={`${row.id}-${row.version ?? 'lampiran'}`}
                                                onClick={() =>
                                                    setDetailDocument(row)
                                                }
                                                className={cn(
                                                    'cursor-pointer transition-colors hover:bg-muted/20',
                                                    hasRevision &&
                                                        'bg-destructive/5 dark:bg-destructive/5',
                                                )}
                                            >
                                                {/* Nama Dokumen */}
                                                <td className="px-5 py-3.5">
                                                    <div className="flex items-center gap-3">
                                                        <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                            <FileText className="size-4" />
                                                        </div>
                                                        <div className="min-w-0">
                                                            <div className="max-w-[180px] truncate text-sm font-medium">
                                                                {row.title}
                                                            </div>
                                                            <div className="max-w-[180px] truncate text-xs text-muted-foreground">
                                                                {row.fileName}
                                                                {row.version
                                                                    ? ` — ${row.version}`
                                                                    : ''}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>

                                                {/* Kategori */}
                                                <td className="px-5 py-3.5">
                                                    <Badge
                                                        variant="outline"
                                                        className="rounded-full bg-background whitespace-nowrap text-foreground"
                                                    >
                                                        {row.category}
                                                    </Badge>
                                                </td>

                                                {/* Tanggal */}
                                                <td className="px-5 py-3.5 text-xs whitespace-nowrap text-muted-foreground">
                                                    {row.uploadedAt}
                                                </td>

                                                {/* Ukuran */}
                                                <td className="px-5 py-3.5 text-xs whitespace-nowrap text-muted-foreground">
                                                    {row.size}
                                                </td>

                                                {/* Status */}
                                                <td className="px-5 py-3.5">
                                                    <StatusBadge
                                                        status={row.status}
                                                    />
                                                </td>

                                                {/* Review */}
                                                <td className="px-5 py-3.5 text-xs whitespace-nowrap text-muted-foreground">
                                                    {row.reviews.length > 0
                                                        ? `${row.reviews.length} review`
                                                        : '—'}
                                                </td>

                                                {/* Aksi */}
                                                <td
                                                    className="px-5 py-3.5"
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            className="h-8"
                                                            onClick={() =>
                                                                setDetailDocument(
                                                                    row,
                                                                )
                                                            }
                                                            aria-label={`Detail dokumen ${row.title}`}
                                                        >
                                                            <Eye className="size-4" />
                                                            Detail
                                                        </Button>
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                asChild
                                                            >
                                                                <Button
                                                                    asChild
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="size-8 text-muted-foreground hover:text-foreground"
                                                                >
                                                                    <a
                                                                        href={
                                                                            row.downloadUrl
                                                                        }
                                                                        aria-label={`Unduh dokumen ${row.title}`}
                                                                    >
                                                                        <Download className="size-4" />
                                                                    </a>
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
                                                                <span>
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="size-8 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                                        disabled={
                                                                            deleteForm.processing ||
                                                                            !row.canDelete
                                                                        }
                                                                        aria-label={`Hapus dokumen ${row.title}`}
                                                                        onClick={() => {
                                                                            setDocumentPendingDelete(
                                                                                row,
                                                                            );
                                                                        }}
                                                                    >
                                                                        <Trash2 className="size-4" />
                                                                    </Button>
                                                                </span>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                {row.deleteBlockedReason ??
                                                                    'Hapus Dokumen'}
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </DataTableContainer>
                    ) : (
                        <DataTableEmptyState
                            icon={Inbox}
                            title={
                                search || statusFilter !== 'semua'
                                    ? 'Tidak ada dokumen yang sesuai'
                                    : 'Belum ada dokumen yang diupload'
                            }
                            description={
                                search || statusFilter !== 'semua'
                                    ? 'Coba ubah kata kunci atau filter yang dipilih.'
                                    : 'Mulai unggah dokumen pertama Anda untuk memulai proses review.'
                            }
                        />
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
