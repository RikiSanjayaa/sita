import { type InertiaFormProps, useForm } from '@inertiajs/react';
import { MessageSquareText } from 'lucide-react';
import { type FormEvent, useState } from 'react';

import { EmptyState } from '@/components/patterns/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { destroy, store, update } from '@/routes/dosen/project-notes';

export type NoteVisibility = 'internal' | 'student';

export type ThesisNote = {
    id: number;
    projectId: number;
    visibility: NoteVisibility;
    visibilityLabel: string;
    body: string;
    authorName: string;
    authorRole: string;
    createdAt: string;
    isEdited: boolean;
    canManage: boolean;
};

type NoteFormData = {
    visibility: NoteVisibility;
    body: string;
};

export type ProjectNotes = {
    canWrite: boolean;
    notes: ThesisNote[];
};

const visibilityOptions: { value: NoteVisibility; label: string }[] = [
    { value: 'internal', label: 'Internal dosen' },
    { value: 'student', label: 'Untuk mahasiswa' },
];

const visibilityHints: Record<NoteVisibility, string> = {
    internal:
        'Hanya terlihat oleh pembimbing, penguji, kaprodi, dan admin. Mahasiswa tidak melihatnya.',
    student:
        'Terlihat oleh mahasiswa di halaman Tugas Akhir dan dikirim sebagai notifikasi.',
};

function VisibilityBadge({
    visibility,
    label,
}: {
    visibility: NoteVisibility;
    label: string;
}) {
    return (
        <Badge
            variant="outline"
            className={
                visibility === 'internal'
                    ? 'rounded-full border-transparent bg-warning-muted text-warning-muted-foreground'
                    : 'rounded-full border-transparent bg-info-muted text-info-muted-foreground'
            }
        >
            {label}
        </Badge>
    );
}

function VisibilityPicker({
    id,
    value,
    onChange,
}: {
    id: string;
    value: NoteVisibility;
    onChange: (value: NoteVisibility) => void;
}) {
    return (
        <div className="grid gap-1.5">
            <Label id={`${id}-label`}>Visibilitas</Label>
            <div
                role="radiogroup"
                aria-labelledby={`${id}-label`}
                className="flex flex-wrap gap-2"
            >
                {visibilityOptions.map((option) => (
                    <Button
                        key={option.value}
                        type="button"
                        size="sm"
                        role="radio"
                        aria-checked={value === option.value}
                        variant={value === option.value ? 'default' : 'outline'}
                        onClick={() => onChange(option.value)}
                    >
                        {option.label}
                    </Button>
                ))}
            </div>
            <p className="text-xs text-muted-foreground">
                {visibilityHints[value]}
            </p>
        </div>
    );
}

function NoteForm({
    id,
    initial,
    submitLabel,
    submit,
    onCancel,
}: {
    id: string;
    initial: NoteFormData;
    submitLabel: string;
    submit: (form: InertiaFormProps<NoteFormData>) => void;
    onCancel?: () => void;
}) {
    const form = useForm<NoteFormData>(initial);

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        submit(form);
    }

    return (
        <form onSubmit={handleSubmit} className="grid gap-3">
            <VisibilityPicker
                id={id}
                value={form.data.visibility}
                onChange={(value) => form.setData('visibility', value)}
            />
            <div className="grid gap-1.5">
                <Label htmlFor={`${id}-body`}>Catatan</Label>
                <Textarea
                    id={`${id}-body`}
                    value={form.data.body}
                    onChange={(event) =>
                        form.setData('body', event.target.value)
                    }
                    rows={3}
                    maxLength={5000}
                    placeholder="Tulis catatan untuk tugas akhir ini"
                    aria-invalid={form.errors.body !== undefined}
                />
                {form.errors.body && (
                    <p className="text-xs text-destructive">
                        {form.errors.body}
                    </p>
                )}
                {form.errors.visibility && (
                    <p className="text-xs text-destructive">
                        {form.errors.visibility}
                    </p>
                )}
            </div>
            <div className="flex justify-end gap-2">
                {onCancel && (
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={onCancel}
                    >
                        Batal
                    </Button>
                )}
                <Button
                    type="submit"
                    size="sm"
                    disabled={form.processing || form.data.body.trim() === ''}
                >
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}

