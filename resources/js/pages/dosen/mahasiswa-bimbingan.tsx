import { Head, Link, usePage } from '@inertiajs/react';
import { MessageCircle, MessageSquareText, Search, UserRound } from 'lucide-react';
import { useMemo, useState } from 'react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useInitials } from '@/hooks/use-initials';
import DosenLayout from '@/layouts/dosen-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dosen/dashboard' },
    { title: 'Mahasiswa Bimbingan', href: '/dosen/mahasiswa-bimbingan' },
];

type MahasiswaRow = {
    nim: string;
    name: string;
    avatar: string | null;
    advisorType: string;
    otherAdvisors: string[];
    stageLabel: string;
    stageDescription: string;
    status: string;
    lastUpdate: string;
    chatUrl: string | null;
    whatsappUrl: string | null;
};

type MahasiswaBimbinganProps = {
    mahasiswaRows: MahasiswaRow[];
    historyRows: MahasiswaRow[];
    activeCount: number;
    capacityLimit: number;
};

type AdvisorFilter = 'semua' | 'Pembimbing 1' | 'Pembimbing 2';

function StudentTable({
    rows,
    emptyText,
    showActions = true,
}: {
    rows: MahasiswaRow[];
    emptyText: string;
    showActions?: boolean;
}) {
    const getInitials = useInitials();
    const [search, setSearch] = useState('');
    const [advisorFilter, setAdvisorFilter] = useState<AdvisorFilter>('semua');

    const advisorTabs: { label: string; value: AdvisorFilter }[] = [
        { label: 'Semua', value: 'semua' },
        { label: 'Pembimbing 1', value: 'Pembimbing 1' },
        { label: 'Pembimbing 2', value: 'Pembimbing 2' },
    ];

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        return rows.filter((row) => {
            const matchSearch =
                !q ||
                row.name.toLowerCase().includes(q) ||
                row.nim.toLowerCase().includes(q) ||
                row.stageLabel.toLowerCase().includes(q);
            const matchAdvisor =
                advisorFilter === 'semua' || row.advisorType === advisorFilter;
            return matchSearch && matchAdvisor;
        });
    }, [rows, search, advisorFilter]);

    return (
        <div className="space-y-3">
            {/* Toolbar */}
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div className="relative max-w-xs flex-1">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Cari nama atau NIM..."
                        className="h-8 pl-8 text-sm"
                    />
                </div>
                <div className="flex gap-1">
                    {advisorTabs.map((tab) => (
                        <button
                            key={tab.value}
                            type="button"
                            onClick={() => setAdvisorFilter(tab.value)}
                            className={cn(
                                'rounded-full px-3 py-1 text-xs font-medium transition-colors',
                                advisorFilter === tab.value
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground',
                            )}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>
            </div>

            {/* Table */}
            {filtered.length > 0 ? (
                <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/30">
                                <th className="px-4 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                    Mahasiswa
                                </th>
                                <th className="hidden px-4 py-2.5 text-left text-xs font-medium text-muted-foreground md:table-cell">
                                    Peran
                                </th>
                                <th className="hidden px-4 py-2.5 text-left text-xs font-medium text-muted-foreground lg:table-cell">
                                    Tahap
                                </th>
                                <th className="hidden px-4 py-2.5 text-left text-xs font-medium text-muted-foreground xl:table-cell">
                                    Aktivitas Terbaru
                                </th>
                                {showActions && (
                                    <th className="px-4 py-2.5 text-right text-xs font-medium text-muted-foreground">
                                        Aksi
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {filtered.map((row) => (
                                <tr
                                    key={`${row.nim}-${row.advisorType}`}
                                    className="transition-colors hover:bg-muted/20"
                                >
                                    {/* Mahasiswa */}
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2.5">
                                            <Avatar className="size-7 shrink-0 border">
                                                <AvatarImage
                                                    src={row.avatar ?? undefined}
                                                    alt={row.name}
                                                />
                                                <AvatarFallback className="bg-primary/10 text-[10px] text-primary">
                                                    {getInitials(row.name)}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div className="min-w-0">
                                                <p className="truncate font-medium leading-snug">
                                                    {row.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {row.nim}
                                                </p>
                                                {/* Mobile: show extras inline */}
                                                <div className="mt-1 flex flex-wrap gap-1 md:hidden">
                                                    <Badge variant="outline" className="rounded-full text-xs">
                                                        {row.advisorType}
                                                    </Badge>
                                                    <Badge variant="outline" className="rounded-full text-xs">
                                                        {row.stageLabel}
                                                    </Badge>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    {/* Peran */}
                                    <td className="hidden px-4 py-3 md:table-cell">
                                        <Badge variant="outline" className="rounded-full text-xs">
                                            {row.advisorType}
                                        </Badge>
                                    </td>

                                    {/* Tahap */}
                                    <td className="hidden px-4 py-3 lg:table-cell">
                                        <p className="text-xs font-medium">{row.stageLabel}</p>
                                        <p className="mt-0.5 max-w-[200px] truncate text-xs text-muted-foreground">
                                            {row.stageDescription}
                                        </p>
                                    </td>

                                    {/* Aktivitas */}
                                    <td className="hidden px-4 py-3 text-xs text-muted-foreground xl:table-cell">
                                        {row.lastUpdate}
                                    </td>

                                    {/* Aksi */}
                                    {showActions && (
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1.5">
                                                {row.chatUrl ? (
                                                    <Button asChild size="sm" className="h-7 px-2.5 text-xs">
                                                        <Link href={row.chatUrl}>
                                                            <MessageSquareText className="size-3.5" />
                                                            Chat
                                                        </Link>
                                                    </Button>
                                                ) : (
                                                    <Button size="sm" disabled className="h-7 px-2.5 text-xs">
                                                        <MessageSquareText className="size-3.5" />
                                                        Chat
                                                    </Button>
                                                )}
                                                {row.whatsappUrl && (
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant="outline"
                                                        className="h-7 px-2.5 text-xs"
                                                    >
                                                        <a
                                                            href={row.whatsappUrl}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                        >
                                                            <MessageCircle className="size-3.5" />
                                                            WA
                                                        </a>
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-10 text-center">
                    <UserRound className="mb-2 size-8 text-muted-foreground/40" />
                    <p className="text-sm text-muted-foreground">{emptyText}</p>
                </div>
            )}

            {filtered.length > 0 && (
                <p className="text-right text-xs text-muted-foreground">
                    {filtered.length} dari {rows.length} mahasiswa
                </p>
            )}
        </div>
    );
}

export default function DosenMahasiswaBimbinganPage() {
    const { mahasiswaRows, historyRows, activeCount, capacityLimit } =
        usePage<SharedData & MahasiswaBimbinganProps>().props;

    return (
        <DosenLayout
            breadcrumbs={breadcrumbs}
            title="Mahasiswa Bimbingan"
            subtitle="Daftar mahasiswa aktif dan riwayat bimbingan"
        >
            <Head title="Mahasiswa Bimbingan" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-10 px-4 py-6 md:px-6 lg:py-8">

                {/* ── Mahasiswa Aktif ── */}
                <section>
                    <div className="mb-4 flex items-center justify-between border-b pb-3">
                        <div>
                            <h2 className="text-base font-semibold">Mahasiswa Aktif</h2>
                            <p className="text-sm text-muted-foreground">
                                Menangani{' '}
                                <span className="font-semibold text-foreground">{activeCount}</span>
                                {' '}dari{' '}
                                <span className="font-semibold text-foreground">{capacityLimit}</span>
                                {' '}kuota
                            </p>
                        </div>
                    </div>
                    <StudentTable
                        rows={mahasiswaRows}
                        emptyText="Belum ada mahasiswa aktif"
                        showActions
                    />
                </section>

                {/* ── Riwayat Bimbingan ── */}
                <section>
                    <div className="mb-4 border-b pb-3">
                        <h2 className="text-base font-semibold">Riwayat Bimbingan</h2>
                        <p className="text-sm text-muted-foreground">
                            Mahasiswa yang sudah tidak aktif atau sudah lulus
                        </p>
                    </div>
                    <StudentTable
                        rows={historyRows}
                        emptyText="Belum ada riwayat mahasiswa bimbingan"
                        showActions={false}
                    />
                </section>
            </div>
        </DosenLayout>
    );
}
