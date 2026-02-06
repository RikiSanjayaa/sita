import { Head, Link, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    CircleAlert,
    Clock,
    Download,
    Eye,
    FileText,
    Trash2,
    Upload,
} from 'lucide-react';
import { useState } from 'react';

import { Alert, AlertDescription } from '@/components/ui/alert';
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
import { type BreadcrumbItem } from '@/types';

type DokumenStatus = 'Menunggu Review' | 'Disetujui' | 'Perlu Revisi';

type UploadedDokumenRow = {
    nama: string;
    versi: string;
    kategori: string;
    tanggalUpload: string;
    ukuran: string;
    status: DokumenStatus;
};

type GroupDocEvent = {
    id: string;
    fileName: string;
    category: string;
    uploadedAt: string;
    uploadedBy: string;
    version: string;
};

const MAX_FILE_BYTES = 10 * 1024 * 1024;
const ACCEPTED_EXTENSIONS = ['pdf', 'docx', 'pptx'] as const;
const ACCEPT_ATTR = '.pdf,.docx,.pptx';
const GROUP_DOC_EVENTS_KEY = 'sita:group-doc-events:v1';

const categoryLabels: Record<string, string> = {
    'draft-tugas-akhir': 'Draft Tugas Akhir',
    proposal: 'Proposal',
    laporan: 'Laporan',
    'slide-presentasi': 'Slide Presentasi',
    lampiran: 'Lampiran',
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
    'Format file yang didukung: PDF, DOCX, PPTX',
    'Ukuran maksimal file: 10 MB',
    'Gunakan penamaan file yang jelas dan deskriptif',
    'Dokumen akan direview oleh pembimbing dalam 2-3 hari kerja',
];

const uploadedDokumen: UploadedDokumenRow[] = [
    {
        nama: 'Draft Tugas Akhir v2.0.pdf',
        versi: 'v2.0',
        kategori: 'Draft Tugas Akhir',
        tanggalUpload: '23 Januari 2026',
        ukuran: '2.4 MB',
        status: 'Menunggu Review',
    },
    {
        nama: 'Proposal Tugas Akhir.pdf',
        versi: 'v1.0',
        kategori: 'Proposal',
        tanggalUpload: '16 Januari 2026',
        ukuran: '1.8 MB',
        status: 'Disetujui',
    },
    {
        nama: 'Slide Presentasi Sidang.pptx',
        versi: 'v1.0',
        kategori: 'Slide Presentasi',
        tanggalUpload: '20 Januari 2026',
        ukuran: '5.2 MB',
        status: 'Perlu Revisi',
    },
    {
        nama: 'Draft Tugas Akhir v1.0.pdf',
        versi: 'v1.0',
        kategori: 'Draft Tugas Akhir',
        tanggalUpload: '10 Januari 2026',
        ukuran: '2.1 MB',
        status: 'Disetujui',
    },
];

