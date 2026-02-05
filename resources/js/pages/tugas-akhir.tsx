import { Head } from '@inertiajs/react';
import {
    Calendar,
    CheckCircle2,
    CircleAlert,
    Download,
    Eye,
    FileText,
    MessageSquareText,
    Pencil,
    PencilLine,
    Upload,
} from 'lucide-react';
import { useMemo, useState } from 'react';

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
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';

import { dashboard, tugasAkhir } from '@/routes';

type TabKey = 'judul' | 'proposal' | 'dokumen';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Tugas Akhir Saya',
        href: tugasAkhir().url,
    },
];

type TimelineTone = 'info' | 'danger' | 'warning' | 'success';

type TimelineItem = {
    title: string;
    name: string;
    time: string;
    icon: typeof PencilLine;
    tone: TimelineTone;
};

const timelineItems: TimelineItem[] = [
    {
        title: 'Pengajuan Pertama',
        name: 'Muhammad Akbar',
        time: '8 Januari 2026, 10:00',
        icon: CheckCircle2,
        tone: 'info',
    },
    {
        title: 'Perlu Revisi',
        name: 'Dr. Budi Santoso, M.Kom.',
        time: '10 Januari 2026, 16:45',
        icon: CircleAlert,
        tone: 'danger',
    },
    {
        title: 'Revisi Diajukan',
        name: 'Muhammad Akbar',
        time: '12 Januari 2026, 09:15',
        icon: PencilLine,
        tone: 'warning',
    },
    {
        title: 'Judul Disetujui',
        name: 'Dr. Budi Santoso, M.Kom.',
        time: '15 Januari 2026, 14:30',
        icon: CheckCircle2,
        tone: 'success',
    },
];

type ProposalVersion = {
    version: string;
    fileName: string;
    uploadedAt: string;
    size: string;
    status: 'Disetujui' | 'Revisi';
    note: string;
};

const proposalVersions: ProposalVersion[] = [
    {
        version: 'v3.0',
        fileName: 'Proposal_TA_MuhammadAkbar_v3.pdf',
        uploadedAt: '15 Januari 2026, 10:30',
        size: '2.4 MB',
        status: 'Disetujui',
        note: 'Proposal disetujui. Silakan lanjutkan ke tahap penelitian.',
    },
    {
        version: 'v2.0',
        fileName: 'Proposal_TA_MuhammadAkbar_v2.pdf',
        uploadedAt: '12 Januari 2026, 14:20',
        size: '2.3 MB',
        status: 'Revisi',
        note: 'Perbaiki metodologi penelitian pada Bab 3. Tambahkan referensi lebih detail.',
    },
    {
        version: 'v1.0',
        fileName: 'Proposal_TA_MuhammadAkbar_v1.pdf',
        uploadedAt: '8 Januari 2026, 09:15',
        size: '2.1 MB',
        status: 'Revisi',
        note: 'Tinjauan pustaka perlu diperluas. Jelaskan lebih detail tentang dataset yang akan digunakan.',
    },
];

type DokumenStatus = 'Disetujui' | 'Ditinjau' | 'Belum Diunggah';
type DokumenType = 'Dokumen Utama' | 'Pendukung' | 'Administrasi';

type DokumenRow = {
    title: string;
    description: string;
    tipe: DokumenType;
    tenggatWaktu: string;
    versi: string;
    status: DokumenStatus;
    actions: Array<'view' | 'download' | 'upload'>;
};

