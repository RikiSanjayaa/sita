import { useId } from 'react';

import AppearanceTabs from '@/components/appearance-tabs';
import {
    THEME_SCHEMES,
    useThemeScheme,
    type ThemeSchemeId,
} from '@/hooks/use-theme-scheme';
import { cn } from '@/lib/utils';

type ThemePickerProps = {
    /** Heading level of the two section titles; pick the one below the host's own heading. */
    headingLevel?: 'h2' | 'h3';
    className?: string;
};

// A swatch carries its own `data-theme` (and `dark` for the dark half), so it reads the
// scheme's tokens straight from app.css. It uses `var(--token)` rather than the `bg-*`
// utilities because those resolve `--color-*` once at the root and would show the
// page's scheme instead.
function SchemeSwatch({ id }: { id: ThemeSchemeId }) {
    return (
        <div
            className="flex overflow-hidden rounded-md border border-border"
            aria-hidden
        >
            {(['light', 'dark'] as const).map((mode) => (
                <div
                    key={mode}
                    data-theme={id}
                    className={cn(
                        'flex flex-1 items-center gap-1.5 bg-(--background) p-2',
                        mode === 'dark' && 'dark',
                    )}
                >
                    <span className="size-4 shrink-0 rounded-full bg-(--primary)" />
                    <span className="size-4 shrink-0 rounded-full border border-(--border) bg-(--primary-muted)" />
                    <span className="h-2 flex-1 rounded-full bg-(--muted-foreground)" />
                </div>
            ))}
        </div>
    );
}

export function ThemePicker({
    headingLevel: Heading = 'h2',
    className,
}: ThemePickerProps) {
    const { schemeId, updateScheme } = useThemeScheme();
    const modeHeadingId = useId();
    const schemeHeadingId = useId();

    return (
        <div className={cn('space-y-8', className)}>
            <section aria-labelledby={modeHeadingId} className="space-y-3">
                <div>
                    <Heading
                        id={modeHeadingId}
                        className="text-lg font-semibold"
                    >
                        Mode tampilan
                    </Heading>
                    <p className="mt-1 text-sm text-pretty text-muted-foreground">
                        Pilih terang, gelap, atau ikuti sistem perangkat.
                    </p>
                </div>
                <AppearanceTabs className="self-start" />
            </section>

            <section aria-labelledby={schemeHeadingId} className="space-y-3">
                <div>
                    <Heading
                        id={schemeHeadingId}
                        className="text-lg font-semibold"
                    >
                        Skema warna
                    </Heading>
                    <p className="mt-1 text-sm text-pretty text-muted-foreground">
                        Warna utama dan nuansa antarmuka berubah seragam di
                        seluruh aplikasi, juga di halaman masuk. Pratinjau
                        menampilkan mode terang dan gelap.
                    </p>
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                    {THEME_SCHEMES.map((scheme) => (
                        <button
                            key={scheme.id}
                            type="button"
                            aria-pressed={scheme.id === schemeId}
                            onClick={() => updateScheme(scheme.id)}
                            className={cn(
                                'flex flex-col gap-3 rounded-xl border bg-card p-4 text-left transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none max-md:min-h-11',
                                scheme.id === schemeId
                                    ? 'border-primary ring-2 ring-primary'
                                    : 'hover:bg-muted/60',
                            )}
                        >
                            <SchemeSwatch id={scheme.id} />
                            <span>
                                <span className="block text-sm font-semibold">
                                    {scheme.label}
                                </span>
                                <span className="mt-1 block text-xs text-muted-foreground">
                                    {scheme.description}
                                </span>
                            </span>
                        </button>
                    ))}
                </div>
            </section>
        </div>
    );
}
