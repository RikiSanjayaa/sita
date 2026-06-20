import { LoaderCircle, Search, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type LecturerSearchPurpose = 'supervisor' | 'examiner';

type LecturerSearchResult = {
    id: number;
    name: string;
    nik: string | null;
    sameProgram: boolean;
    placements: string[];
    concentrations: string[];
    expertiseFields: string[];
    activeSupervisionCount: number;
    quota: number;
    available: boolean;
    unavailableReason: string | null;
    profileUrl: string;
};

type CommonProps = {
    projectId: number | null;
    purpose: LecturerSearchPurpose;
    error?: string;
    excludeIds?: string[];
};

function useLecturerSearch({
    projectId,
    purpose,
    selectedIds,
}: {
    projectId: number | null;
    purpose: LecturerSearchPurpose;
    selectedIds: string[];
}) {
    const [query, setQuery] = useState('');
    const [fetchedResults, setFetchedResults] = useState<
        LecturerSearchResult[]
    >([]);
    const [fetchedSelected, setFetchedSelected] = useState<
        LecturerSearchResult[]
    >([]);
    const [loading, setLoading] = useState(false);
    const selectedKey = selectedIds.filter(Boolean).sort().join(',');

    useEffect(() => {
        if (!projectId || selectedKey === '') {
            return;
        }

        const controller = new AbortController();
        const parameters = new URLSearchParams({
            project_id: String(projectId),
            purpose,
        });
        selectedKey
            .split(',')
            .forEach((id) => parameters.append('selected_ids[]', id));

        fetch(`/kaprodi/lecturers/search?${parameters.toString()}`, {
            signal: controller.signal,
            headers: { Accept: 'application/json' },
        })
            .then((response) => (response.ok ? response.json() : { data: [] }))
            .then((payload: { data: LecturerSearchResult[] }) =>
                setFetchedSelected(payload.data),
            )
            .catch((error: unknown) => {
                if (
                    !(
                        error instanceof DOMException &&
                        error.name === 'AbortError'
                    )
                ) {
                    setFetchedSelected([]);
                }
            });

        return () => controller.abort();
    }, [projectId, purpose, selectedKey]);

    useEffect(() => {
        const search = query.trim();

        if (!projectId || search.length < 2) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            setLoading(true);
            const parameters = new URLSearchParams({
                project_id: String(projectId),
                purpose,
                q: search,
            });

            fetch(`/kaprodi/lecturers/search?${parameters.toString()}`, {
                signal: controller.signal,
                headers: { Accept: 'application/json' },
            })
                .then((response) =>
                    response.ok ? response.json() : { data: [] },
                )
                .then((payload: { data: LecturerSearchResult[] }) =>
                    setFetchedResults(payload.data),
                )
                .catch((error: unknown) => {
                    if (
                        !(
                            error instanceof DOMException &&
                            error.name === 'AbortError'
                        )
                    ) {
                        setFetchedResults([]);
                    }
                })
                .finally(() => setLoading(false));
        }, 300);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [projectId, purpose, query]);

    return {
        query,
        setQuery,
        results: query.trim().length >= 2 ? fetchedResults : [],
        selected: selectedKey === '' ? [] : fetchedSelected,
        loading: query.trim().length >= 2 && loading,
    };
}

function LecturerResult({
    lecturer,
    onSelect,
    disabled,
}: {
    lecturer: LecturerSearchResult;
    onSelect: () => void;
    disabled: boolean;
}) {
    return (
        <button
            type="button"
            disabled={disabled || !lecturer.available}
            onClick={onSelect}
            className="grid w-full gap-1.5 border-b px-3 py-2.5 text-left transition-colors last:border-b-0 hover:bg-muted/60 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <span className="flex min-w-0 items-center justify-between gap-2">
                <span className="truncate text-sm font-medium">
                    {lecturer.name}
                </span>
                <span className="shrink-0 text-[11px] text-muted-foreground">
                    {lecturer.activeSupervisionCount}/{lecturer.quota} aktif
                </span>
            </span>
            <span className="truncate text-xs text-muted-foreground">
                NIK {lecturer.nik ?? '-'} ·{' '}
                {lecturer.placements.join(', ') || 'Tanpa penempatan prodi'}
            </span>
            <span className="flex flex-wrap gap-1">
                {lecturer.sameProgram ? (
                    <Badge variant="outline" className="text-[10px]">
                        Prodi yang sama
                    </Badge>
                ) : null}
                {lecturer.expertiseFields.slice(0, 3).map((field) => (
                    <Badge
                        key={field}
                        variant="secondary"
                        className="text-[10px]"
                    >
                        {field}
                    </Badge>
                ))}
                {!lecturer.available ? (
                    <Badge variant="destructive" className="text-[10px]">
                        Kuota penuh
                    </Badge>
                ) : null}
            </span>
        </button>
    );
}