const dokumenRows: DokumenRow[] = [
    {
        title: 'Draft Tugas Akhir',
        description: 'Dokumen lengkap tugas akhir (semua bab)',
        tipe: 'Dokumen Utama',
        tenggatWaktu: '1 Maret 2026',
        versi: 'v2.0',
        status: 'Disetujui',
        actions: ['view', 'download', 'upload'],
    },
    {
        title: 'Slide Presentasi',
        description: 'Presentasi untuk sidang tugas akhir',
        tipe: 'Pendukung',
        tenggatWaktu: '10 Maret 2026',
        versi: 'v1.0',
        status: 'Ditinjau',
        actions: ['view', 'download', 'upload'],
    },
    {
        title: 'Kode Program / Source Code',
        description: 'File source code implementasi sistem',
        tipe: 'Pendukung',
        tenggatWaktu: '1 Maret 2026',
        versi: '-',
        status: 'Belum Diunggah',
        actions: ['upload'],
    },
    {
        title: 'Logbook Bimbingan',
        description: 'Catatan dan dokumentasi setiap sesi bimbingan',
        tipe: 'Administrasi',
        tenggatWaktu: '-',
        versi: 'v1.0',
        status: 'Disetujui',
        actions: ['view', 'download', 'upload'],
    },
    {
        title: 'Lembar Persetujuan Pembimbing',
        description:
            'Lembar persetujuan pembimbing untuk melanjutkan ke tahap sidang',
        tipe: 'Administrasi',
        tenggatWaktu: '20 Maret 2026',
        versi: '-',
        status: 'Belum Diunggah',
        actions: ['upload'],
    },
    {
        title: 'Kartu Bimbingan',
        description: 'Kartu bimbingan yang telah ditandatangani pembimbing',
        tipe: 'Administrasi',
        tenggatWaktu: '20 Maret 2026',
        versi: '-',
        status: 'Belum Diunggah',
        actions: ['upload'],
    },
];

function StatusBadge({ label }: { label: string }) {
    return (
        <Badge className="bg-slate-900 text-white hover:bg-slate-900">
            <span className="inline-flex items-center gap-1">
                <span className="inline-flex size-4 items-center justify-center rounded-full bg-white/10">
                    <CheckCircle2 className="size-3" />
                </span>
                {label}
            </span>
        </Badge>
    );
}

function ProposalStatusBadge({
    status,
}: {
    status: ProposalVersion['status'];
}) {
    if (status === 'Disetujui') {
        return <Badge className="bg-slate-900 text-white">Disetujui</Badge>;
    }

    return (
        <Badge variant="destructive" className="gap-1">
            <span className="inline-flex size-3 items-center justify-center rounded-full bg-white/20" />
            Revisi
        </Badge>
    );
}

function DokumenTypeBadge({ tipe }: { tipe: DokumenType }) {
    const base = 'rounded-full border px-2 py-0.5 text-xs font-medium';
    if (tipe === 'Dokumen Utama') {
        return (
            <span className={cn(base, 'bg-background text-foreground')}>
                {tipe}
            </span>
        );
    }

    return (
        <span className={cn(base, 'bg-background text-foreground')}>
            {tipe}
        </span>
    );
}

function DokumenVersionBadge({ versi }: { versi: string }) {
    if (versi === '-') {
        return <span className="text-sm text-muted-foreground">-</span>;
    }

    return (
        <Badge
            variant="secondary"
            className="rounded-full bg-muted text-foreground"
        >
            {versi}
        </Badge>
    );
}

function DokumenStatusBadge({ status }: { status: DokumenStatus }) {
    if (status === 'Disetujui') {
        return <Badge className="bg-slate-900 text-white">Disetujui</Badge>;
    }

    if (status === 'Ditinjau') {
        return (
            <Badge variant="secondary" className="bg-muted text-foreground">
                Ditinjau
            </Badge>
        );
    }

    return (
        <Badge variant="outline" className="gap-1">
            <CircleAlert className="size-3" />
            Belum Diunggah
        </Badge>
    );
}

function ActionIconButton({
    label,
    icon: Icon,
}: {
    label: string;
    icon: typeof Eye;
}) {
    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            className="h-8 w-8"
            aria-label={label}
        >
            <Icon className="size-4" />
        </Button>
    );
}

