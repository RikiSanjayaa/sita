import { useForm } from '@inertiajs/react';
import { CalendarPlus, Repeat } from 'lucide-react';
import { type FormEvent } from 'react';

import { Button } from '@/components/ui/button';
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
import { Textarea } from '@/components/ui/textarea';
import { store } from '@/routes/dosen/jadwal-bimbingan';

export type SupervisedStudent = {
    id: number;
    name: string;
};

type CreateScheduleForm = {
    student_user_id: string;
    topic: string;
    requested_for: string;
    location: string;
    lecturer_note: string;
    is_recurring: boolean;
    recurring_pattern: string;
    recurring_count: number;
};

const initialFormData: CreateScheduleForm = {
    student_user_id: '',
    topic: '',
    requested_for: '',
    location: '',
    lecturer_note: '',
    is_recurring: false,
    recurring_pattern: 'weekly',
    recurring_count: 4,
};

type DosenScheduleCreateDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    students: SupervisedStudent[];
};

export function DosenScheduleCreateDialog({
    open,
    onOpenChange,
    students,
}: DosenScheduleCreateDialogProps) {
    const form = useForm<CreateScheduleForm>(initialFormData);

    const handleOpenChange = (nextOpen: boolean) => {
        if (!nextOpen) {
            form.reset();
            form.clearErrors();
        }
        onOpenChange(nextOpen);
    };

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        form.post(store().url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Buat Jadwal Bimbingan</DialogTitle>
                    <DialogDescription>
                        Jadwal yang dibuat dosen langsung disetujui dan muncul
                        di kalender mahasiswa.
                    </DialogDescription>
                </DialogHeader>

                <form className="grid gap-5" onSubmit={submit}>
                    <div className="grid gap-2">
                        <Label htmlFor="create_student_user_id">
                            Mahasiswa
                        </Label>
                        <Select
                            value={form.data.student_user_id}
                            onValueChange={(value) =>
                                form.setData('student_user_id', value)
                            }
                        >
                            <SelectTrigger id="create_student_user_id">
                                <SelectValue placeholder="Pilih mahasiswa bimbingan" />
                            </SelectTrigger>
                            <SelectContent>
                                {students.map((student) => (
                                    <SelectItem
                                        key={student.id}
                                        value={String(student.id)}
                                    >
                                        {student.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {form.errors.student_user_id && (
                            <p className="text-xs text-destructive">
                                {form.errors.student_user_id}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="create_topic">Topik Bimbingan</Label>
                        <Input
                            id="create_topic"
                            value={form.data.topic}
                            onChange={(e) =>
                                form.setData('topic', e.target.value)
                            }
                            required
                            placeholder="Contoh: Review Bab 3 - Metodologi Penelitian"
                        />
                        {form.errors.topic && (
                            <p className="text-xs text-destructive">
                                {form.errors.topic}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="create_requested_for">
                            Tanggal &amp; Waktu
                        </Label>
                        <Input
                            id="create_requested_for"
                            type="datetime-local"
                            value={form.data.requested_for}
                            onChange={(e) =>
                                form.setData('requested_for', e.target.value)
                            }
                            required
                        />
                        {form.errors.requested_for && (
                            <p className="text-xs text-destructive">
                                {form.errors.requested_for}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="create_location">Lokasi</Label>
                        <Input
                            id="create_location"
                            value={form.data.location}
                            onChange={(e) =>
                                form.setData('location', e.target.value)
                            }
                            required
                            placeholder="Contoh: Ruang Dosen 3 atau tautan Google Meet"
                        />
                        {form.errors.location && (
                            <p className="text-xs text-destructive">
                                {form.errors.location}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-3 rounded-lg border p-4">
                        <div className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id="create_is_recurring"
                                checked={form.data.is_recurring}
                                onChange={(e) =>
                                    form.setData(
                                        'is_recurring',
                                        e.target.checked,
                                    )
                                }
                                className="size-4 rounded border-input"
                            />
                            <Label
                                htmlFor="create_is_recurring"
                                className="flex items-center gap-2 font-medium"
                            >
                                <Repeat className="size-4" />
                                Jadwalkan Berulang
                            </Label>
                        </div>

                        {form.data.is_recurring && (
                            <div className="grid gap-3 pl-6">
                                <div className="grid gap-2">
                                    <Label htmlFor="create_recurring_pattern">
                                        Pola Pengulangan
                                    </Label>
                                    <Select
                                        value={form.data.recurring_pattern}
                                        onValueChange={(value) =>
                                            form.setData(
                                                'recurring_pattern',
                                                value,
                                            )
                                        }
                                    >
                                        <SelectTrigger id="create_recurring_pattern">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="weekly">
                                                Mingguan
                                            </SelectItem>
                                            <SelectItem value="biweekly">
                                                2 Mingguan
                                            </SelectItem>
                                            <SelectItem value="monthly">
                                                Bulanan
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {form.errors.recurring_pattern && (
                                        <p className="text-xs text-destructive">
                                            {form.errors.recurring_pattern}
                                        </p>
                                    )}
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="create_recurring_count">
                                        Jumlah Pertemuan
                                    </Label>
                                    <Select
                                        value={String(
                                            form.data.recurring_count,
                                        )}
                                        onValueChange={(value) =>
                                            form.setData(
                                                'recurring_count',
                                                parseInt(value, 10),
                                            )
                                        }
                                    >
                                        <SelectTrigger id="create_recurring_count">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {[
                                                2, 3, 4, 5, 6, 7, 8, 9, 10, 11,
                                                12,
                                            ].map((n) => (
                                                <SelectItem
                                                    key={n}
                                                    value={String(n)}
                                                >
                                                    {n} pertemuan
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {form.errors.recurring_count && (
                                        <p className="text-xs text-destructive">
                                            {form.errors.recurring_count}
                                        </p>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="create_lecturer_note">
                            Catatan Dosen (Opsional)
                        </Label>
                        <Textarea
                            id="create_lecturer_note"
                            value={form.data.lecturer_note}
                            onChange={(e) =>
                                form.setData('lecturer_note', e.target.value)
                            }
                            placeholder="Catatan untuk mahasiswa..."
                        />
                    </div>

                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            className="w-full sm:w-auto"
                            onClick={() => handleOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            className="w-full sm:w-auto"
                            disabled={form.processing || students.length === 0}
                        >
                            <CalendarPlus className="size-4" />
                            Buat Jadwal
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
