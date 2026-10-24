import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    Gauge,
    GraduationCap,
    MessageSquareText,
    Pencil,
    UserRound,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { RowIconButton } from '@/components/kaprodi/row-icon-button';
import {
    DataSection,
    DataToolbar,
    dataTh,
    stackedTable,
} from '@/components/patterns/data-section';
import { FilterPills } from '@/components/patterns/filter-pills';
import { StatusBadge } from '@/components/patterns/status-badge';
import { StickyFilterBar } from '@/components/patterns/sticky-filter-bar';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DataTableContainer,
    DataTableEmptyState,
    DataTablePagination,
    usePagination,
} from '@/components/ui/data-table';
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
import { neutralAcademicTerminology } from '@/lib/academic-terminology';
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
    expertiseFields: string[];
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

function inConcentration(lecturer: LecturerRow, concentration: string) {
    return (
        (lecturer.concentrations ?? []).includes(concentration) ||
        lecturer.concentration === concentration
    );
}

export default function KaprodiDosenProdiPage() {
    const { auth, programStudi, lecturers } = usePage<
        SharedData & DosenProdiProps
    >().props;
    const canManageQuota =
        auth.kaprodiCapabilities?.manage_lecturer_quota ?? true;
    const terms = neutralAcademicTerminology;
    const getInitials = useInitials();
    const [search, setSearch] = useUrlState('search', '');
    const [concentrationFilter, setConcentrationFilter] = useUrlState(
        'concentration',
        'semua',
    );
    const pageState = useUrlState('page', 1);
    const [quotaDialog, setQuotaDialog] = useState<LecturerRow | null>(null);

    const openPrivateChat = (lecturer: LecturerRow): void => {
        router.post(
            '/dosen/pesan/private',
            { recipient_id: lecturer.id },
            { preserveScroll: true },
        );
    };

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
                    ...(lecturer.expertiseFields ?? []),
                    lecturer.status,
                    ...lecturer.activeStudents,
                ]
                    .join(' ')
                    .toLowerCase()
                    .includes(query);

            const matchesFilter =
                concentrationFilter === 'semua' ||
                inConcentration(lecturer, concentrationFilter);

            return matchesSearch && matchesFilter;
        });
    }, [concentrationFilter, lecturers, search]);

    const {
        page,
        setPage,
        pageSize,
        setPageSize,
        totalPages,
        paginated,
        totalItems,
    } = usePagination(
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

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-6 lg:py-8">
                <DataSection
                    level={1}
                    title="Daftar Dosen"
                    description={`${lecturers.length} dosen tercatat dengan beban pembimbing dan penguji di ${programStudi.name}.`}
                >
                    <StickyFilterBar>
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            placeholder="Cari nama, NIK, konsentrasi..."
                        />

                        {concentrations.length > 0 ? (
                            <FilterPills
                                label="Filter konsentrasi"
                                value={concentrationFilter}
                                onValueChange={setConcentrationFilter}
                                itemClassName="max-md:h-11 max-md:px-4"
                                options={[
                                    {
                                        label: 'Semua',
                                        value: 'semua',
                                        count: lecturers.length,
                                    },
                                    ...concentrations.map((item) => ({
                                        label: item,
                                        value: item,
                                        count: lecturers.filter((lecturer) =>
                                            inConcentration(lecturer, item),
                                        ).length,
                                    })),
                                ]}
                            />
                        ) : null}
                    </StickyFilterBar>

                    {filtered.length > 0 ? (
                        <DataTableContainer
                            pagination={
                                <DataTablePagination
                                    currentPage={page}
                                    totalPages={totalPages}
                                    totalItems={totalItems}
                                    pageSize={pageSize}
                                    onPageChange={setPage}
                                    onPageSizeChange={setPageSize}
                                    itemLabel="dosen"
                                />
                            }
                        >
                            <table
                                className={cn(
                                    stackedTable.table,
                                    'md:min-w-[860px]',
                                )}
                            >
                                <thead className={stackedTable.thead}>
                                    <tr className="border-b bg-muted/40">
                                        <th className={dataTh}>Dosen</th>
                                        <th className={dataTh}>Bimbingan</th>
                                        <th className={dataTh}>Ujian</th>
                                        <th className={dataTh}>
                                            Mahasiswa aktif
                                        </th>
                                        <th className={dataTh}>
                                            <span className="sr-only">
                                                Aksi
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    className={cn(
                                        stackedTable.tbody,
                                        'md:divide-y',
                                    )}
                                >
                                    {paginated.map((lecturer) => (
                                        <tr
                                            key={lecturer.id}
                                            className={cn(
                                                stackedTable.tr,
                                                'align-top transition-colors hover:bg-muted/30',
                                            )}
                                        >
                                            <td
                                                className={stackedTable.tdPlain}
                                            >
                                                <Link
                                                    href={lecturer.profileUrl}
                                                    className="group flex items-start gap-3"
                                                >
                                                    <Avatar className="size-8 shrink-0 border">
                                                        <AvatarImage
                                                            src={
                                                                lecturer.avatar ??
                                                                undefined
                                                            }
                                                            alt=""
                                                        />
                                                        <AvatarFallback className="bg-primary-muted text-xs text-primary-muted-foreground">
                                                            {getInitials(
                                                                lecturer.name,
                                                            )}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div className="min-w-0">
                                                        <p className="font-medium group-hover:text-primary">
                                                            {lecturer.name}
                                                        </p>
                                                        <p className="text-muted-foreground tabular-nums">
                                                            {lecturer.nik} ·{' '}
                                                            {lecturer.status}
                                                        </p>
                                                        {(lecturer
                                                            .concentrations
                                                            ?.length ?? 0) >
                                                        0 ? (
                                                            <p
                                                                title={lecturer.concentrations.join(
                                                                    ', ',
                                                                )}
                                                                className="mt-1 text-xs text-muted-foreground max-md:truncate"
                                                            >
                                                                {lecturer.concentrations.join(
                                                                    ', ',
                                                                )}
                                                            </p>
                                                        ) : null}
                                                        {lecturer
                                                            .expertiseFields
                                                            .length > 0 ? (
                                                            <p
                                                                title={lecturer.expertiseFields.join(
                                                                    ', ',
                                                                )}
                                                                className="text-xs text-muted-foreground max-md:truncate"
                                                            >
                                                                {lecturer.expertiseFields.join(
                                                                    ', ',
                                                                )}
                                                            </p>
                                                        ) : null}
                                                    </div>
                                                </Link>
                                            </td>
                                            <td
                                                className={cn(
                                                    stackedTable.td,
                                                    'max-md:flex max-md:items-center max-md:gap-x-2',
                                                )}
                                            >
                                                <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                                                    <span className="inline-flex items-center gap-1.5">
                                                        <Badge
                                                            variant="outline"
                                                            className="rounded-full border-transparent bg-primary-muted text-primary-muted-foreground"
                                                        >
                                                            P1
                                                        </Badge>
                                                        <span className="tabular-nums">
                                                            {
                                                                lecturer.primaryCount
                                                            }
                                                        </span>
                                                    </span>
                                                    <span className="inline-flex items-center gap-1.5">
                                                        <Badge
                                                            variant="outline"
                                                            className="rounded-full border-transparent bg-success-muted text-success-muted-foreground"
                                                        >
                                                            P2
                                                        </Badge>
                                                        <span className="tabular-nums">
                                                            {
                                                                lecturer.secondaryCount
                                                            }
                                                        </span>
                                                    </span>
                                                </div>
                                                <p className="font-medium tabular-nums">
                                                    Kuota {lecturer.quota}
                                                </p>
                                            </td>
                                            <td
                                                className={cn(
                                                    stackedTable.td,
                                                    'max-md:flex max-md:items-center max-md:gap-x-2',
                                                )}
                                            >
                                                <p className="tabular-nums">
                                                    {terms.proposalExamShort}{' '}
                                                    {lecturer.semproCount} ·{' '}
                                                    {terms.finalExam}{' '}
                                                    {lecturer.sidangCount}
                                                </p>
                                                {lecturer.upcomingExamCount >
                                                0 ? (
                                                    <StatusBadge
                                                        domain="schedule"
                                                        status="scheduled"
                                                        className="mt-1 max-md:mt-0"
                                                    >
                                                        {
                                                            lecturer.upcomingExamCount
                                                        }{' '}
                                                        jadwal mendatang
                                                    </StatusBadge>
                                                ) : null}
                                            </td>
                                            <td
                                                className={cn(
                                                    stackedTable.tdDetail,
                                                    'md:max-w-xs',
                                                    lecturer.activeStudents
                                                        .length === 0 &&
                                                        'max-md:hidden',
                                                )}
                                            >
                                                {lecturer.activeStudents
                                                    .length > 0 ? (
                                                    <p
                                                        title={lecturer.activeStudents.join(
                                                            ', ',
                                                        )}
                                                        className="line-clamp-2 text-xs text-pretty text-muted-foreground"
                                                    >
                                                        <span className="md:hidden">
                                                            Mahasiswa
                                                            aktif:{' '}
                                                        </span>
                                                        {lecturer.activeStudents.join(
                                                            ', ',
                                                        )}
                                                    </p>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        Belum ada mahasiswa
                                                        aktif
                                                    </span>
                                                )}
                                            </td>
                                            <td
                                                className={
                                                    stackedTable.tdActions
                                                }
                                            >
                                                <div className="flex items-center justify-end gap-1">
                                                    {canManageQuota ? (
                                                        <RowIconButton
                                                            label="Atur kuota bimbingan"
                                                            onClick={() =>
                                                                setQuotaDialog(
                                                                    lecturer,
                                                                )
                                                            }
                                                        >
                                                            <Pencil className="size-4" />
                                                        </RowIconButton>
                                                    ) : null}
                                                    {lecturer.id !==
                                                    auth.user.id ? (
                                                        <RowIconButton
                                                            label="Chat dengan dosen"
                                                            onClick={() =>
                                                                openPrivateChat(
                                                                    lecturer,
                                                                )
                                                            }
                                                        >
                                                            <MessageSquareText className="size-4" />
                                                        </RowIconButton>
                                                    ) : null}
                                                    <RowIconButton
                                                        asChild
                                                        label="Buka profil dosen"
                                                        className="max-md:hidden"
                                                    >
                                                        <Link
                                                            href={
                                                                lecturer.profileUrl
                                                            }
                                                        >
                                                            <UserRound className="size-4" />
                                                        </Link>
                                                    </RowIconButton>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </DataTableContainer>
                    ) : (
                        <DataTableEmptyState
                            icon={GraduationCap}
                            title="Tidak ada dosen ditemukan"
                            description="Coba ubah pencarian atau filter yang sedang dipakai."
                        />
                    )}
                </DataSection>
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
    const { clearErrors, setData } = form;

    useEffect(() => {
        setData({
            supervision_quota: lecturer ? String(lecturer.quota) : '',
        });
        clearErrors();
    }, [clearErrors, lecturer, setData]);

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
                        <div className="flex size-10 items-center justify-center rounded-lg bg-primary-muted text-primary-muted-foreground">
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
                        <label
                            htmlFor="supervision-quota"
                            className="text-sm font-medium"
                        >
                            Kuota Bimbingan
                        </label>
                        <Input
                            id="supervision-quota"
                            className="max-md:h-11"
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
