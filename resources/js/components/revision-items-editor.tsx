import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
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

export type DraftItem = {
    key: number;
    location: string;
    description: string;
    priority: 'none' | 'major' | 'minor';
};

let draftItemKey = 0;

export function newDraftItem(): DraftItem {
    draftItemKey += 1;
    return {
        key: draftItemKey,
        location: '',
        description: '',
        priority: 'none',
    };
}

export function RevisionItemsEditor({
    items,
    onChange,
    label = 'Butir Revisi (opsional)',
    emptyHint = 'Tambahkan butir per lokasi di naskah agar mahasiswa dapat menindaklanjuti satu per satu.',
    idPrefix = 'item',
}: {
    items: DraftItem[];
    onChange: (items: DraftItem[]) => void;
    label?: string;
    emptyHint?: string;
    idPrefix?: string;
}) {
    function update(key: number, patch: Partial<DraftItem>) {
        onChange(
            items.map((item) =>
                item.key === key ? { ...item, ...patch } : item,
            ),
        );
    }

    function move(index: number, offset: -1 | 1) {
        const target = index + offset;
        if (target < 0 || target >= items.length) return;
        const next = [...items];
        [next[index], next[target]] = [next[target], next[index]];
        onChange(next);
    }

    return (
        <div className="grid gap-2">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <Label>{label}</Label>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className="max-md:h-11"
                    onClick={() => onChange([...items, newDraftItem()])}
                >
                    <Plus className="size-4" /> Tambah Butir
                </Button>
            </div>

            {items.length === 0 ? (
                <p className="text-sm text-muted-foreground">{emptyHint}</p>
            ) : (
                <ol className="divide-y rounded-md border">
                    {items.map((item, index) => (
                        <li
                            key={item.key}
                            className="grid gap-3 p-3"
                            data-testid="revision-item-row"
                        >
                            <div className="grid gap-3 sm:grid-cols-[1fr_9rem]">
                                <div className="grid gap-1.5">
                                    <Label
                                        htmlFor={`${idPrefix}-location-${item.key}`}
                                    >
                                        Butir {index + 1}: lokasi
                                    </Label>
                                    <Input
                                        id={`${idPrefix}-location-${item.key}`}
                                        value={item.location}
                                        maxLength={255}
                                        placeholder="Mis. Bab 3, hlm. 42"
                                        className="max-md:h-11"
                                        onChange={(e) =>
                                            update(item.key, {
                                                location: e.target.value,
                                            })
                                        }
                                    />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label
                                        htmlFor={`${idPrefix}-priority-${item.key}`}
                                    >
                                        Prioritas
                                    </Label>
                                    <Select
                                        value={item.priority}
                                        onValueChange={(value) =>
                                            update(item.key, {
                                                priority:
                                                    value as DraftItem['priority'],
                                            })
                                        }
                                    >
                                        <SelectTrigger
                                            id={`${idPrefix}-priority-${item.key}`}
                                            className="max-md:h-11"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">
                                                Tanpa prioritas
                                            </SelectItem>
                                            <SelectItem value="major">
                                                Mayor
                                            </SelectItem>
                                            <SelectItem value="minor">
                                                Minor
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor={`${idPrefix}-description-${item.key}`}
                                >
                                    Uraian
                                </Label>
                                <Textarea
                                    id={`${idPrefix}-description-${item.key}`}
                                    rows={2}
                                    maxLength={2000}
                                    value={item.description}
                                    placeholder="Apa yang perlu diperbaiki..."
                                    className="resize-none"
                                    onChange={(e) =>
                                        update(item.key, {
                                            description: e.target.value,
                                        })
                                    }
                                />
                            </div>
                            <div className="flex flex-wrap gap-1">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    className="max-md:h-11"
                                    disabled={index === 0}
                                    aria-label={`Naikkan butir ${index + 1}`}
                                    onClick={() => move(index, -1)}
                                >
                                    <ArrowUp className="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    className="max-md:h-11"
                                    disabled={index === items.length - 1}
                                    aria-label={`Turunkan butir ${index + 1}`}
                                    onClick={() => move(index, 1)}
                                >
                                    <ArrowDown className="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    className="ml-auto max-md:h-11"
                                    aria-label={`Hapus butir ${index + 1}`}
                                    onClick={() =>
                                        onChange(
                                            items.filter(
                                                (entry) =>
                                                    entry.key !== item.key,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 className="size-4" /> Hapus
                                </Button>
                            </div>
                        </li>
                    ))}
                </ol>
            )}
        </div>
    );
}