function formatBytes(bytes: number) {
    if (!Number.isFinite(bytes) || bytes <= 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const order = Math.min(
        Math.floor(Math.log(bytes) / Math.log(1024)),
        units.length - 1,
    );
    const value = bytes / 1024 ** order;
    const rounded =
        value >= 10 || order === 0 ? value.toFixed(0) : value.toFixed(1);
    return `${rounded} ${units[order]}`;
}

function getExtension(filename: string) {
    const lastDot = filename.lastIndexOf('.');
    if (lastDot === -1) return '';
    return filename.slice(lastDot + 1).toLowerCase();
}

function validateFile(file: File) {
    if (file.size > MAX_FILE_BYTES) {
        return 'Ukuran file melebihi 10 MB. Silakan pilih file lain.';
    }

    const ext = getExtension(file.name);
    if (
        ext &&
        !ACCEPTED_EXTENSIONS.includes(
            ext as (typeof ACCEPTED_EXTENSIONS)[number],
        )
    ) {
        return 'Format file tidak didukung. Gunakan PDF, DOCX, atau PPTX.';
    }

    return null;
}

function KategoriBadge({ kategori }: { kategori: string }) {
    return (
        <Badge
            variant="outline"
            className="rounded-full bg-background text-foreground"
        >
            {kategori}
        </Badge>
    );
}

function StatusBadge({ status }: { status: DokumenStatus }) {
    if (status === 'Disetujui') {
        return (
            <Badge className="gap-1 bg-slate-900 text-white hover:bg-slate-900">
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

function ActionIconButton({
    label,
    tone = 'default',
    icon: Icon,
}: {
    label: string;
    tone?: 'default' | 'danger';
    icon: typeof Eye;
}) {
    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            className={
                tone === 'danger'
                    ? 'h-8 w-8 text-destructive hover:bg-destructive/10 hover:text-destructive'
                    : 'h-8 w-8 text-muted-foreground hover:bg-muted hover:text-foreground'
            }
            aria-label={label}
            title={label}
        >
            <Icon className="size-4" />
        </Button>
    );
}

export default function UploadDokumen() {
    const page = usePage();
    const query = page.url.split('?')[1] ?? '';
    const [isUploadOpen, setIsUploadOpen] = useState(
        new URLSearchParams(query).get('open') === 'unggah',
    );
    const [kategori, setKategori] = useState<string>('draft-tugas-akhir');
    const [file, setFile] = useState<File | null>(null);
    const [fileError, setFileError] = useState<string | null>(null);
    const [isUploadSuccessOpen, setIsUploadSuccessOpen] = useState(false);

    function publishUploadToGroupChat(nextFile: File, selectedCategory: string) {
        if (typeof window === 'undefined') return;

        const raw = window.localStorage.getItem(GROUP_DOC_EVENTS_KEY);
        const currentEvents = raw
            ? (JSON.parse(raw) as GroupDocEvent[])
            : [];

        const nextEvent: GroupDocEvent = {
            id: `evt-${Date.now()}`,
            fileName: nextFile.name,
            category: categoryLabels[selectedCategory] ?? selectedCategory,
            uploadedAt: new Date().toISOString(),
            uploadedBy: 'Mahasiswa',
            version: 'v1.0',
        };

        window.localStorage.setItem(
            GROUP_DOC_EVENTS_KEY,
            JSON.stringify([...currentEvents, nextEvent]),
        );
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
                        onSubmit={(e) => {
                            e.preventDefault();
                            if (!file || !kategori) return;
                            publishUploadToGroupChat(file, kategori);
                            setIsUploadOpen(false);
                            setIsUploadSuccessOpen(true);
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="kategori-dokumen">
                                Kategori Dokumen
                            </Label>
                            <Select value={kategori} onValueChange={setKategori}>
                                <SelectTrigger id="kategori-dokumen">
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
                                    <SelectItem value="slide-presentasi">
                                        Slide Presentasi
                                    </SelectItem>
                                    <SelectItem value="lampiran">
                                        Lampiran
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-muted-foreground">
                                Pastikan kategori sesuai dengan jenis dokumen agar proses review lebih cepat.
                            </p>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="file-dokumen">File Dokumen</Label>
                            <Input
                                id="file-dokumen"
                                type="file"
                                accept={ACCEPT_ATTR}
                                onChange={(e) => {
                                    const nextFile =
                                        e.currentTarget.files?.[0] ?? null;

                                    if (!nextFile) {
                                        setFile(null);
                                        setFileError(null);
                                        return;
                                    }

                                    const error = validateFile(nextFile);
                                    setFileError(error);
                                    setFile(error ? null : nextFile);
                                }}
                            />
                            <p className="text-xs text-muted-foreground">
                                Format: PDF, DOCX, PPTX. Ukuran maksimal: 10 MB.
                            </p>
                            {file ? (
                                <p className="text-xs text-muted-foreground">
                                    File terpilih:{' '}
                                    <span className="font-medium text-foreground">
                                        {file.name}
                                    </span>{' '}
                                    ({formatBytes(file.size)})
                                </p>
                            ) : null}
                            {fileError ? (
                                <Alert variant="destructive">
                                    <AlertDescription>{fileError}</AlertDescription>
                                </Alert>
                            ) : null}
                        </div>

                        <Alert className="border-sky-100 bg-sky-50 text-sky-900">
                            <AlertDescription className="text-sky-900">
                                <span className="font-medium">Catatan:</span> Pastikan dokumen sudah sesuai dengan format dan panduan yang diberikan sebelum mengupload.
                            </AlertDescription>
                        </Alert>

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
                                className="bg-slate-900 text-white hover:bg-slate-900/90"
                                disabled={!kategori || !file}
                            >
                                Upload
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={isUploadSuccessOpen}
                onOpenChange={setIsUploadSuccessOpen}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Upload berhasil</DialogTitle>
                        <DialogDescription>
                            Notifikasi dokumen baru sudah dikirim ke Group Chat
                            Bimbingan. Dosen pembimbing dapat langsung melihat
                            dan mengunduh dokumen.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="flex items-center justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setIsUploadSuccessOpen(false)}
                        >
                            Tutup
                        </Button>
                        <Button
                            asChild
                            className="bg-slate-900 text-white hover:bg-slate-900/90"
                        >
                            <Link href={routes.pesan().url}>
                                Buka Group Chat
                            </Link>
                        </Button>
                    </div>
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
                        className="h-10 gap-2 bg-slate-900 text-white hover:bg-slate-900/90"
                        onClick={() => setIsUploadOpen(true)}
                    >
                        <Upload className="size-4" />
                        Upload Dokumen
                    </Button>
                </div>

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
                                    <span className="mt-0.5 inline-flex size-6 items-center justify-center rounded-full bg-green-50 text-green-700 ring-1 ring-green-200">
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
                            Daftar semua dokumen yang telah Anda upload
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
                                    {uploadedDokumen.map((row) => (
                                        <tr
                                            key={`${row.nama}-${row.versi}`}
                                            className="group border-b transition-colors last:border-b-0 hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="flex items-start gap-3">
                                                    <span className="mt-0.5 inline-flex size-8 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                                        <FileText className="size-4" />
                                                    </span>
                                                    <div className="min-w-0">
                                                        <div className="text-sm font-medium">
                                                            {row.nama}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {row.versi}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <KategoriBadge
                                                    kategori={row.kategori}
                                                />
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {row.tanggalUpload}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {row.ukuran}
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    status={row.status}
                                                />
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex justify-end gap-1">
                                                    <ActionIconButton
                                                        label="Lihat"
                                                        icon={Eye}
                                                    />
                                                    <ActionIconButton
                                                        label="Unduh"
                                                        icon={Download}
                                                    />
                                                    <ActionIconButton
                                                        label="Hapus"
                                                        tone="danger"
                                                        icon={Trash2}
                                                    />
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
