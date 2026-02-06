import { Head, Link } from '@inertiajs/react';
import * as React from 'react';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { create as uploadDokumenCreate } from '@/routes/upload-dokumen';
import { type BreadcrumbItem } from '@/types';

const MAX_FILE_BYTES = 10 * 1024 * 1024;
const ACCEPTED_EXTENSIONS = ['pdf', 'docx', 'pptx'] as const;
const ACCEPT_ATTR = '.pdf,.docx,.pptx';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: routes.dashboard().url,
    },
    {
        title: 'Upload Dokumen',
        href: routes.uploadDokumen().url,
    },
    {
        title: 'Unggah Dokumen',
        href: uploadDokumenCreate().url,
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

export default function UploadDokumenForm() {
    const [kategori, setKategori] = React.useState<string>('draft-tugas-akhir');
    const [file, setFile] = React.useState<File | null>(null);
    const [fileError, setFileError] = React.useState<string | null>(null);

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Upload Dokumen"
            subtitle="Pilih kategori dan file yang akan diupload"
        >
            <Head title="Upload Dokumen" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col px-4 py-6 md:px-6">
                <div className="flex flex-1 items-start justify-center py-10">
                    <Card className="w-full max-w-xl">
                        <CardHeader className="gap-1">
                            <CardTitle className="text-xl">
                                Upload Dokumen
                            </CardTitle>
                            <CardDescription>
                                Pilih kategori dan file yang akan diupload
                            </CardDescription>
                        </CardHeader>
                        <Separator />
                        <CardContent className="pt-6">
                            <form
                                className="grid gap-5"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                }}
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="kategori-dokumen">
                                        Kategori Dokumen
                                    </Label>
                                    <Select
                                        value={kategori}
                                        onValueChange={setKategori}
                                    >
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
                                        Pastikan kategori sesuai dengan jenis
                                        dokumen agar proses review lebih cepat.
                                    </p>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="file-dokumen">
                                        File Dokumen
                                    </Label>
                                    <Input
                                        id="file-dokumen"
                                        type="file"
                                        accept={ACCEPT_ATTR}
                                        onChange={(e) => {
                                            const nextFile =
                                                e.currentTarget.files?.[0] ??
                                                null;

                                            if (!nextFile) {
                                                setFile(null);
                                                setFileError(null);
                                                return;
                                            }

                                            const error =
                                                validateFile(nextFile);
                                            setFileError(error);
                                            setFile(error ? null : nextFile);
                                        }}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Format: PDF, DOCX, PPTX. Ukuran
                                        maksimal: 10 MB.
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
                                            <AlertDescription>
                                                {fileError}
                                            </AlertDescription>
                                        </Alert>
                                    ) : null}
                                </div>

                                <Alert className="border-sky-100 bg-sky-50 text-sky-900">
                                    <AlertDescription className="text-sky-900">
                                        <span className="font-medium">
                                            Catatan:
                                        </span>{' '}
                                        Pastikan dokumen sudah sesuai dengan
                                        format dan panduan yang diberikan
                                        sebelum mengupload.
                                    </AlertDescription>
                                </Alert>

                                <div className="flex items-center justify-end gap-2">
                                    <Button asChild variant="outline">
                                        <Link href={routes.uploadDokumen().url}>
                                            Batal
                                        </Link>
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
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