export function LecturerSearchSelect({
    label,
    value,
    onChange,
    projectId,
    purpose,
    error,
    excludeIds = [],
    optional = false,
}: CommonProps & {
    label: string;
    value: string;
    onChange: (value: string) => void;
    optional?: boolean;
}) {
    const { query, setQuery, results, selected, loading } = useLecturerSearch({
        projectId,
        purpose,
        selectedIds: value ? [value] : [],
    });
    const selectedLecturer = selected.find(
        (lecturer) => String(lecturer.id) === value,
    );

    return (
        <div className="grid min-w-0 gap-1.5">
            <label className="text-sm font-medium">{label}</label>
            {selectedLecturer ? (
                <div className="flex items-start justify-between gap-2 rounded-md border bg-muted/20 p-2.5">
                    <div className="min-w-0">
                        <p className="truncate text-sm font-medium">
                            {selectedLecturer.name}
                        </p>
                        <p className="truncate text-xs text-muted-foreground">
                            {selectedLecturer.placements.join(', ') ||
                                'Tanpa penempatan prodi'}
                        </p>
                    </div>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        className="size-7 shrink-0"
                        onClick={() => onChange('')}
                        aria-label={optional ? 'Hapus pilihan' : 'Ganti dosen'}
                    >
                        <X className="size-3.5" />
                    </Button>
                </div>
            ) : null}
            <div className="relative z-20">
                <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                <Input
                    value={query}
                    disabled={!projectId}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Ketik min. 2 karakter..."
                    className="pl-8"
                />
                {loading ? (
                    <LoaderCircle className="absolute top-2.5 right-2.5 size-4 animate-spin text-muted-foreground" />
                ) : null}
                {query.trim().length >= 2 ? (
                    <div className="absolute top-full right-0 left-0 z-50 mt-1 max-h-60 overflow-y-auto rounded-md border bg-popover text-popover-foreground shadow-lg">
                        {results.length > 0 ? (
                            results.map((lecturer) => (
                                <LecturerResult
                                    key={lecturer.id}
                                    lecturer={lecturer}
                                    disabled={
                                        String(lecturer.id) === value ||
                                        excludeIds.includes(String(lecturer.id))
                                    }
                                    onSelect={() => {
                                        onChange(String(lecturer.id));
                                        setQuery('');
                                    }}
                                />
                            ))
                        ) : !loading ? (
                            <p className="px-3 py-4 text-center text-xs text-muted-foreground">
                                Tidak ada dosen yang cocok.
                            </p>
                        ) : null}
                    </div>
                ) : null}
            </div>
            {query.trim().length < 2 ? (
                <p className="text-[11px] text-muted-foreground">
                    Cari nama, NIK, prodi, konsentrasi, atau bidang keilmuan.
                </p>
            ) : null}
            {error ? <p className="text-xs text-destructive">{error}</p> : null}
        </div>
    );
}

export function LecturerMultiSearch({
    label,
    values,
    onChange,
    projectId,
    purpose,
    error,
    excludeIds = [],
}: CommonProps & {
    label: string;
    values: string[];
    onChange: (values: string[]) => void;
}) {
    const { query, setQuery, results, selected, loading } = useLecturerSearch({
        projectId,
        purpose,
        selectedIds: values,
    });
    const selectedById = useMemo(
        () =>
            new Map(
                selected.map((lecturer) => [String(lecturer.id), lecturer]),
            ),
        [selected],
    );

    return (
        <div className="grid gap-2">
            <p className="text-sm font-medium">{label}</p>
            {values.length > 0 ? (
                <div className="grid gap-2 sm:grid-cols-2">
                    {values.map((id) => {
                        const lecturer = selectedById.get(id);

                        return (
                            <div
                                key={id}
                                className="flex min-w-0 items-center justify-between gap-2 rounded-md border bg-muted/20 px-3 py-2"
                            >
                                <span className="min-w-0 truncate text-sm font-medium">
                                    {lecturer?.name ?? `Dosen #${id}`}
                                </span>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    className="size-7 shrink-0"
                                    onClick={() =>
                                        onChange(
                                            values.filter(
                                                (value) => value !== id,
                                            ),
                                        )
                                    }
                                >
                                    <X className="size-3.5" />
                                </Button>
                            </div>
                        );
                    })}
                </div>
            ) : null}
            <div className="relative z-20">
                <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                <Input
                    value={query}
                    disabled={!projectId}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Cari dosen penguji (min. 2 karakter)..."
                    className="pl-8"
                />
                {loading ? (
                    <LoaderCircle className="absolute top-2.5 right-2.5 size-4 animate-spin text-muted-foreground" />
                ) : null}
                {query.trim().length >= 2 ? (
                    <div
                        className={cn(
                            'absolute top-full right-0 left-0 z-50 mt-1 max-h-60 overflow-y-auto rounded-md border bg-popover text-popover-foreground shadow-lg',
                            results.length === 0 && 'py-3',
                        )}
                    >
                        {results.length > 0 ? (
                            results.map((lecturer) => (
                                <LecturerResult
                                    key={lecturer.id}
                                    lecturer={lecturer}
                                    disabled={
                                        values.includes(String(lecturer.id)) ||
                                        excludeIds.includes(String(lecturer.id))
                                    }
                                    onSelect={() => {
                                        onChange([
                                            ...values,
                                            String(lecturer.id),
                                        ]);
                                        setQuery('');
                                    }}
                                />
                            ))
                        ) : !loading ? (
                            <p className="text-center text-xs text-muted-foreground">
                                Tidak ada dosen yang cocok.
                            </p>
                        ) : null}
                    </div>
                ) : null}
            </div>
            {query.trim().length < 2 ? (
                <p className="text-[11px] text-muted-foreground">
                    Hasil baru dimuat setelah minimal 2 karakter.
                </p>
            ) : null}
            {error ? <p className="text-xs text-destructive">{error}</p> : null}
        </div>
    );
}
