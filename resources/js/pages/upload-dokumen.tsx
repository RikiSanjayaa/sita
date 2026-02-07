import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    CircleAlert,
    Clock,
    Download,
    FileText,
    Trash2,
    Upload,
} from 'lucide-react';
import { useState } from 'react';

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
import { Separator } from '@/components/ui/separator';
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
    downloadUrl: string;
};

type UploadDokumenProps = {
    uploadedDocuments: UploadedDokumenRow[];
    flashMessage?: string | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: routes.dashboard().url,
    },
    {
        title: 'Upload Dokumen',
        href: routes.uploadDokumen().url,
    },
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

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Upload Dokumen"
            subtitle="Kelola dan upload dokumen tugas akhir Anda"
        >
            <Head title="Upload Dokumen" />

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
                                        Draft Tugas Akhir
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

                        <div className="flex items-center justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
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
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Upload Dokumen
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Kelola dan upload dokumen tugas akhir Anda
                        </p>
                    </div>
                    <Button
                        className="h-10 gap-2 bg-primary text-primary-foreground hover:bg-primary/90"
                        onClick={() => setIsUploadOpen(true)}
                    >
                        <Upload className="size-4" />
                        Upload Dokumen
                    </Button>
                </div>

                {page.props.flashMessage && (
                    <Alert>
                        <AlertTitle>Berhasil</AlertTitle>
                        <AlertDescription>
                            {page.props.flashMessage}
                        </AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Panduan Upload</CardTitle>
                    </CardHeader>
                    <Separator />
                    <CardContent className="pt-6">
                        <div className="grid gap-3">
                            {panduanItems.map((item) => (
                                <div
                                    key={item}
                                    className="flex items-start gap-3 text-sm"
                                >
                                    <span className="mt-0.5 inline-flex size-6 items-center justify-center rounded-full bg-green-50 text-green-700 ring-1 ring-green-200 dark:bg-green-500/15 dark:text-green-300 dark:ring-green-500/40">
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

                <Card>
                    <CardHeader className="gap-1">
                        <CardTitle>Dokumen yang Diupload</CardTitle>
                        <CardDescription>
                            Daftar dokumen beserta riwayat versi
                        </CardDescription>
                    </CardHeader>
                    <Separator />
                    <CardContent className="pt-6">
                        <div className="overflow-hidden rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-background">
                                    <tr className="border-b">
                                        <th className="px-4 py-3 font-medium">
                                            Nama Dokumen
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Kategori
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Tanggal Upload
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Ukuran
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Status
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            Aksi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {page.props.uploadedDocuments.map((row) => (
                                        <tr
                                            key={`${row.id}-${row.version}`}
                                            className="group border-b transition-colors last:border-b-0 hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="flex items-start gap-3">
                                                    <span className="mt-0.5 inline-flex size-8 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                                        <FileText className="size-4" />
                                                    </span>
                                                    <div className="min-w-0">
                                                        <div className="text-sm font-medium">
                                                            {row.title}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {row.fileName} -{' '}
                                                            {row.version}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant="outline"
                                                    className="rounded-full bg-background text-foreground"
                                                >
                                                    {row.category}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {row.uploadedAt}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {row.size}
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    status={row.status}
                                                />
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        asChild
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-8 w-8 text-muted-foreground hover:bg-muted hover:text-foreground"
                                                    >
                                                        <Link
                                                            href={
                                                                row.downloadUrl
                                                            }
                                                        >
                                                            <Download className="size-4" />
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-8 w-8 text-destructive hover:bg-destructive/10 hover:text-destructive"
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
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
