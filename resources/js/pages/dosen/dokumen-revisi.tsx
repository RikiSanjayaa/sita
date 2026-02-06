import { Head, useForm, usePage } from '@inertiajs/react';
import { Download, FileText, MessageSquareMore } from 'lucide-react';
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
import { Input } from '@/components/ui/input';
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

    const [notes, setNotes] = useState<Record<number, string>>({});

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
            revision_notes: (notes[documentId] ?? '').trim(),
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

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Antrian Review Dokumen</CardTitle>
                        <CardDescription>
                            Data berasal dari dokumen unggahan mahasiswa
                            bimbingan
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {flashMessage && (
                            <Alert>
                                <AlertTitle>Berhasil</AlertTitle>
                                <AlertDescription>
                                    {flashMessage}
                                </AlertDescription>
                            </Alert>
                        )}

                        {documentQueue.map((doc) => (
                            <div
                                key={`${doc.id}-${doc.file}`}
                                className="rounded-lg border bg-background p-4"
                            >
                                <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div className="grid gap-1">
                                        <p className="text-sm font-semibold">
                                            {doc.mahasiswa}
                                        </p>
                                        <p className="inline-flex items-center gap-2 text-sm text-muted-foreground">
                                            <FileText className="size-4" />
                                            {doc.file}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Upload: {doc.uploadedAt}
                                        </p>
                                        {doc.revisionNotes && (
                                            <p className="text-xs text-muted-foreground">
                                                Catatan terakhir:{' '}
                                                {doc.revisionNotes}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Badge
                                            variant={
                                                doc.status === 'Disetujui'
                                                    ? 'default'
                                                    : doc.status ===
                                                        'Perlu Revisi'
                                                      ? 'destructive'
                                                      : 'secondary'
                                            }
                                            className={
                                                doc.status === 'Disetujui'
                                                    ? 'bg-emerald-600 text-white dark:bg-emerald-500'
                                                    : ''
                                            }
                                        >
                                            {doc.status}
                                        </Badge>
                                        <Button
                                            size="sm"
                                            variant="outline"
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
                                            <Download className="size-4" />
                                            Unduh
                                        </Button>
                                    </div>
                                </div>
                                <div className="mt-3 flex flex-col gap-2 sm:flex-row">
                                    <Input
                                        value={notes[doc.id] ?? ''}
                                        onChange={(event) =>
                                            setNotes((prev) => ({
                                                ...prev,
                                                [doc.id]: event.target.value,
                                            }))
                                        }
                                        placeholder="Tulis catatan revisi singkat..."
                                    />
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        disabled={form.processing}
                                        onClick={() =>
                                            submitReview(
                                                doc.id,
                                                'needs_revision',
                                            )
                                        }
                                    >
                                        <MessageSquareMore className="size-4" />
                                        Perlu Revisi
                                    </Button>
                                    <Button
                                        size="sm"
                                        className="bg-primary text-primary-foreground hover:bg-primary/90"
                                        disabled={form.processing}
                                        onClick={() =>
                                            submitReview(doc.id, 'approved')
                                        }
                                    >
                                        Setujui
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </DosenLayout>
    );
}
