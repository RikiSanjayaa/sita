import { Form } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/patterns/status-badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export type ReviewRevisionItem = {
    id: string;
    description: string;
    resolved_at: string | null;
};

export type DocumentRevisionItem = {
    id: string;
    description: string;
    resolved: boolean;
    resolvedAt: string | null;
    resolveUrl: string | null;
};

export function RevisionEditor({
    initialItems,
    processing,
    errors,
    compact = false,
}: {
    initialItems: ReviewRevisionItem[];
    processing: boolean;
    errors: Record<string, string>;
    compact?: boolean;
}) {
    const [items, setItems] = useState(() =>
        initialItems.map((item) => ({ ...item, key: item.id })),
    );

    function notifyForm(form: HTMLFormElement | null) {
        requestAnimationFrame(() => {
            form?.dispatchEvent(new Event('input', { bubbles: true }));
        });
    }

    return (
        <fieldset
            disabled={processing}
            className={
                compact ? 'grid min-w-0 gap-2' : 'mt-5 grid gap-4 border-t pt-5'
            }
        >
            <legend className="float-left w-full text-sm font-medium">
                Butir revisi
            </legend>
            <p
                className={
                    compact
                        ? 'text-xs text-pretty text-muted-foreground'
                        : 'text-sm text-muted-foreground'
                }
            >
                Mahasiswa dapat menandai setiap butir selesai tanpa wajib
                mengunggah ulang. Penyelesaian bukan persetujuan dokumen.
                Perubahan uraian membuka kembali butir tersebut.
            </p>
            {items.map((item, index) => (
                <div
                    key={item.key}
                    className={
                        compact
                            ? 'grid grid-cols-[minmax(0,1fr)_auto] gap-x-1 gap-y-1'
                            : 'grid gap-2'
                    }
                >
                    <input
                        type="hidden"
                        name={`revision_items[${index}][id]`}
                        value={item.id}
                    />
                    <div
                        className={
                            compact
                                ? 'contents'
                                : 'flex items-center justify-between gap-3'
                        }
                    >
                        <Label htmlFor={`revision-item-${item.key}`}>
                            Butir {index + 1}
                        </Label>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className={
                                compact
                                    ? 'col-start-2 row-start-2 self-start max-md:size-11'
                                    : 'max-md:size-11'
                            }
                            aria-label={`Hapus butir ${index + 1}`}
                            onClick={(event) => {
                                const form = event.currentTarget.form;
                                setItems((current) =>
                                    current.filter(
                                        (entry) => entry.key !== item.key,
                                    ),
                                );
                                notifyForm(form);
                            }}
                        >
                            <Trash2 aria-hidden className="size-4" />
                        </Button>
                    </div>
                    <Textarea
                        id={`revision-item-${item.key}`}
                        name={`revision_items[${index}][description]`}
                        value={item.description}
                        onChange={(event) =>
                            setItems((current) =>
                                current.map((entry) =>
                                    entry.key === item.key
                                        ? {
                                              ...entry,
                                              description: event.target.value,
                                          }
                                        : entry,
                                ),
                            )
                        }
                        rows={compact ? 2 : 3}
                        className={
                            compact
                                ? 'col-start-1 row-start-2 min-h-16'
                                : undefined
                        }
                        aria-invalid={Boolean(
                            errors[`revision_items.${index}.description`],
                        )}
                    />
                    <InputError
                        message={errors[`revision_items.${index}.description`]}
                    />
                </div>
            ))}
            <InputError message={errors.revision_items} />
            <Button
                type="button"
                variant="outline"
                className="w-fit max-md:min-h-11"
                onClick={(event) => {
                    const form = event.currentTarget.form;
                    setItems((current) => [
                        ...current,
                        {
                            key: crypto.randomUUID(),
                            id: '',
                            description: '',
                            resolved_at: null,
                        },
                    ]);
                    notifyForm(form);
                }}
            >
                <Plus aria-hidden className="size-4" />
                Tambah butir
            </Button>
        </fieldset>
    );
}

export function RevisionList({
    items,
    compact = false,
}: {
    items: DocumentRevisionItem[];
    compact?: boolean;
}) {
    return (
        <ol className={compact ? 'grid gap-2' : 'grid gap-4'}>
            {items.map((item, index) => (
                <li
                    key={item.id}
                    className={
                        compact
                            ? 'grid gap-1 border-t pt-2 first:border-0 first:pt-0'
                            : 'grid gap-2 border-t pt-4 first:border-0 first:pt-0'
                    }
                >
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <span className="text-sm font-medium">
                            Butir {index + 1}
                        </span>
                        <StatusBadge
                            domain="document"
                            status={
                                item.resolved
                                    ? 'revision_completed'
                                    : 'needs_revision'
                            }
                        >
                            {item.resolved ? 'Selesai' : 'Belum selesai'}
                        </StatusBadge>
                    </div>
                    <p className="max-w-prose text-sm break-words whitespace-pre-wrap">
                        {item.description}
                    </p>
                    {item.resolvedAt && (
                        <p className="text-xs text-muted-foreground">
                            Diselesaikan {item.resolvedAt}
                        </p>
                    )}
                    {item.resolveUrl && (
                        <Form
                            action={item.resolveUrl}
                            method="patch"
                            options={{ preserveScroll: true }}
                        >
                            {({ processing, errors }) => (
                                <div className="grid gap-2">
                                    <input
                                        type="hidden"
                                        name="resolved"
                                        value={item.resolved ? '0' : '1'}
                                    />
                                    <Button
                                        type="submit"
                                        size="sm"
                                        variant="outline"
                                        disabled={processing}
                                        className="w-fit max-md:min-h-11"
                                    >
                                        {processing
                                            ? 'Menyimpan...'
                                            : item.resolved
                                              ? 'Buka kembali'
                                              : 'Tandai selesai'}
                                    </Button>
                                    <InputError message={errors.resolved} />
                                </div>
                            )}
                        </Form>
                    )}
                </li>
            ))}
        </ol>
    );
}
