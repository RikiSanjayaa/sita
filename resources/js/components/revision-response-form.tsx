import { useForm } from '@inertiajs/react';
import { Send } from 'lucide-react';
import { type FormEventHandler } from 'react';

import { type RevisionItem } from '@/components/revision-items';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { respond } from '@/routes/mahasiswa/tugas-akhir/revisions';

type ItemAnswer = {
    addressed: boolean;
    response: string;
    location: string;
};

type ResponseFormData = {
    responses: Record<number, ItemAnswer>;
    manuscript: File | null;
};

const blankAnswer: ItemAnswer = {
    addressed: false,
    response: '',
    location: '',
};

/**
 * The mahasiswa answers every open/reopened item of the current round and
 * uploads the corrected manuscript. Submitting needs all of it.
 */
export function RevisionResponseForm({
    revisionId,
    items,
}: {
    revisionId: number;
    items: RevisionItem[];
}) {
    const pending = items.filter(
        (item) => item.status === 'open' || item.status === 'reopened',
    );

    const form = useForm<ResponseFormData>({
        responses: Object.fromEntries(
            pending.map((item) => [item.id, { ...blankAnswer }]),
        ),
        manuscript: null,
    });

    const errors = form.errors as Record<string, string | undefined>;

    const ready =
        form.data.manuscript !== null &&
        pending.every((item) => {
            const answer = form.data.responses[item.id];
            return answer.addressed && answer.response.trim() !== '';
        });

    function update(itemId: number, patch: Partial<ItemAnswer>) {
        form.setData('responses', {
            ...form.data.responses,
            [itemId]: { ...form.data.responses[itemId], ...patch },
        });
    }

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        form.post(respond(revisionId).url, {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    if (pending.length === 0) return null;

    return (
        <form
            onSubmit={submit}
            className="mt-4 grid gap-4"
            data-testid="revision-response-form"
        >
            <h4 className="text-sm font-semibold">
                Tanggapi {pending.length} butir revisi
            </h4>

            <ol className="divide-y rounded-md border">
                {pending.map((item, index) => {
                    const answer = form.data.responses[item.id];

                    return (
                        <li
                            key={item.id}
                            className="grid gap-3 p-3"
                            data-testid="revision-response-item"
                        >
                            <p className="text-sm leading-relaxed text-pretty">
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
                            {item.verificationComment && (
                                <p className="text-sm text-muted-foreground">
                                    Komentar penguji: {item.verificationComment}
                                </p>
                            )}

                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id={`addressed-${item.id}`}
                                    checked={answer.addressed}
                                    onCheckedChange={(checked) =>
                                        update(item.id, {
                                            addressed: checked === true,
                                        })
                                    }
                                    className="size-5 max-md:size-6"
                                />
                                <Label
                                    htmlFor={`addressed-${item.id}`}
                                    className="min-h-6 flex-1 max-md:min-h-11 max-md:items-center"
                                >
                                    Butir {index + 1} sudah diperbaiki
                                </Label>
                            </div>
                            {errors[`responses.${item.id}.addressed`] && (
                                <p className="text-sm text-destructive">
                                    {errors[`responses.${item.id}.addressed`]}
                                </p>
                            )}

                            <div className="grid gap-1.5">
                                <Label htmlFor={`response-${item.id}`}>
                                    Tanggapan butir {index + 1}
                                </Label>
                                <Textarea
                                    id={`response-${item.id}`}
                                    rows={2}
                                    maxLength={2000}
                                    value={answer.response}
                                    placeholder="Jelaskan perbaikan yang Anda lakukan..."
                                    className="resize-none"
                                    onChange={(e) =>
                                        update(item.id, {
                                            response: e.target.value,
                                        })
                                    }
                                />
                                {errors[`responses.${item.id}.response`] && (
                                    <p className="text-sm text-destructive">
                                        {
                                            errors[
                                                `responses.${item.id}.response`
                                            ]
                                        }
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor={`location-${item.id}`}>
                                    Lokasi perbaikan (opsional)
                                </Label>
                                <Input
                                    id={`location-${item.id}`}
                                    value={answer.location}
                                    maxLength={255}
                                    placeholder="Mis. Bab 3, hlm. 45"
                                    className="max-md:h-11"
                                    onChange={(e) =>
                                        update(item.id, {
                                            location: e.target.value,
                                        })
                                    }
                                />
                            </div>
                        </li>
                    );
                })}
            </ol>

            <div className="grid gap-1.5">
                <Label htmlFor={`manuscript-${revisionId}`}>
                    Naskah perbaikan (PDF atau Word)
                </Label>
                <Input
                    id={`manuscript-${revisionId}`}
                    type="file"
                    accept=".pdf,.doc,.docx"
                    className="max-md:h-11"
                    onChange={(e) =>
                        form.setData('manuscript', e.target.files?.[0] ?? null)
                    }
                />
                {errors.manuscript && (
                    <p className="text-sm text-destructive">
                        {errors.manuscript}
                    </p>
                )}
                {errors.responses && (
                    <p className="text-sm text-destructive">
                        {errors.responses}
                    </p>
                )}
            </div>

            <div>
                <Button
                    type="submit"
                    className="max-md:h-11 max-md:w-full"
                    disabled={form.processing || !ready}
                >
                    <Send className="size-4" />
                    {form.processing ? 'Mengirim...' : 'Kirim perbaikan'}
                </Button>
            </div>
        </form>
    );
}
