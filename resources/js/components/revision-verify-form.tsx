import { useForm } from '@inertiajs/react';
import { Send } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

import { type RevisionItem } from '@/components/revision-items';
import {
    type DraftItem,
    RevisionItemsEditor,
} from '@/components/revision-items-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { verify } from '@/routes/dosen/seminar-proposal/revisions';

type Decision = 'accepted' | 'reopened';

type Verdict = {
    decision: Decision | '';
    comment: string;
};

type VerifyFormData = {
    verdicts: Record<number, Verdict>;
    new_items: Array<{
        location: string | null;
        description: string;
        priority: 'major' | 'minor' | null;
    }>;
    annotation: File | null;
};

/**
 * The examiner marks each answered item "diterima" or "belum" (with a
 * comment) and may add new items; anything not accepted opens the next round.
 */
export function RevisionVerifyForm({
    revisionId,
    items,
}: {
    revisionId: number;
    items: RevisionItem[];
}) {
    const answered = items.filter((item) => item.status === 'addressed');
    const [newItems, setNewItems] = useState<DraftItem[]>([]);

    const form = useForm<VerifyFormData>({
        verdicts: Object.fromEntries(
            answered.map((item) => [item.id, { decision: '', comment: '' }]),
        ),
        new_items: [],
        annotation: null,
    });

    const errors = form.errors as Record<string, string | undefined>;
    const filledNewItems = newItems.filter(
        (item) => item.description.trim() !== '',
    );

    const ready = answered.every((item) => {
        const verdict = form.data.verdicts[item.id];
        return (
            verdict.decision === 'accepted' ||
            (verdict.decision === 'reopened' && verdict.comment.trim() !== '')
        );
    });
    const allAccepted =
        answered.every(
            (item) => form.data.verdicts[item.id].decision === 'accepted',
        ) && filledNewItems.length === 0;

    function update(itemId: number, patch: Partial<Verdict>) {
        form.setData('verdicts', {
            ...form.data.verdicts,
            [itemId]: { ...form.data.verdicts[itemId], ...patch },
        });
    }

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            new_items: filledNewItems.map((item) => ({
                location: item.location.trim() || null,
                description: item.description.trim(),
                priority: item.priority === 'none' ? null : item.priority,
            })),
        }));
        form.post(verify(revisionId).url, {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    if (answered.length === 0) return null;

    return (
        <form
            onSubmit={submit}
            className="mt-4 grid gap-4"
            data-testid="revision-verify-form"
        >
            <h4 className="text-sm font-semibold">
                Verifikasi {answered.length} butir
            </h4>

            <ol className="divide-y rounded-md border">
                {answered.map((item, index) => {
                    const verdict = form.data.verdicts[item.id];

                    return (
                        <li
                            key={item.id}
                            className="grid gap-3 p-3"
                            data-testid="revision-verify-item"
                        >
                            <div className="grid gap-1 text-sm leading-relaxed text-pretty">
                                <p>
                                    <span className="font-medium tabular-nums">
                                        {index + 1}.
                                    </span>{' '}
                                    {item.location && (
                                        <span className="font-medium">
                                            {item.location}:{' '}
                                        </span>
                                    )}
                                    {item.description}
                                </p>
                                <p className="text-muted-foreground">
                                    <span className="font-medium text-foreground">
                                        Tanggapan mahasiswa:
                                    </span>{' '}
                                    {item.studentResponse}
                                    {item.studentResponseLocation &&
                                        ` (${item.studentResponseLocation})`}
                                </p>
                            </div>

                            <fieldset className="grid gap-2">
                                <legend className="sr-only">
                                    Keputusan butir {index + 1}
                                </legend>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant={
                                            verdict.decision === 'accepted'
                                                ? 'default'
                                                : 'outline'
                                        }
                                        aria-pressed={
                                            verdict.decision === 'accepted'
                                        }
                                        className="max-md:h-11 max-md:flex-1"
                                        onClick={() =>
                                            update(item.id, {
                                                decision: 'accepted',
                                            })
                                        }
                                    >
                                        Diterima
                                        <span className="sr-only">
                                            {' '}
                                            butir {index + 1}
                                        </span>
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant={
                                            verdict.decision === 'reopened'
                                                ? 'default'
                                                : 'outline'
                                        }
                                        aria-pressed={
                                            verdict.decision === 'reopened'
                                        }
                                        className="max-md:h-11 max-md:flex-1"
                                        onClick={() =>
                                            update(item.id, {
                                                decision: 'reopened',
                                            })
                                        }
                                    >
                                        Belum
                                        <span className="sr-only">
                                            {' '}
                                            butir {index + 1}
                                        </span>
                                    </Button>
                                </div>
                                {errors[`verdicts.${item.id}.decision`] && (
                                    <p className="text-sm text-destructive">
                                        {errors[`verdicts.${item.id}.decision`]}
                                    </p>
                                )}
                            </fieldset>

                            <div className="grid gap-1.5">
                                <Label htmlFor={`verdict-comment-${item.id}`}>
                                    Komentar butir {index + 1}
                                    {verdict.decision === 'reopened'
                                        ? ' *'
                                        : ' (opsional)'}
                                </Label>
                                <Textarea
                                    id={`verdict-comment-${item.id}`}
                                    rows={2}
                                    maxLength={2000}
                                    value={verdict.comment}
                                    className="min-h-24 resize-y"
                                    onChange={(e) =>
                                        update(item.id, {
                                            comment: e.target.value,
                                        })
                                    }
                                />
                                {errors[`verdicts.${item.id}.comment`] && (
                                    <p className="text-sm text-destructive">
                                        {errors[`verdicts.${item.id}.comment`]}
                                    </p>
                                )}
                            </div>
                        </li>
                    );
                })}
            </ol>

            <RevisionItemsEditor
                items={newItems}
                onChange={setNewItems}
                label="Butir baru (opsional)"
                emptyHint="Tambahkan butir baru jika ada hal lain yang perlu diperbaiki. Butir baru membuka putaran berikutnya."
                idPrefix={`new-item-${revisionId}`}
            />
            {errors['new_items.0.description'] && (
                <p className="text-sm text-destructive">
                    {errors['new_items.0.description']}
                </p>
            )}

            {!allAccepted && (
                <div className="grid gap-1.5">
                    <Label htmlFor={`verify-annotation-${revisionId}`}>
                        Naskah beranotasi putaran berikutnya (PDF, opsional)
                    </Label>
                    <Input
                        id={`verify-annotation-${revisionId}`}
                        type="file"
                        accept=".pdf"
                        className="max-md:h-11"
                        onChange={(e) =>
                            form.setData(
                                'annotation',
                                e.target.files?.[0] ?? null,
                            )
                        }
                    />
                    {errors.annotation && (
                        <p className="text-sm text-destructive">
                            {errors.annotation}
                        </p>
                    )}
                </div>
            )}

            {errors.verdicts && (
                <p className="text-sm text-destructive">{errors.verdicts}</p>
            )}

            <div>
                <Button
                    type="submit"
                    className="max-md:h-11 max-md:w-full"
                    disabled={form.processing || !ready}
                >
                    <Send className="size-4" />
                    {form.processing
                        ? 'Menyimpan...'
                        : allAccepted
                          ? 'Simpan dan selesaikan revisi'
                          : 'Simpan dan buka putaran berikutnya'}
                </Button>
            </div>
        </form>
    );
}