export default function TugasAkhirSaya() {
    const [tab, setTab] = useState<TabKey>('judul');

    const tabs = useMemo(
        () => [
            { key: 'judul' as const, label: 'Judul' },
            { key: 'proposal' as const, label: 'Proposal' },
            { key: 'dokumen' as const, label: 'Dokumen' },
        ],
        [],
    );

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
        >
            <Head title="Tugas Akhir Saya" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6">
                <div>
                    <h1 className="text-xl font-semibold">Tugas Akhir Saya</h1>
                    <p className="text-sm text-muted-foreground">
                        Kelola judul, proposal, dan dokumen tugas akhir Anda
                    </p>
                </div>

                <div className="rounded-lg bg-muted p-1">
                    <div className="grid grid-cols-3 gap-1">
                        {tabs.map((t) => {
                            const isActive = tab === t.key;
                            return (
                                <Button
                                    key={t.key}
                                    type="button"
                                    variant="ghost"
                                    className={cn(
                                        'h-10 w-full rounded-md text-sm font-medium',
                                        isActive
                                            ? 'bg-background shadow-sm hover:bg-background'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                    onClick={() => setTab(t.key)}
                                >
                                    {t.label}
                                </Button>
                            );
                        })}
                    </div>
                </div>

                {tab === 'judul' && (
                    <div className="space-y-6">
                        <Card>
                            <CardHeader className="gap-3">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <CardTitle>
                                            Status Pengajuan Judul
                                        </CardTitle>
                                        <CardDescription>
                                            Status persetujuan judul tugas akhir
                                            Anda
                                        </CardDescription>
                                    </div>
                                    <StatusBadge label="Disetujui" />
                                </div>
                            </CardHeader>
                            <CardContent>
                                <Alert>
                                    <CheckCircle2 />
                                    <AlertDescription>
                                        Judul Anda telah disetujui oleh
                                        pembimbing. Anda dapat melanjutkan ke
                                        tahap proposal.
                                    </AlertDescription>
                                </Alert>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="gap-3">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <CardTitle>Informasi Judul</CardTitle>
                                        <CardDescription>
                                            Detail judul tugas akhir dalam
                                            Bahasa Indonesia dan Inggris
                                        </CardDescription>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-9"
                                    >
                                        <Pencil className="size-4" />
                                        Edit
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <div className="text-sm font-medium">
                                        Judul (Bahasa Indonesia)
                                    </div>
                                    <Input
                                        readOnly
                                        defaultValue="Implementasi Machine Learning untuk Prediksi Kualitas Air Berbasis IoT"
                                    />
                                </div>

                                <div className="space-y-2">
                                    <div className="text-sm font-medium">
                                        Judul (Bahasa Inggris)
                                    </div>
                                    <Input
                                        readOnly
                                        defaultValue="Implementation of Machine Learning for Water Quality Prediction Based on IoT"
                                    />
                                </div>

                                <div className="space-y-2">
                                    <div className="text-sm font-medium">
                                        Deskripsi Singkat
                                    </div>
                                    <div className="rounded-md border bg-background px-3 py-2 text-sm text-muted-foreground">
                                        Penelitian ini bertujuan untuk
                                        mengembangkan sistem prediksi kualitas
                                        air menggunakan algoritma machine
                                        learning yang terintegrasi dengan sensor
                                        IoT untuk monitoring real-time.
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="gap-1">
                                <CardTitle>Riwayat Pengajuan</CardTitle>
                                <CardDescription>
                                    Timeline perubahan dan persetujuan judul
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="relative">
                                    <div className="absolute top-0 left-3 h-full w-px bg-border" />
                                    <div className="space-y-6">
                                        {timelineItems.map((item) => {
                                            const Icon = item.icon;
                                            const tone =
                                                item.tone === 'success'
                                                    ? 'bg-green-50 text-green-700 ring-green-200'
                                                    : item.tone === 'warning'
                                                        ? 'bg-yellow-50 text-yellow-700 ring-yellow-200'
                                                        : item.tone === 'danger'
                                                            ? 'bg-red-50 text-red-700 ring-red-200'
                                                            : 'bg-blue-50 text-blue-700 ring-blue-200';

                                            return (
                                                <div
                                                    key={item.title}
                                                    className="relative flex gap-4"
                                                >
                                                    <span
                                                        className={cn(
                                                            'relative z-10 inline-flex size-7 shrink-0 items-center justify-center rounded-full ring-2',
                                                            tone,
                                                        )}
                                                    >
                                                        <Icon className="size-4" />
                                                    </span>
                                                    <div className="min-w-0">
                                                        <div className="text-sm font-semibold">
                                                            {item.title}
                                                        </div>
                                                        <div className="text-sm text-muted-foreground">
                                                            {item.name}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {item.time}
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {tab === 'proposal' && (
                    <div className="space-y-6">
                        <Card>
                            <CardHeader className="gap-3">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <CardTitle>Status Proposal</CardTitle>
                                        <CardDescription>
                                            Status terkini proposal tugas akhir
                                            Anda
                                        </CardDescription>
                                    </div>
                                    <StatusBadge label="Disetujui" />
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <Alert>
                                    <CheckCircle2 />
                                    <AlertDescription>
                                        Proposal Anda telah disetujui pada 15
                                        Januari 2026. Anda dapat melanjutkan ke
                                        tahap penelitian dan bimbingan.
                                    </AlertDescription>
                                </Alert>

                                <div className="flex items-start gap-3 rounded-lg border bg-muted/40 p-4">
                                    <span className="mt-0.5 inline-flex size-9 items-center justify-center rounded-md bg-background text-muted-foreground">
                                        <MessageSquareText className="size-4" />
                                    </span>
                                    <div className="min-w-0">
                                        <div className="text-sm font-medium">
                                            Catatan Pembimbing
                                        </div>
                                        <div className="mt-1 text-sm text-muted-foreground">
                                            Proposal disetujui. Silakan
                                            lanjutkan ke tahap penelitian.
                                        </div>
                                        <div className="mt-2 text-xs text-muted-foreground">
                                            Dr. Budi Santoso, M.Kom. - 15
                                            Januari 2026, 10:30
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="gap-1">
                                <CardTitle>Unggah Proposal</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="text-sm text-muted-foreground">
                                    Unggah dokumen proposal dalam format PDF
                                    (maksimal 5 MB)
                                </div>

                                <div className="space-y-2">
                                    <div className="text-sm font-medium">
                                        Pilih File
                                    </div>
                                    <div className="flex gap-3">
                                        <Input
                                            type="file"
                                            className="flex-1"
                                            aria-label="Pilih file proposal"
                                        />
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            className="h-9"
                                        >
                                            <Upload className="size-4" />
                                            Unggah
                                        </Button>
                                    </div>
                                </div>

                                <div className="rounded-lg border bg-blue-50 p-4 text-sm text-blue-900">
                                    <div className="font-semibold">
                                        Panduan Proposal:
                                    </div>
                                    <ul className="mt-2 list-disc space-y-1 pl-5 text-blue-800">
                                        <li>
                                            Gunakan template proposal yang telah
                                            disediakan
                                        </li>
                                        <li>
                                            Pastikan semua bab sudah lengkap
                                            (Bab 1-5)
                                        </li>
                                        <li>
                                            Sertakan daftar pustaka minimal 15
                                            referensi
                                        </li>
                                        <li>
                                            Format file harus PDF dengan ukuran
                                            maksimal 5 MB
                                        </li>
                                    </ul>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="gap-1">
                                <CardTitle>Riwayat Versi</CardTitle>
                                <CardDescription>
                                    Daftar semua versi proposal yang pernah
                                    diunggah
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-hidden rounded-lg border">
                                    <table className="w-full text-left text-sm">
                                        <thead className="bg-background">
                                            <tr className="border-b">
                                                <th className="px-4 py-3 font-medium">
                                                    Versi
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Nama File
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
                                            {proposalVersions.map((row) => (
                                                <tr
                                                    key={row.version}
                                                    className="border-b last:border-b-0"
                                                >
                                                    <td className="px-4 py-3">
                                                        {row.version}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="flex items-center gap-2">
                                                            <FileText className="size-4 text-muted-foreground" />
                                                            <span className="text-foreground underline-offset-4 hover:underline">
                                                                {row.fileName}
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3 text-muted-foreground">
                                                        {row.uploadedAt}
                                                    </td>
                                                    <td className="px-4 py-3 text-muted-foreground">
                                                        {row.size}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <ProposalStatusBadge
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
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="gap-1">
                                <CardTitle>Detail Catatan Per Versi</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {proposalVersions.map((v) => (
                                    <div
                                        key={v.version}
                                        className="rounded-lg border bg-background p-4"
                                    >
                                        <div className="flex items-start gap-3">
                                            <Badge
                                                className={cn(
                                                    'rounded-full',
                                                    v.status === 'Disetujui'
                                                        ? 'bg-slate-900 text-white'
                                                        : 'bg-destructive text-white',
                                                )}
                                            >
                                                {v.version}
                                            </Badge>
                                            <div className="min-w-0">
                                                <div className="text-sm font-medium">
                                                    {v.fileName}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {v.uploadedAt}
                                                </div>
                                            </div>
                                        </div>

                                        <div className="mt-4 rounded-lg bg-muted/40 p-4 text-sm">
                                            <div className="text-muted-foreground">
                                                Catatan dari Dr. Budi Santoso,
                                                M.Kom.:
                                            </div>
                                            <div className="mt-1 text-foreground">
                                                {v.note}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </div>
                )}

                {tab === 'dokumen' && (
                    <div className="space-y-6">
                        <Card>
                            <CardHeader className="gap-1">
                                <CardTitle>Daftar Dokumen</CardTitle>
                                <CardDescription>
                                    Kelola semua dokumen tugas akhir Anda dalam
                                    satu tempat
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-hidden rounded-lg border">
                                    <table className="w-full text-left text-sm">
                                        <thead className="bg-background">
                                            <tr className="border-b">
                                                <th className="px-4 py-3 font-medium">
                                                    Dokumen
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Tipe
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Tenggat Waktu
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Versi
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
                                            {dokumenRows.map((row) => (
                                                <tr
                                                    key={row.title}
                                                    className="border-b last:border-b-0"
                                                >
                                                    <td className="px-4 py-3">
                                                        <div className="flex items-start gap-3">
                                                            <FileText className="mt-0.5 size-4 text-muted-foreground" />
                                                            <div className="min-w-0">
                                                                <div className="text-sm font-medium">
                                                                    {row.title}
                                                                </div>
                                                                <div className="text-xs text-muted-foreground">
                                                                    {
                                                                        row.description
                                                                    }
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <DokumenTypeBadge
                                                            tipe={row.tipe}
                                                        />
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {row.tenggatWaktu ===
                                                            '-' ? (
                                                            <span className="text-sm text-muted-foreground">
                                                                -
                                                            </span>
                                                        ) : (
                                                            <div className="inline-flex items-center gap-2 text-muted-foreground">
                                                                <Calendar className="size-4" />
                                                                <span>
                                                                    {
                                                                        row.tenggatWaktu
                                                                    }
                                                                </span>
                                                            </div>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <DokumenVersionBadge
                                                            versi={row.versi}
                                                        />
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <DokumenStatusBadge
                                                            status={row.status}
                                                        />
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="flex justify-end gap-1">
                                                            {row.actions.includes(
                                                                'view',
                                                            ) && (
                                                                    <ActionIconButton
                                                                        label="Lihat"
                                                                        icon={Eye}
                                                                    />
                                                                )}
                                                            {row.actions.includes(
                                                                'download',
                                                            ) && (
                                                                    <ActionIconButton
                                                                        label="Unduh"
                                                                        icon={
                                                                            Download
                                                                        }
                                                                    />
                                                                )}
                                                            {row.actions.includes(
                                                                'upload',
                                                            ) && (
                                                                    <ActionIconButton
                                                                        label="Unggah"
                                                                        icon={
                                                                            Upload
                                                                        }
                                                                    />
                                                                )}
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
                )}
            </div>
        </AppLayout>
    );
}
