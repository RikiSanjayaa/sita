import { Head, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, UserRoundPlus } from 'lucide-react';
import { useMemo, useState } from 'react';

import InputError from '@/components/input-error';
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
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';

type AssignmentQueueItem = {
    id: string;
    studentUserId: number;
    nim: string;
    mahasiswa: string;
    program: string;
    statusAkademik: string;
    primaryAdvisor: { id: number; name: string } | null;
    secondaryAdvisor: { id: number; name: string } | null;
    status: 'Pending' | 'Partial' | 'Assigned';
};

type LecturerOption = {
    id: number;
    name: string;
    homebase: string | null;
    load: number;
    limit: number;
    isActive: boolean;
    atCapacity: boolean;
};

type PenugasanProps = {
    queue: AssignmentQueueItem[];
    lecturers: LecturerOption[];
    flashMessage?: string | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Penugasan', href: '/admin/penugasan' },
];

const FINAL_STATUSES = ['lulus', 'drop', 'nonaktif'];

export default function AdminPenugasanPage() {
    const { queue, lecturers, flashMessage } = usePage<
        SharedData & PenugasanProps
    >().props;
    const [selected, setSelected] = useState<AssignmentQueueItem | null>(null);
    const [search, setSearch] = useState('');

    const form = useForm({
        student_user_id: 0,
        primary_lecturer_user_id: '',
        secondary_lecturer_user_id: 'none',
        notes: '',
    });

    const lecturersById = useMemo(
        () => new Map(lecturers.map((lecturer) => [lecturer.id, lecturer])),
        [lecturers],
    );

    const filteredQueue = useMemo(
        () =>
            queue.filter((item) =>
                `${item.nim} ${item.mahasiswa} ${item.program}`
                    .toLowerCase()
                    .includes(search.toLowerCase()),
            ),
        [queue, search],
    );

    const selectedPrimaryLecturer =
        form.data.primary_lecturer_user_id === ''
            ? null
            : lecturersById.get(Number(form.data.primary_lecturer_user_id)) ??
              null;
    const selectedSecondaryLecturer =
        form.data.secondary_lecturer_user_id === 'none'
            ? null
            : lecturersById.get(Number(form.data.secondary_lecturer_user_id)) ??
              null;

    const selectedStudentIsInactive =
        selected === null
            ? false
            : FINAL_STATUSES.includes(selected.statusAkademik.toLowerCase());

    const primaryCapacityConflict =
        selected !== null &&
        selectedPrimaryLecturer !== null &&
        selectedPrimaryLecturer.atCapacity &&
        selectedPrimaryLecturer.id !== selected.primaryAdvisor?.id;

    const secondaryCapacityConflict =
        selected !== null &&
        selectedSecondaryLecturer !== null &&
        selectedSecondaryLecturer.atCapacity &&
        selectedSecondaryLecturer.id !== selected.secondaryAdvisor?.id;

    function openDialog(item: AssignmentQueueItem) {
        setSelected(item);
        form.clearErrors();
        form.setData({
            student_user_id: item.studentUserId,
            primary_lecturer_user_id: item.primaryAdvisor
                ? String(item.primaryAdvisor.id)
                : '',
            secondary_lecturer_user_id: item.secondaryAdvisor
                ? String(item.secondaryAdvisor.id)
                : 'none',
            notes: '',
        });
    }

    function closeDialog() {
        setSelected(null);
        form.reset();
        form.clearErrors();
    }

    function submitAssignment() {
        if (selected === null) {
            return;
        }

        form.transform((data) => ({
            ...data,
            secondary_lecturer_user_id:
                data.secondary_lecturer_user_id === 'none'
                    ? null
                    : data.secondary_lecturer_user_id,
            notes: data.notes.trim() === '' ? null : data.notes.trim(),
        }));

        form.post('/admin/penugasan', {
            preserveScroll: true,
            onSuccess: () => {
                closeDialog();
            },
        });
    }

    const canSubmit =
        selected !== null &&
        form.data.primary_lecturer_user_id !== '' &&
        !selectedStudentIsInactive;

    return (
        <AdminLayout
            breadcrumbs={breadcrumbs}
            title="Penugasan Pembimbing"
            subtitle="Assign atau reassign pembimbing mahasiswa"
        >
            <Head title="Penugasan Admin" />

            <Dialog
                open={selected !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        closeDialog();
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Assign Pembimbing</DialogTitle>
                        <DialogDescription>
                            Menentukan pembimbing 1 dan pembimbing 2 untuk
                            mahasiswa terpilih
                        </DialogDescription>
                    </DialogHeader>

                    {selected && (
                        <div className="grid gap-4">
                            <div className="rounded-lg border bg-muted/20 p-3 text-sm">
                                <p className="font-medium">
                                    {selected.mahasiswa}
                                </p>
                                <p className="text-muted-foreground">
                                    {selected.nim} - {selected.program}
                                </p>
                            </div>

                            {selectedStudentIsInactive && (
                                <Alert variant="destructive">
                                    <AlertTriangle className="size-4" />
                                    <AlertTitle>Tidak bisa diassign</AlertTitle>
                                    <AlertDescription>
                                        Mahasiswa dengan status akademik{' '}
                                        {selected.statusAkademik} tidak dapat
                                        menerima assignment baru.
                                    </AlertDescription>
                                </Alert>
                            )}

                            <div className="grid gap-2">
                                <Label>Pembimbing 1</Label>
                                <Select
                                    value={form.data.primary_lecturer_user_id}
                                    onValueChange={(value) =>
                                        form.setData(
                                            'primary_lecturer_user_id',
                                            value,
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih pembimbing 1" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {lecturers.map((lecturer) => {
                                            const canKeepCurrent =
                                                lecturer.id ===
                                                selected.primaryAdvisor?.id;
                                            const disabled =
                                                !lecturer.isActive ||
                                                (lecturer.atCapacity &&
                                                    !canKeepCurrent);

                                            return (
                                                <SelectItem
                                                    key={lecturer.id}
                                                    value={String(lecturer.id)}
                                                    disabled={disabled}
                                                >
                                                    {lecturer.name} (
                                                    {lecturer.load}/
                                                    {lecturer.limit})
                                                </SelectItem>
                                            );
                                        })}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={
                                        form.errors.primary_lecturer_user_id
                                    }
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label>Pembimbing 2 (Opsional)</Label>
                                <Select
                                    value={form.data.secondary_lecturer_user_id}
                                    onValueChange={(value) =>
                                        form.setData(
                                            'secondary_lecturer_user_id',
                                            value,
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih pembimbing 2" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            Tanpa pembimbing 2
                                        </SelectItem>
                                        {lecturers.map((lecturer) => {
                                            const canKeepCurrent =
                                                lecturer.id ===
                                                selected.secondaryAdvisor?.id;
                                            const disabled =
                                                !lecturer.isActive ||
                                                (lecturer.atCapacity &&
                                                    !canKeepCurrent);

                                            return (
                                                <SelectItem
                                                    key={lecturer.id}
                                                    value={String(lecturer.id)}
                                                    disabled={disabled}
                                                >
                                                    {lecturer.name} (
                                                    {lecturer.load}/
                                                    {lecturer.limit})
                                                </SelectItem>
                                            );
                                        })}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={
                                        form.errors.secondary_lecturer_user_id
                                    }
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label>Catatan (Opsional)</Label>
                                <Textarea
                                    value={form.data.notes}
                                    onChange={(event) =>
                                        form.setData('notes', event.target.value)
                                    }
                                    placeholder="Contoh: pertimbangan topik dan kepakaran dosen."
                                />
                                <InputError message={form.errors.notes} />
                            </div>

                            {(primaryCapacityConflict ||
                                secondaryCapacityConflict) && (
                                <Alert variant="destructive">
                                    <AlertTriangle className="size-4" />
                                    <AlertTitle>Conflict warning</AlertTitle>
                                    <AlertDescription>
                                        Salah satu dosen yang dipilih sudah
                                        mencapai kuota 14/14. Pilih dosen lain
                                        untuk assignment baru.
                                    </AlertDescription>
                                </Alert>
                            )}

                            {Object.keys(form.errors).length > 0 && (
                                <Alert variant="destructive">
                                    <AlertTriangle className="size-4" />
                                    <AlertTitle>Validasi gagal</AlertTitle>
                                    <AlertDescription>
                                        {Object.values(form.errors)[0]}
                                    </AlertDescription>
                                </Alert>
                            )}
                        </div>
                    )}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={closeDialog}
                            disabled={form.processing}
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            className="bg-primary text-primary-foreground hover:bg-primary/90"
                            disabled={!canSubmit || form.processing}
                            onClick={submitAssignment}
                        >
                            Simpan Assignment
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <div className="mx-auto grid w-full max-w-7xl flex-1 gap-6 px-4 py-6 lg:grid-cols-[1fr_320px] md:px-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Queue Penugasan Mahasiswa</CardTitle>
                        <CardDescription>
                            Data assignment terhubung langsung ke database
                        </CardDescription>
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Cari NIM / nama mahasiswa..."
                        />
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

                        {filteredQueue.map((item) => (
                            <div
                                key={item.id}
                                className="rounded-lg border bg-background p-4"
                            >
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="min-w-0">
                                        <p className="text-sm font-semibold">
                                            {item.mahasiswa}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {item.nim} - {item.program}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            P1:{' '}
                                            {item.primaryAdvisor?.name ??
                                                'Belum ditetapkan'}{' '}
                                            - P2:{' '}
                                            {item.secondaryAdvisor?.name ??
                                                'Belum ditetapkan'}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Badge
                                            variant={
                                                item.status === 'Assigned'
                                                    ? 'default'
                                                    : item.status === 'Pending'
                                                      ? 'destructive'
                                                      : 'secondary'
                                            }
                                            className={
                                                item.status === 'Assigned'
                                                    ? 'bg-emerald-600 text-white dark:bg-emerald-500'
                                                    : ''
                                            }
                                        >
                                            {item.status}
                                        </Badge>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => openDialog(item)}
                                        >
                                            <UserRoundPlus className="size-4" />
                                            {item.status === 'Assigned'
                                                ? 'Reassign'
                                                : 'Assign'}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Kapasitas Dosen</CardTitle>
                        <CardDescription>
                            Indikator kuota aktif per dosen (maks 14)
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {lecturers.map((advisor) => (
                            <div
                                key={advisor.id}
                                className="rounded-lg border bg-background p-3"
                            >
                                <p className="text-sm font-medium">
                                    {advisor.name}
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Beban aktif: {advisor.load}/{advisor.limit}
                                </p>
                                <div className="mt-2 h-2 rounded-full bg-muted">
                                    <div
                                        className={
                                            advisor.load >= advisor.limit
                                                ? 'h-2 rounded-full bg-destructive'
                                                : advisor.load >= advisor.limit - 2
                                                  ? 'h-2 rounded-full bg-amber-500'
                                                  : 'h-2 rounded-full bg-emerald-600'
                                        }
                                        style={{
                                            width: `${Math.min(
                                                100,
                                                (advisor.load /
                                                    advisor.limit) *
                                                    100,
                                            )}%`,
                                        }}
                                    />
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
