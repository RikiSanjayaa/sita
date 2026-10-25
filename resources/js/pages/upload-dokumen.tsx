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
import { useMemo, useState, type ReactNode } from 'react';

import { ArchivedMentorshipNotice } from '@/components/archived-mentorship-notice';
import {
    DataSection,
    DataSelect,
    DataToolbar,
    dataTh,
    stackedTable,
} from '@/components/patterns/data-section';
import { FilterPills } from '@/components/patterns/filter-pills';
import { PageHeader } from '@/components/patterns/page-header';
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
    archivedProject: boolean;
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

const DOKUMEN_STATUS_STYLE: Record<
    DokumenStatus,
    { statusKey: string; icon: typeof Clock }
> = {
    Tersimpan: { statusKey: 'draft', icon: Inbox },
    'Menunggu Review': { statusKey: 'draft', icon: Clock },
    'Sebagian direview': { statusKey: 'submitted', icon: Clock },
    'Perlu Revisi': { statusKey: 'needs_revision', icon: CircleAlert },
    Disetujui: { statusKey: 'approved', icon: CheckCircle2 },
};

function DokumenStatusBadge({ status }: { status: DokumenStatus }) {
    const { statusKey, icon: Icon } = DOKUMEN_STATUS_STYLE[status];

    return (
        <StatusBadge
            domain="document"
            status={statusKey}
            className="gap-1 whitespace-nowrap"
        >
            <Icon aria-hidden="true" className="size-3" />
            {status}
        </StatusBadge>
    );
}

const REVIEW_STATUS_LABELS: Record<string, string> = {
    approved: 'Disetujui',
    needs_revision: 'Perlu Revisi',
};

type StatusFilter = 'semua' | DokumenStatus;
const PAGE_SIZE = 15;

const fieldError = 'text-sm text-destructive';

