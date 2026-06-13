import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    ChevronRight,
    Gauge,
    GraduationCap,
    Pencil,
    Search,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { EmptyState } from '@/components/empty-state';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTablePagination, usePagination } from '@/components/ui/data-table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useInitials } from '@/hooks/use-initials';
import { useUrlState } from '@/hooks/use-url-state';
import KaprodiLayout from '@/layouts/kaprodi-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';

type ProgramStudi = {
    name: string;
};

type LecturerRow = {
    id: number;
    name: string;
    avatar: string | null;
    nik: string;
    concentration: string | null;
    concentrations: string[];
    status: string;
    quota: number;
    activeSupervisionCount: number;
    primaryCount: number;
    secondaryCount: number;
    semproCount: number;
    sidangCount: number;
    upcomingExamCount: number;
    activeStudents: string[];
    profileUrl: string;
};

type DosenProdiProps = {
    programStudi: ProgramStudi;
    lecturers: LecturerRow[];
};

const PAGE_SIZE = 15;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/kaprodi/dashboard' },
    { title: 'Dosen Prodi', href: '/kaprodi/dosen-prodi' },
];

export default function KaprodiDosenProdiPage() {
    const { auth, programStudi, lecturers } = usePage<
        SharedData & DosenProdiProps
    >().props;
    const canManageQuota =
        auth.kaprodiCapabilities?.manage_lecturer_quota ?? true;
    const getInitials = useInitials();
    const [search, setSearch] = useUrlState('search', '');
    const [concentrationFilter, setConcentrationFilter] = useUrlState(
        'concentration',
        'semua',
    );
    const pageState = useUrlState('page', 1);
    const [quotaDialog, setQuotaDialog] = useState<LecturerRow | null>(null);

    const concentrations = useMemo(
        () =>
            Array.from(
                new Set(
                    lecturers
                        .map((lecturer) => lecturer.concentration)
                        .concat(
                            lecturers.flatMap(
                                (lecturer) => lecturer.concentrations ?? [],
                            ),
                        )
                        .filter((item): item is string => Boolean(item)),
                ),
            ).sort(),
        [lecturers],
    );

    const filtered = useMemo(() => {
        const query = search.trim().toLowerCase();

        return lecturers.filter((lecturer) => {
            const matchesSearch =
                !query ||
                [
                    lecturer.name,
                    lecturer.nik,
                    lecturer.concentration ?? '',
                    ...(lecturer.concentrations ?? []),
                    lecturer.status,
                    ...lecturer.activeStudents,
                ]
                    .join(' ')
                    .toLowerCase()
                    .includes(query);

            const matchesFilter =
                concentrationFilter === 'semua' ||
                (lecturer.concentrations ?? []).includes(concentrationFilter) ||
                lecturer.concentration === concentrationFilter;

            return matchesSearch && matchesFilter;
        });
    }, [concentrationFilter, lecturers, search]);

    const { page, setPage, totalPages, paginated, totalItems } = usePagination(
        filtered,
        PAGE_SIZE,
        [search, concentrationFilter],
        pageState,
    );

    return (
        <KaprodiLayout
            breadcrumbs={breadcrumbs}
            title="Dosen Prodi"
            subtitle={`Beban pembimbing dan penguji di ${programStudi.name}`}
        >
            <Head title="Dosen Prodi" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-10 px-4 py-6 md:px-6 lg:py-8">
                <section>
                    <div className="mb-4 border-b pb-3">
                        <h2 className="text-base font-semibold">Dosen Prodi</h2>
                        <p className="text-sm text-muted-foreground">
                            {lecturers.length} dosen tercatat pada{' '}
                            {programStudi.name}
                        </p>
                    </div>

                    <div className="space-y-3">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div className="relative max-w-xs flex-1">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Cari nama, NIK, konsentrasi..."
                                    className="h-8 pl-8 text-sm"
                                />
                            </div>
                            <div className="flex flex-wrap gap-1">
                                {[
                                    { label: 'Semua', value: 'semua' },
                                    ...concentrations.map((item) => ({
                                        label: item,
                                        value: item,
                                    })),
                                ].map((tab) => (
                                    <button
                                        key={tab.value}
                                        type="button"
                                        onClick={() =>
                                            setConcentrationFilter(tab.value)
                                        }
                                        className={cn(
                                            'rounded-full px-3 py-1 text-xs font-medium transition-colors',
                                            concentrationFilter === tab.value
                                                ? 'bg-primary text-primary-foreground shadow-sm'
                                                : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground',
                                        )}
                                    >
                                        {tab.label}
                                    </button>
                                ))}
                            </div>
                        </div>

                        {filtered.length > 0 ? (
                            <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b bg-muted/30">
                                            <th className="px-4 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                                Dosen
                                            </th>
                                            <th className="hidden px-4 py-2.5 text-left text-xs font-medium text-muted-foreground md:table-cell">
                                                Konsentrasi
                                            </th>
                                            <th className="px-4 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                                Bimbingan
                                            </th>
                                            <th className="hidden px-4 py-2.5 text-left text-xs font-medium text-muted-foreground lg:table-cell">
                                                Ujian
                                            </th>
                                            <th className="hidden px-4 py-2.5 text-left text-xs font-medium text-muted-foreground xl:table-cell">
                                                Mahasiswa Aktif
                                            </th>
                                            <th className="w-8 px-4 py-2.5" />
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {paginated.map((lecturer) => (
                                            <tr
                                                key={lecturer.id}
                                                className="transition-colors hover:bg-muted/20"
                                            >
                                                <td className="px-4 py-3">
                                                    <Link
                                                        href={
                                                            lecturer.profileUrl
                                                        }
                                                        className="flex items-center gap-2.5"
                                                    >
                                                        <Avatar className="size-7 shrink-0 border">
                                                            <AvatarImage
                                                                src={
                                                                    lecturer.avatar ??
                                                                    undefined
                                                                }
                                                                alt={
                                                                    lecturer.name
                                                                }
                                                            />
                                                            <AvatarFallback className="bg-primary/10 text-[10px] text-primary">
                                                                {getInitials(
                                                                    lecturer.name,
                                                                )}
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <div className="min-w-0">
                                                            <p className="truncate leading-snug font-medium">
                                                                {lecturer.name}
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {lecturer.nik} -{' '}
                                                                {
                                                                    lecturer.status
                                                                }
                                                            </p>
                                                        </div>
                                                    </Link>
                                                </td>
                                                <td className="hidden px-4 py-3 md:table-cell">
                                                    <div className="flex flex-wrap gap-1.5">
                                                        {(lecturer
                                                            .concentrations
                                                            ?.length ?? 0) >
                                                        0 ? (
                                                            lecturer.concentrations.map(
                                                                (item) => (
                                                                    <Badge
                                                                        key={
                                                                            item
                                                                        }
                                                                        variant="outline"
                                                                        className="rounded-full"
                                                                    >
                                                                        {item}
                                                                    </Badge>
                                                                ),
                                                            )
                                                        ) : (
                                                            <Badge
                                                                variant="outline"
                                                                className="rounded-full"
                                                            >
                                                                -
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex flex-wrap gap-1.5">
                                                        <Badge variant="outline">
                                                            P1{' '}
                                                            {
                                                                lecturer.primaryCount
                                                            }
                                                        </Badge>
                                                        <Badge variant="outline">
                                                            P2{' '}
                                                            {
                                                                lecturer.secondaryCount
                                                            }
                                                        </Badge>
                                                        <Badge variant="secondary">
                                                            Kuota{' '}
                                                            {lecturer.quota}
                                                        </Badge>
                                                        {canManageQuota ? (
                                                            <button
                                                                type="button"
                                                                title="Atur kuota bimbingan"
                                                                onClick={() =>
                                                                    setQuotaDialog(
                                                                        lecturer,
                                                                    )
                                                                }
                                                                className="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium transition-colors hover:bg-muted"
                                                            >
                                                                <Pencil className="size-3" />
                                                            </button>
                                                        ) : null}
                                                    </div>
                                                </td>
                                                <td className="hidden px-4 py-3 lg:table-cell">
                                                    <div className="flex flex-wrap gap-1.5">
                                                        <Badge variant="outline">
                                                            Sempro{' '}
                                                            {
                                                                lecturer.semproCount
                                                            }
                                                        </Badge>
                                                        <Badge variant="outline">
                                                            Sidang{' '}
                                                            {
                                                                lecturer.sidangCount
                                                            }
                                                        </Badge>
                                                        {lecturer.upcomingExamCount >
                                                        0 ? (
                                                            <Badge>
                                                                <CalendarClock className="size-3" />
                                                                {
                                                                    lecturer.upcomingExamCount
                                                                }
                                                            </Badge>
                                                        ) : null}
                                                    </div>
                                                </td>
                                                <td className="hidden px-4 py-3 xl:table-cell">
                                                    <p className="max-w-xs truncate text-xs text-muted-foreground">
                                                        {lecturer.activeStudents
                                                            .length > 0
                                                            ? lecturer.activeStudents.join(
                                                                  ', ',
                                                              )
                                                            : 'Belum ada mahasiswa aktif'}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    <Link
                                                        href={
                                                            lecturer.profileUrl
                                                        }
                                                    >
                                                        <ChevronRight className="size-4" />
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                <DataTablePagination
                                    currentPage={page}
                                    totalPages={totalPages}
                                    totalItems={totalItems}
                                    pageSize={PAGE_SIZE}
                                    onPageChange={setPage}
                                    itemLabel="dosen"
                                />
                            </div>
                        ) : (
                            <EmptyState
                                icon={GraduationCap}
                                title="Tidak ada dosen ditemukan"
                                description="Coba ubah pencarian atau filter yang sedang dipakai."
                            />
                        )}

                        {filtered.length > 0 ? (
                            <p className="text-right text-xs text-muted-foreground">
                                {filtered.length} dari {lecturers.length} dosen
                            </p>
                        ) : null}
                    </div>
                </section>
            </div>
            <QuotaDialog
                lecturer={quotaDialog}
                open={quotaDialog !== null}
                onOpenChange={(open) => {
                    if (!open) setQuotaDialog(null);
                }}
            />
        </KaprodiLayout>
    );
}

function QuotaDialog({
    lecturer,
    open,
    onOpenChange,
}: {
    lecturer: LecturerRow | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({
        supervision_quota: lecturer ? String(lecturer.quota) : '',
    });

    useEffect(() => {
        form.setData({
            supervision_quota: lecturer ? String(lecturer.quota) : '',
        });
        form.clearErrors();
    }, [lecturer?.id]);

    if (!lecturer) return null;

    const activeLecturer = lecturer;
    const quota = Number(form.data.supervision_quota);
    const minimum = activeLecturer.activeSupervisionCount;
    const invalid =
        form.processing ||
        form.data.supervision_quota === '' ||
        !Number.isFinite(quota) ||
        quota < Math.max(1, minimum);

    function submit() {
        form.patch(`/kaprodi/dosen-prodi/${activeLecturer.id}/quota`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Atur Kuota Bimbingan</DialogTitle>
                    <DialogDescription>
                        Perbarui batas mahasiswa bimbingan aktif untuk{' '}
                        {activeLecturer.name}.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="flex items-center gap-3 rounded-lg border bg-muted/20 p-3">
                        <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Gauge className="size-5" />
                        </div>
                        <div>
                            <p className="text-sm font-medium">
                                {activeLecturer.activeSupervisionCount}{' '}
                                mahasiswa bimbingan aktif
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Kuota baru tidak boleh lebih kecil dari jumlah
                                ini.
                            </p>
                        </div>
                    </div>

                    <div className="grid gap-1.5">
                        <label className="text-sm font-medium">
                            Kuota Bimbingan
                        </label>
                        <Input
                            type="number"
                            min={Math.max(1, minimum)}
                            max={100}
                            value={form.data.supervision_quota}
                            onChange={(event) =>
                                form.setData(
                                    'supervision_quota',
                                    event.target.value,
                                )
                            }
                        />
                        {form.errors.supervision_quota ? (
                            <p className="text-xs text-destructive">
                                {form.errors.supervision_quota}
                            </p>
                        ) : null}
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Batal
                    </Button>
                    <Button type="button" disabled={invalid} onClick={submit}>
                        Simpan Kuota
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