function NoteItem({
    note,
    showVisibility,
}: {
    note: ThesisNote;
    showVisibility: boolean;
}) {
    const [editing, setEditing] = useState(false);
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const deleteForm = useForm({});

    return (
        <li
            className="grid gap-2 rounded-lg border bg-card p-3"
            data-testid="thesis-note"
        >
            <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span className="text-sm font-medium">{note.authorName}</span>
                <span className="text-xs text-muted-foreground">
                    {note.authorRole}
                </span>
                {showVisibility && (
                    <VisibilityBadge
                        visibility={note.visibility}
                        label={note.visibilityLabel}
                    />
                )}
                <span className="ml-auto text-xs text-muted-foreground tabular-nums">
                    {note.createdAt}
                    {note.isEdited && ' (diedit)'}
                </span>
            </div>

            {editing ? (
                <NoteForm
                    id={`note-edit-${note.id}`}
                    initial={{ visibility: note.visibility, body: note.body }}
                    submitLabel="Simpan perubahan"
                    onCancel={() => setEditing(false)}
                    submit={(form) =>
                        form.patch(update(note.id).url, {
                            preserveScroll: true,
                            onSuccess: () => setEditing(false),
                        })
                    }
                />
            ) : (
                <p className="text-sm leading-relaxed whitespace-pre-wrap">
                    {note.body}
                </p>
            )}

            {note.canManage && !editing && (
                <div className="flex justify-end gap-2">
                    {confirmingDelete ? (
                        <>
                            <span className="self-center text-xs text-muted-foreground">
                                Hapus catatan ini?
                            </span>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={() => setConfirmingDelete(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="destructive"
                                disabled={deleteForm.processing}
                                onClick={() =>
                                    deleteForm.delete(destroy(note.id).url, {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                Ya, hapus
                            </Button>
                        </>
                    ) : (
                        <>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={() => setEditing(true)}
                            >
                                Edit
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={() => setConfirmingDelete(true)}
                            >
                                Hapus
                            </Button>
                        </>
                    )}
                </div>
            )}
        </li>
    );
}

/**
 * Notes list for a project. Writers also get the add form. `showVisibility`
 * is false for the student, who only ever receives student-visible notes.
 */
export function ThesisNotesPanel({
    projectId,
    data,
    showVisibility = true,
}: {
    projectId: number | null;
    data: ProjectNotes | undefined;
    showVisibility?: boolean;
}) {
    if (projectId === null || data === undefined) {
        return (
            <EmptyState
                icon={MessageSquareText}
                title="Catatan tidak tersedia"
                description="Mahasiswa belum memiliki tugas akhir aktif."
            />
        );
    }

    return (
        <div className="grid gap-4">
            {data.canWrite && (
                <NoteForm
                    id={`note-new-${projectId}`}
                    initial={{ visibility: 'internal', body: '' }}
                    submitLabel="Simpan catatan"
                    submit={(form) =>
                        form.post(store(projectId).url, {
                            preserveScroll: true,
                            onSuccess: () => form.reset('body'),
                        })
                    }
                />
            )}

            {data.notes.length === 0 ? (
                <EmptyState
                    icon={MessageSquareText}
                    title="Belum ada catatan"
                    description={
                        data.canWrite
                            ? 'Tambahkan catatan untuk pembimbing, penguji, atau mahasiswa.'
                            : undefined
                    }
                />
            ) : (
                <ul className="grid gap-2" aria-label="Daftar catatan">
                    {data.notes.map((note) => (
                        <NoteItem
                            key={note.id}
                            note={note}
                            showVisibility={showVisibility}
                        />
                    ))}
                </ul>
            )}
        </div>
    );
}