function DetailItem({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div>
            <dt className="text-xs font-medium text-muted-foreground">
                {label}
            </dt>
            <dd className="mt-1 text-sm font-medium">{children}</dd>
        </div>
    );
}

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
    const [categoryFilter, setCategoryFilter] = useUrlState(
        'kategori',
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

    const categoryOptions = useMemo(
        () => [
            { value: 'semua', label: 'Semua kategori' },
            ...Array.from(
                new Set(page.props.uploadedDocuments.map((d) => d.category)),
            )
                .sort((a, b) => a.localeCompare(b))
                .map((category) => ({ value: category, label: category })),
        ],
        [page.props.uploadedDocuments],
    );

    const filteredDocuments = useMemo(() => {
        const lower = search.toLowerCase();
        return page.props.uploadedDocuments.filter((doc) => {
            const matchesStatus =
                statusFilter === 'semua' || doc.status === statusFilter;
            const matchesCategory =
                categoryFilter === 'semua' || doc.category === categoryFilter;
            const matchesSearch =
                lower === '' ||
                doc.title.toLowerCase().includes(lower) ||
                doc.fileName.toLowerCase().includes(lower) ||
                doc.category.toLowerCase().includes(lower);
            return matchesStatus && matchesCategory && matchesSearch;
        });
    }, [page.props.uploadedDocuments, statusFilter, categoryFilter, search]);

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
        [search, statusFilter, categoryFilter],
        pageState,
    );

    const statusOptions: {
        value: StatusFilter;
        label: string;
        count?: number;
    }[] = [
        { value: 'semua', label: 'Semua' },
        {
            value: 'Menunggu Review',
            label: 'Menunggu Review',
            count: statusCounts.review,
        },
        {
            value: 'Sebagian direview',
            label: 'Sebagian direview',
            count: statusCounts.partial,
        },
        {
            value: 'Perlu Revisi',
            label: 'Perlu Revisi',
            count: statusCounts.revisi,
        },
        { value: 'Disetujui', label: 'Disetujui' },
    ];

    const pendingRevisiCount = statusCounts.revisi;
    const isFiltered =
        search !== '' || statusFilter !== 'semua' || categoryFilter !== 'semua';

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
                        className="grid gap-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            submitUpload();
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="title">Judul Dokumen</Label>
                            <Input
                                id="title"
                                value={form.data.title}
                                onChange={(event) =>
                                    form.setData('title', event.target.value)
                                }
                                placeholder="Contoh: Draft Bab 3 Metodologi"
                                aria-invalid={Boolean(form.errors.title)}
                                className="max-md:h-11"
                            />
                            {form.errors.title && (
                                <p role="alert" className={fieldError}>
                                    {form.errors.title}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="category">Kategori Dokumen</Label>
                            <Select
                                value={form.data.category}
                                onValueChange={(value) =>
                                    form.setData('category', value)
                                }
                            >
                                <SelectTrigger
                                    id="category"
                                    className="w-full max-md:h-11"
                                >
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
                                <p role="alert" className={fieldError}>
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
                                aria-describedby="document-hint"
                                aria-invalid={Boolean(form.errors.document)}
                                className="cursor-pointer max-md:h-11 max-md:py-2"
                            />
                            <p
                                id="document-hint"
                                className="text-xs text-muted-foreground"
                            >
                                Format: PDF, DOC, DOCX. Ukuran maksimal: 10 MB.
                            </p>
                            {form.errors.document && (
                                <p role="alert" className={fieldError}>
                                    {form.errors.document}
                                </p>
                            )}
                        </div>

                        {form.processing && (
                            <div
                                role="status"
                                className="space-y-3 border-t pt-4"
                            >
                                <div className="flex items-center justify-between gap-3 text-sm">
                                    <div className="flex items-center gap-2 font-medium">
                                        <Spinner className="size-4" />
                                        Mengunggah dokumen...
                                    </div>
                                    {form.progress && (
                                        <span className="text-xs text-muted-foreground tabular-nums">
                                            {form.progress.percentage}%
                                        </span>
                                    )}
                                </div>
                                {form.progress && (
                                    <div className="h-2 overflow-hidden rounded-full bg-muted">
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
                                className="w-full max-md:h-11 sm:w-auto"
                                onClick={() => setIsUploadOpen(false)}
                                disabled={form.processing}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                className="w-full bg-primary text-primary-foreground hover:bg-primary/90 max-md:h-11 sm:w-auto"
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
                        <div className="border-y py-3 text-sm">
                            <p className="font-medium break-words">
                                {documentPendingDelete.title}
                            </p>
                            <p className="mt-1 text-xs break-all text-muted-foreground">
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
                            className="max-md:h-11"
                            onClick={() => setDocumentPendingDelete(null)}
                            disabled={deleteForm.processing}
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            className="max-md:h-11"
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
                            <SheetHeader className="border-b px-6 py-4">
                                <div className="flex items-start gap-3 pr-8">
                                    <div className="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-md bg-primary-muted text-primary-muted-foreground">
                                        <FileText
                                            aria-hidden="true"
                                            className="size-5"
                                        />
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

                            <div className="min-h-0 flex-1 space-y-6 overflow-y-auto px-6 py-5">
                                <dl className="grid gap-4 sm:grid-cols-2">
                                    <DetailItem label="Kategori">
                                        {detailDocument.category}
                                    </DetailItem>
                                    <DetailItem label="Status">
                                        <DokumenStatusBadge
                                            status={detailDocument.status}
                                        />
                                    </DetailItem>
                                    <DetailItem label="Tanggal Upload">
                                        {detailDocument.uploadedAt}
                                    </DetailItem>
                                    <DetailItem label="Ukuran">
                                        {detailDocument.size}
                                    </DetailItem>
                                </dl>

                                <section className="space-y-3 border-t pt-5">
                                    <h3 className="text-sm font-semibold">
                                        Review Dosen
                                    </h3>
                                    {detailDocument.reviews.length > 0 ? (
                                        <ul className="divide-y">
                                            {detailDocument.reviews.map(
                                                (review, index) => (
                                                    <li
                                                        key={`${review.lecturerName}-${index}`}
                                                        className="space-y-2 py-3 first:pt-0 last:pb-0"
                                                    >
                                                        <div className="flex flex-wrap items-start justify-between gap-2">
                                                            <div className="min-w-0">
                                                                <p className="text-sm font-medium break-words">
                                                                    {
                                                                        review.lecturerName
                                                                    }
                                                                </p>
                                                                <p className="text-xs text-muted-foreground">
                                                                    {
                                                                        review.role
                                                                    }
                                                                </p>
                                                            </div>
                                                            <StatusBadge
                                                                domain="document"
                                                                status={
                                                                    review.status
                                                                }
                                                                className="whitespace-nowrap"
                                                            >
                                                                {REVIEW_STATUS_LABELS[
                                                                    review
                                                                        .status
                                                                ] ??
                                                                    'Menunggu Review'}
                                                            </StatusBadge>
                                                        </div>
                                                        {review.notes ? (
                                                            <p className="text-sm break-words whitespace-pre-wrap">
                                                                {review.notes}
                                                            </p>
                                                        ) : (
                                                            <p className="text-xs text-muted-foreground italic">
                                                                Tidak ada
                                                                catatan.
                                                            </p>
                                                        )}
                                                        {review.reviewedAt ? (
                                                            <p className="text-xs text-muted-foreground">
                                                                Direview{' '}
                                                                {
                                                                    review.reviewedAt
                                                                }
                                                            </p>
                                                        ) : null}
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            Belum ada review dari dosen.
                                        </p>
                                    )}
                                </section>

                                <Button
                                    asChild
                                    variant="outline"
                                    className="max-md:h-11"
                                >
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
                <PageHeader
                    title="Upload Dokumen"
                    description={`Kelola dan upload dokumen ${terms.finalWorkLower} Anda`}
                    actions={
                        <Button
                            type="button"
                            className="w-full gap-2 bg-primary text-primary-foreground hover:bg-primary/90 max-md:h-11 sm:w-auto"
                            onClick={() => setIsUploadOpen(true)}
                        >
                            <Upload className="size-4" />
                            Upload Dokumen
                        </Button>
                    }
                />

                {page.props.archivedProject && (
                    <ArchivedMentorshipNotice finalWork={terms.finalWork} />
                )}

                <DataSection title="Panduan Upload">
                    <ul className="grid gap-3 sm:grid-cols-2">
                        {panduanItems.map((item) => (
                            <li
                                key={item}
                                className="flex items-start gap-3 text-sm"
                            >
                                <span className="mt-0.5 inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-success-muted text-success-muted-foreground">
                                    <CheckCircle2
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                </span>
                                <span className="text-pretty">{item}</span>
                            </li>
                        ))}
                    </ul>
                </DataSection>

                <DataSection
                    title="Dokumen yang Diupload"
                    summary={
                        pendingRevisiCount > 0 ? (
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-danger-muted px-3 py-1 text-xs font-medium text-danger-muted-foreground">
                                <AlertTriangle
                                    aria-hidden="true"
                                    className="size-3"
                                />
                                {pendingRevisiCount} perlu revisi
                            </span>
                        ) : undefined
                    }
                >
                    <StickyFilterBar>
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            placeholder="Cari judul atau file..."
                        >
                            <DataSelect
                                label="Kategori"
                                value={categoryFilter}
                                onValueChange={setCategoryFilter}
                                placeholder="Semua kategori"
                                options={categoryOptions}
                                className="md:w-52"
                            />
                        </DataToolbar>

                        <FilterPills
                            label="Status dokumen"
                            value={statusFilter}
                            onValueChange={setStatusFilter}
                            options={statusOptions}
                            itemClassName="max-md:h-11 max-md:px-4"
                        />
                    </StickyFilterBar>

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
                            <table
                                className={cn(
                                    stackedTable.table,
                                    'md:min-w-[760px]',
                                )}
                            >
                                <thead className={stackedTable.thead}>
                                    <tr className="border-b bg-muted/40">
                                        <th className={dataTh}>Nama Dokumen</th>
                                        <th className={dataTh}>Kategori</th>
                                        <th className={dataTh}>Diunggah</th>
                                        <th className={dataTh}>Status</th>
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
                                    {paginated.map((row) => (
                                        <tr
                                            key={`${row.id}-${row.version ?? 'lampiran'}`}
                                            onClick={() =>
                                                setDetailDocument(row)
                                            }
                                            className={cn(
                                                stackedTable.tr,
                                                'cursor-pointer align-top transition-colors hover:bg-muted/30',
                                            )}
                                        >
                                            <td
                                                className={stackedTable.tdPlain}
                                            >
                                                <div className="flex items-center gap-3">
                                                    <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary-muted text-primary-muted-foreground">
                                                        <FileText
                                                            aria-hidden="true"
                                                            className="size-4"
                                                        />
                                                    </div>
                                                    <div className="min-w-0">
                                                        <div
                                                            className="text-sm font-medium break-words max-md:line-clamp-2 md:max-w-56 md:truncate"
                                                            title={row.title}
                                                        >
                                                            {row.title}
                                                        </div>
                                                        <div className="text-xs break-all text-muted-foreground max-md:truncate md:max-w-56 md:truncate">
                                                            {row.fileName}
                                                            {row.version
                                                                ? ` — ${row.version}`
                                                                : ''}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <td className={stackedTable.td}>
                                                {row.category}
                                            </td>

                                            <td
                                                className={cn(
                                                    stackedTable.td,
                                                    'text-muted-foreground',
                                                )}
                                            >
                                                <span className="whitespace-nowrap">
                                                    {row.uploadedAt}
                                                </span>
                                                <span className="block text-xs max-md:ml-1 max-md:inline">
                                                    {row.size}
                                                </span>
                                            </td>

                                            <td
                                                className={cn(
                                                    stackedTable.tdAside,
                                                    'max-md:text-right',
                                                )}
                                            >
                                                <DokumenStatusBadge
                                                    status={row.status}
                                                />
                                                <span className="mt-1 block text-xs text-muted-foreground">
                                                    {row.reviews.length > 0
                                                        ? `${row.reviews.length} review`
                                                        : 'Belum direview'}
                                                </span>
                                            </td>

                                            <td
                                                className={cn(
                                                    stackedTable.tdActions,
                                                    'max-md:order-4 max-md:mt-2 max-md:basis-full',
                                                )}
                                                onClick={(event) =>
                                                    event.stopPropagation()
                                                }
                                            >
                                                <div className="flex items-center justify-end gap-1 max-md:justify-start">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        className="h-8 max-md:h-11 max-md:px-4"
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
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                asChild
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-8 text-muted-foreground hover:text-foreground max-md:size-11"
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
                                                        <TooltipTrigger asChild>
                                                            <span>
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="size-8 text-destructive hover:bg-destructive/10 hover:text-destructive max-md:size-11"
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
                                    ))}
                                </tbody>
                            </table>
                        </DataTableContainer>
                    ) : (
                        <DataTableEmptyState
                            icon={Inbox}
                            title={
                                isFiltered
                                    ? 'Tidak ada dokumen yang sesuai'
                                    : 'Belum ada dokumen yang diupload'
                            }
                            description={
                                isFiltered
                                    ? 'Coba ubah kata kunci atau filter yang dipilih.'
                                    : 'Mulai unggah dokumen pertama Anda untuk memulai proses review.'
                            }
                        />
                    )}
                </DataSection>
            </div>
        </AppLayout>
    );
}
