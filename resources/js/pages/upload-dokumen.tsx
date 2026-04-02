import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    CircleAlert,
    Clock,
    Download,
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
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import * as routes from '@/routes';
import { type BreadcrumbItem, type SharedData } from '@/types';

type DokumenStatus = 'Menunggu Review' | 'Disetujui' | 'Perlu Revisi';

type UploadedDokumenRow = {
    id: number;
    title: string;
    category: string;
    version: string;
    uploadedAt: string;
    fileName: string;
    size: string;
    status: DokumenStatus;
    revisionNotes?: string | null;
    downloadUrl: string;
};

type UploadDokumenProps = {
    uploadedDocuments: UploadedDokumenRow[];
    flashMessage?: string | null;
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

type StatusFilter = 'semua' | DokumenStatus;
const PAGE_SIZE = 15;

export default function UploadDokumenPage() {
    const page = usePage<SharedData & UploadDokumenProps>();
    const query = page.url.split('?')[1] ?? '';
    const [isUploadOpen, setIsUploadOpen] = useState(
        new URLSearchParams(query).get('open') === 'unggah',
    );

    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState<StatusFilter>('semua');

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
        form.post('/mahasiswa/upload-dokumen', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setIsUploadOpen(false);
            },
        });
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
        totalPages,
        paginated,
        totalItems,
    } = usePagination(filteredDocuments, PAGE_SIZE, [search, statusFilter]);

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
            subtitle="Kelola dan upload dokumen skripsi Anda"
        >
            <Head title="Upload Dokumen" />

            {/* Upload Dialog */}
            <Dialog open={isUploadOpen} onOpenChange={setIsUploadOpen}>
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
                            />
                            {form.errors.title && (
                                <p className="text-xs text-destructive">
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
                                <SelectTrigger id="category">
                                    <SelectValue placeholder="Pilih kategori dokumen" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="draft-tugas-akhir">
                                        Draft Skripsi
                                    </SelectItem>
                                    <SelectItem value="proposal">
                                        Proposal
                                    </SelectItem>
                                    <SelectItem value="laporan">
                                        Laporan
                                    </SelectItem>
                                    <SelectItem value="lampiran">
                                        Lampiran
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

                        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full sm:w-auto"
                                onClick={() => setIsUploadOpen(false)}
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
                                Upload
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6">
                {/* Page header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Upload Dokumen
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Kelola dan upload dokumen skripsi Anda
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
                {page.props.flashMessage && (
                    <Alert>
                        <AlertTitle>Berhasil</AlertTitle>
                        <AlertDescription>
                            {page.props.flashMessage}
                        </AlertDescription>
                    </Alert>
                )}

                {/* Panduan Upload */}
                <div className="overflow-hidden rounded-xl border bg-card py-0 shadow-sm">
                    <div className="border-b bg-muted/20 px-6 py-4">
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
                        <DataTableContainer>
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
                                        <th className="w-[22%] px-5 py-3 font-medium">
                                            Catatan Revisi
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
                                                key={`${row.id}-${row.version}`}
                                                className={cn(
                                                    'transition-colors hover:bg-muted/20',
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
                                                                {row.fileName} —{' '}
                                                                {row.version}
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

                                                {/* Catatan Revisi */}
                                                <td className="px-5 py-3.5">
                                                    {row.revisionNotes ? (
                                                        <div className="max-h-16 overflow-y-auto rounded border border-amber-200 bg-amber-50/50 p-2 text-xs whitespace-pre-wrap text-muted-foreground dark:border-amber-900/30 dark:bg-amber-950/10 dark:text-amber-200/80">
                                                            {row.revisionNotes}
                                                        </div>
                                                    ) : (
                                                        <span className="text-xs text-muted-foreground italic">
                                                            —
                                                        </span>
                                                    )}
                                                </td>

                                                {/* Aksi */}
                                                <td className="px-5 py-3.5">
                                                    <div className="flex items-center justify-end gap-1">
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
                                                                    <Link
                                                                        href={
                                                                            row.downloadUrl
                                                                        }
                                                                    >
                                                                        <Download className="size-4" />
                                                                    </Link>
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
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="size-8 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                                    disabled={
                                                                        deleteForm.processing
                                                                    }
                                                                    onClick={() => {
                                                                        deleteForm.delete(
                                                                            `/mahasiswa/upload-dokumen/${row.id}`,
                                                                            {
                                                                                preserveScroll: true,
                                                                            },
                                                                        );
                                                                    }}
                                                                >
                                                                    <Trash2 className="size-4" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                Hapus Dokumen
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                            <DataTablePagination
                                currentPage={currentPage}
                                totalPages={totalPages}
                                totalItems={totalItems}
                                pageSize={PAGE_SIZE}
                                onPageChange={setPage}
                                itemLabel="dokumen"
                            />
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
