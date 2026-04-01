import { Head, useForm, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    Download,
    FileText,
    Search,
    XCircle,
} from 'lucide-react';
import { useMemo, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
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
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
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

type StatusFilter = 'semua' | 'Perlu Review' | 'Perlu Revisi' | 'Disetujui';

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

    const statusCounts = useMemo(
        () => ({
            semua: documentQueue.length,
            'Perlu Review': documentQueue.filter(
                (d) => d.status === 'Perlu Review',
            ).length,
            'Perlu Revisi': documentQueue.filter(
                (d) => d.status === 'Perlu Revisi',
            ).length,
            Disetujui: documentQueue.filter((d) => d.status === 'Disetujui')
                .length,
        }),
        [documentQueue],
    );

    const filterTabs: { label: string; value: StatusFilter }[] = [
        { label: 'Semua', value: 'semua' },
        { label: 'Perlu Review', value: 'Perlu Review' },
        { label: 'Perlu Revisi', value: 'Perlu Revisi' },
        { label: 'Disetujui', value: 'Disetujui' },
    ];

    return (
        <DosenLayout
            breadcrumbs={breadcrumbs}
            title="Dokumen & Revisi"
            subtitle="Tinjau dokumen mahasiswa dan kirim catatan revisi"
        >
            <Head title="Dokumen & Revisi Dosen" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6 lg:gap-8 lg:py-8">
                <Card className="gap-0 overflow-hidden py-0 shadow-sm">
                    {/* Header */}
                    <CardHeader className="border-b bg-muted/20 px-6 py-4">
                        <div className="flex flex-col gap-4">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                {/* Filter tabs */}
                                <div className="flex flex-wrap gap-1.5">
                                    {filterTabs.map((tab) => (
                                        <button
                                            key={tab.value}
                                            type="button"
                                            onClick={() =>
                                                setStatusFilter(tab.value)
                                            }
                                            className={cn(
                                                'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium transition-colors',
                                                statusFilter === tab.value
                                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                                    : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground',
                                            )}
                                        >
                                            {tab.label}
                                            <span
                                                className={cn(
                                                    'rounded-full px-1.5 py-0.5 text-[10px] leading-none font-semibold',
                                                    statusFilter === tab.value
                                                        ? 'bg-white/20 text-white'
                                                        : 'bg-background text-foreground',
                                                )}
                                            >
                                                {
                                                    statusCounts[
                                                        tab.value as keyof typeof statusCounts
                                                    ]
                                                }
                                            </span>
                                        </button>
                                    ))}
                                </div>

                                {/* Search */}
                                <div className="relative w-full sm:w-64">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        value={search}
                                        onChange={(e) =>
                                            setSearch(e.target.value)
                                        }
                                        placeholder="Cari mahasiswa atau file..."
                                        className="h-8 pl-8 text-sm"
                                    />
                                </div>
                            </div>

                            {/* Status filter (secondary: sort) */}
                            <div className="flex items-center justify-between gap-3">
                                <p className="text-xs text-muted-foreground">
                                    {filteredDocuments.length ===
                                    documentQueue.length
                                        ? `${documentQueue.length} dokumen`
                                        : `${filteredDocuments.length} dari ${documentQueue.length} dokumen`}
                                </p>
                                <div className="flex items-center gap-2">
                                    <Label
                                        htmlFor="sort-select"
                                        className="shrink-0 text-xs text-muted-foreground"
                                    >
                                        Urutkan:
                                    </Label>
                                    <Select defaultValue="terbaru">
                                        <SelectTrigger
                                            id="sort-select"
                                            className="h-7 w-32 text-xs"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="terbaru">
                                                Terbaru
                                            </SelectItem>
                                            <SelectItem value="terlama">
                                                Terlama
                                            </SelectItem>
                                            <SelectItem value="nama">
                                                Nama A-Z
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent className="p-0">
                        {/* Flash message */}
                        {flashMessage && (
                            <div className="border-b bg-emerald-500/10 px-6 py-3 text-sm font-medium text-emerald-700 dark:text-emerald-400">
                                <div className="flex items-center gap-2">
                                    <CheckCircle2 className="size-4 shrink-0" />
                                    {flashMessage}
                                </div>
                            </div>
                        )}

                        {filteredDocuments.length > 0 ? (
                            <div className="overflow-x-auto">
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
                                        {filteredDocuments.map((doc) => (
                                            <tr
                                                key={`${doc.id}-${doc.file}`}
                                                className="transition-colors hover:bg-muted/20"
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
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <div className="flex flex-col items-center justify-center px-6 py-20 text-center">
                                <span className="mb-4 inline-flex size-14 items-center justify-center rounded-full bg-muted">
                                    <FileText className="size-6 text-muted-foreground" />
                                </span>
                                <p className="text-base font-semibold">
                                    {search || statusFilter !== 'semua'
                                        ? 'Tidak ada dokumen yang sesuai'
                                        : 'Belum ada dokumen yang perlu direview'}
                                </p>
                                <p className="mt-1.5 text-sm text-muted-foreground">
                                    {search || statusFilter !== 'semua'
                                        ? 'Coba ubah kata kunci atau filter yang dipilih.'
                                        : 'Dokumen mahasiswa yang butuh persetujuan akan muncul di sini.'}
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>
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
