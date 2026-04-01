import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    CircleAlert,
    Clock,
    Download,
    FileText,
    Inbox,
    Trash2,
    Upload,
} from 'lucide-react';
import { useState } from 'react';

import { EmptyState } from '@/components/empty-state';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
            <Badge className="gap-1 bg-emerald-600 text-white hover:bg-emerald-600/90 dark:bg-emerald-500 dark:hover:bg-emerald-500/90">
                <CheckCircle2 className="size-3" />
                Disetujui
            </Badge>
        );
    }
    if (status === 'Perlu Revisi') {
        return (
            <Badge variant="destructive" className="gap-1">
                <CircleAlert className="size-3" />
                Perlu Revisi
            </Badge>
        );
    }
    return (
        <Badge variant="secondary" className="gap-1 bg-muted text-foreground">
            <Clock className="size-3 text-muted-foreground" />
            Menunggu Review
        </Badge>
    );
}

export default function UploadDokumenPage() {
    const page = usePage<SharedData & UploadDokumenProps>();
    const query = page.url.split('?')[1] ?? '';
    const [isUploadOpen, setIsUploadOpen] = useState(
        new URLSearchParams(query).get('open') === 'unggah',
    );

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

    const hasUploadedDocuments = page.props.uploadedDocuments.length > 0;

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
                        <h1 className="text-xl font-semibold">Upload Dokumen</h1>
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
                <Card className="overflow-hidden py-0 shadow-sm">
                    <CardHeader className="border-b bg-muted/20 px-6 py-4">
                        <CardTitle>Panduan Upload</CardTitle>
                        <CardDescription>
                            Hal yang perlu diperhatikan sebelum mengunggah
                            dokumen.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="pb-6">
                        <div className="grid gap-3">
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
                    </CardContent>
                </Card>

                {/* Dokumen Table — flat, no nested card */}
                <div>
                    <div className="mb-3 flex items-center justify-between">
                        <div>
                            <h2 className="text-base font-semibold">
                                Dokumen yang Diupload
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Daftar dokumen beserta riwayat versi
                            </p>
                        </div>
                        <span className="text-xs text-muted-foreground">
                            {page.props.uploadedDocuments.length} dokumen
                        </span>
                    </div>

                    {hasUploadedDocuments ? (
                        <>
                            {/* Mobile cards */}
                            <div className="grid gap-3 md:hidden">
                                {page.props.uploadedDocuments.map((row) => (
                                    <div
                                        key={`${row.id}-${row.version}-mobile`}
                                        className="rounded-xl border bg-card p-4 shadow-sm"
                                    >
                                        <div className="flex items-start gap-3">
                                            <div className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                <FileText className="size-4" />
                                            </div>
                                            <div className="min-w-0 flex-1 space-y-3">
                                                <div className="space-y-0.5">
                                                    <p className="text-sm font-medium break-words leading-snug">
                                                        {row.title}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground break-all">
                                                        {row.fileName} — {row.version}
                                                    </p>
                                                </div>

                                                <div className="flex flex-wrap gap-2">
                                                    <Badge
                                                        variant="outline"
                                                        className="rounded-full bg-background text-foreground"
                                                    >
                                                        {row.category}
                                                    </Badge>
                                                    <StatusBadge status={row.status} />
                                                </div>

                                                <div className="grid gap-1 text-xs text-muted-foreground">
                                                    <p>Upload: {row.uploadedAt}</p>
                                                    <p>Ukuran: {row.size}</p>
                                                    {row.revisionNotes && (
                                                        <div className="mt-1.5 rounded-md border border-amber-200 bg-amber-50 p-2.5 text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-300">
                                                            <p className="font-semibold mb-1 text-[11px] uppercase tracking-wider opacity-80">
                                                                Catatan Revisi
                                                            </p>
                                                            <p className="text-xs leading-relaxed">
                                                                {row.revisionNotes}
                                                            </p>
                                                        </div>
                                                    )}
                                                </div>

                                                <div className="flex gap-2">
                                                    <Button
                                                        asChild
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        className="flex-1 justify-center"
                                                    >
                                                        <Link href={row.downloadUrl}>
                                                            <Download className="size-4" />
                                                            Unduh
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        className="flex-1 justify-center text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                        disabled={deleteForm.processing}
                                                        onClick={() => {
                                                            deleteForm.delete(
                                                                `/mahasiswa/upload-dokumen/${row.id}`,
                                                                { preserveScroll: true },
                                                            );
                                                        }}
                                                    >
                                                        <Trash2 className="size-4" />
                                                        Hapus
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            {/* Desktop table */}
                            <div className="hidden overflow-hidden rounded-xl border bg-card shadow-sm md:block">
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-sm">
                                        <thead className="border-b bg-muted/30">
                                            <tr>
                                                <th className="px-5 py-3 text-xs font-medium text-muted-foreground">
                                                    Nama Dokumen
                                                </th>
                                                <th className="px-5 py-3 text-xs font-medium text-muted-foreground">
                                                    Kategori
                                                </th>
                                                <th className="px-5 py-3 text-xs font-medium text-muted-foreground">
                                                    Tanggal Upload
                                                </th>
                                                <th className="px-5 py-3 text-xs font-medium text-muted-foreground">
                                                    Ukuran
                                                </th>
                                                <th className="px-5 py-3 text-xs font-medium text-muted-foreground">
                                                    Status
                                                </th>
                                                <th className="w-[22%] px-5 py-3 text-xs font-medium text-muted-foreground">
                                                    Catatan Revisi
                                                </th>
                                                <th className="px-5 py-3 text-right text-xs font-medium text-muted-foreground">
                                                    Aksi
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y bg-background">
                                            {page.props.uploadedDocuments.map((row) => (
                                                <tr
                                                    key={`${row.id}-${row.version}`}
                                                    className="transition-colors hover:bg-muted/20"
                                                >
                                                    {/* Nama Dokumen */}
                                                    <td className="px-5 py-3.5">
                                                        <div className="flex items-center gap-3">
                                                            <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                                <FileText className="size-4" />
                                                            </div>
                                                            <div className="min-w-0">
                                                                <div className="truncate text-sm font-medium max-w-[180px]">
                                                                    {row.title}
                                                                </div>
                                                                <div className="truncate text-xs text-muted-foreground max-w-[180px]">
                                                                    {row.fileName} — {row.version}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    {/* Kategori */}
                                                    <td className="px-5 py-3.5">
                                                        <Badge
                                                            variant="outline"
                                                            className="rounded-full bg-background text-foreground whitespace-nowrap"
                                                        >
                                                            {row.category}
                                                        </Badge>
                                                    </td>

                                                    {/* Tanggal */}
                                                    <td className="px-5 py-3.5 text-xs text-muted-foreground whitespace-nowrap">
                                                        {row.uploadedAt}
                                                    </td>

                                                    {/* Ukuran */}
                                                    <td className="px-5 py-3.5 text-xs text-muted-foreground whitespace-nowrap">
                                                        {row.size}
                                                    </td>

                                                    {/* Status */}
                                                    <td className="px-5 py-3.5">
                                                        <StatusBadge status={row.status} />
                                                    </td>

                                                    {/* Catatan Revisi */}
                                                    <td className="px-5 py-3.5">
                                                        {row.revisionNotes ? (
                                                            <div className="max-h-16 overflow-y-auto rounded border border-amber-200 bg-amber-50/50 p-2 text-xs text-muted-foreground whitespace-pre-wrap dark:border-amber-900/30 dark:bg-amber-950/10 dark:text-amber-200/80">
                                                                {row.revisionNotes}
                                                            </div>
                                                        ) : (
                                                            <span className="text-xs italic text-muted-foreground">
                                                                —
                                                            </span>
                                                        )}
                                                    </td>

                                                    {/* Aksi */}
                                                    <td className="px-5 py-3.5">
                                                        <div className="flex items-center justify-end gap-1">
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <Button
                                                                        asChild
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="size-8 text-muted-foreground hover:text-foreground"
                                                                    >
                                                                        <Link href={row.downloadUrl}>
                                                                            <Download className="size-4" />
                                                                        </Link>
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    Unduh Dokumen
                                                                </TooltipContent>
                                                            </Tooltip>
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="size-8 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                                        disabled={deleteForm.processing}
                                                                        onClick={() => {
                                                                            deleteForm.delete(
                                                                                `/mahasiswa/upload-dokumen/${row.id}`,
                                                                                { preserveScroll: true },
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
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </>
                    ) : (
                        <EmptyState
                            icon={Inbox}
                            title="Belum ada dokumen yang diupload"
                            description="Mulai unggah dokumen pertama Anda untuk memulai proses review."
                        />
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
