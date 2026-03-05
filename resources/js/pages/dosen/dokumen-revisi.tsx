import { Head, useForm, usePage } from '@inertiajs/react';
import { Download, FileText } from 'lucide-react';

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
import DosenLayout from '@/layouts/dosen-layout';
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

export default function DosenDokumenRevisiPage() {
    const { documentQueue, flashMessage } = usePage<
        SharedData & DokumenRevisiProps
    >().props;

    const form = useForm({
        status: 'needs_revision' as 'needs_revision' | 'approved',
        revision_notes: '',
    });

    function submitReview(
        documentId: number,
        status: 'needs_revision' | 'approved',
    ) {
        form.setData({
            status,
            revision_notes: '',
        });

        form.transform((data) => ({
            ...data,
            revision_notes: data.revision_notes || null,
        }));

        form.post(`/dosen/dokumen-revisi/${documentId}/review`, {
            preserveScroll: true,
        });
    }

    return (
        <DosenLayout
            breadcrumbs={breadcrumbs}
            title="Dokumen & Revisi"
            subtitle="Tinjau dokumen mahasiswa dan kirim catatan revisi"
        >
            <Head title="Dokumen & Revisi Dosen" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6 lg:gap-8 lg:py-8">
                <Card className="shadow-sm py-0">
                    <CardHeader className="border-b bg-muted/20 px-6 py-4">
                        <CardTitle className="text-lg font-semibold">Antrian Review Dokumen</CardTitle>
                        <CardDescription>
                            Data berasal dari dokumen unggahan mahasiswa
                            bimbingan
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4 pb-6">
                        {flashMessage && (
                            <Alert>
                                <AlertTitle>Berhasil</AlertTitle>
                                <AlertDescription>
                                    {flashMessage}
                                </AlertDescription>
                            </Alert>
                        )}

                        {documentQueue.length > 0 ? (
                            documentQueue.map((doc) => (
                                <div
                                    key={`${doc.id}-${doc.file}`}
                                    className="rounded-xl border bg-background p-5 shadow-sm transition-shadow hover:shadow-md"
                                >
                                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                        <div className="grid gap-1.5">
                                            <p className="text-base font-semibold">
                                                {doc.mahasiswa}
                                            </p>
                                            <p className="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                                <FileText className="size-4" />
                                                {doc.file}
                                            </p>
                                            <p className="text-xs text-muted-foreground mt-1">
                                                Upload: {doc.uploadedAt}
                                            </p>
                                            {doc.revisionNotes && (
                                                <p className="text-xs text-muted-foreground">
                                                    Catatan terakhir:{' '}
                                                    <span className="font-medium text-foreground">{doc.revisionNotes}</span>
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex flex-col items-start lg:items-end gap-3">
                                            <div className="flex items-center gap-2">
                                                <Badge
                                                    variant="soft"
                                                    className={
                                                        doc.status === 'Disetujui'
                                                            ? 'bg-emerald-600/10 text-emerald-600 hover:bg-emerald-600/20'
                                                            : doc.status ===
                                                                'Perlu Revisi'
                                                                ? 'bg-amber-600/10 text-amber-600 hover:bg-amber-600/20'
                                                                : ''
                                                    }
                                                >
                                                    {doc.status}
                                                </Badge>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    className="font-semibold"
                                                    onClick={() => {
                                                        if (doc.fileUrl) {
                                                            window.open(
                                                                doc.fileUrl,
                                                                '_blank',
                                                                'noopener,noreferrer',
                                                            );
                                                        }
                                                    }}
                                                    disabled={!doc.fileUrl}
                                                >
                                                    <Download className="size-4 mr-1.5" />
                                                    Unduh
                                                </Button>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                {doc.status === 'Perlu Review' && (
                                                    <Button
                                                        size="sm"
                                                        variant="soft"
                                                        className="bg-amber-600/10 text-amber-600 hover:bg-amber-600/20 font-semibold"
                                                        disabled={form.processing}
                                                        onClick={() =>
                                                            submitReview(
                                                                doc.id,
                                                                'needs_revision',
                                                            )
                                                        }
                                                    >
                                                        Perlu Revisi
                                                    </Button>
                                                )}
                                                {doc.status === 'Perlu Review' && (
                                                    <Button
                                                        size="sm"
                                                        className="bg-emerald-600 text-white hover:bg-emerald-700 font-semibold"
                                                        disabled={form.processing}
                                                        onClick={() =>
                                                            submitReview(
                                                                doc.id,
                                                                'approved',
                                                            )
                                                        }
                                                    >
                                                        Setujui
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ))
                        ) : (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <span className="mb-4 inline-flex size-12 items-center justify-center rounded-full bg-muted">
                                    <FileText className="size-6 text-muted-foreground" />
                                </span>
                                <p className="text-base font-medium">Belum ada dokumen yang perlu direview</p>
                                <p className="text-sm text-muted-foreground">Dokumen mahasiswa yang butuh persetujuan akan muncul di sini.</p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </DosenLayout>
    );
}
