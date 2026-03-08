import { Settings2 } from 'lucide-react';

import AppearanceTabs from '@/components/appearance-tabs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { THEME_PRESETS, usePrimaryColor } from '@/hooks/use-primary-color';
import { cn } from '@/lib/utils';

export function ThemeSettingsFab() {
    const { presetId, resetPreset, updatePreset } = usePrimaryColor();

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    size="icon"
                    className="fixed right-5 bottom-5 z-50 size-12 rounded-full shadow-lg"
                    aria-label="Buka pengaturan tampilan"
                >
                    <Settings2 className="size-5" />
                </Button>
            </DialogTrigger>

            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Pengaturan Tampilan</DialogTitle>
                    <DialogDescription>
                        Ubah mode terang-gelap dan preset warna tanpa mengganggu
                        fokus halaman publik.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-6">
                    <section className="space-y-3">
                        <div>
                            <p className="text-sm font-semibold">
                                Mode Tampilan
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Pilih light, dark, atau ikuti sistem perangkat.
                            </p>
                        </div>
                        <AppearanceTabs className="self-start" />
                    </section>

                    <section className="space-y-3">
                        <div>
                            <p className="text-sm font-semibold">
                                Preset Warna
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Terapkan skema warna yang konsisten di landing
                                page dan area aplikasi lain.
                            </p>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            {THEME_PRESETS.map((preset) => {
                                const isActive = preset.id === presetId;

                                return (
                                    <button
                                        key={preset.id}
                                        type="button"
                                        onClick={() => updatePreset(preset.id)}
                                        className={cn(
                                            'rounded-2xl border p-4 text-left transition-colors',
                                            isActive
                                                ? 'border-primary bg-primary/10'
                                                : 'hover:bg-muted/60',
                                        )}
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="text-sm font-semibold">
                                                    {preset.label}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {preset.description}
                                                </p>
                                            </div>
                                            {isActive ? (
                                                <Badge variant="secondary">
                                                    Aktif
                                                </Badge>
                                            ) : null}
                                        </div>

                                        <div className="mt-4 flex items-center gap-2">
                                            <span
                                                className="size-5 rounded-full border"
                                                style={{
                                                    backgroundColor:
                                                        preset.light.background,
                                                }}
                                                aria-hidden
                                            />
                                            <span
                                                className="size-5 rounded-full border"
                                                style={{
                                                    backgroundColor:
                                                        preset.primary,
                                                }}
                                                aria-hidden
                                            />
                                            <span
                                                className="size-5 rounded-full border"
                                                style={{
                                                    backgroundColor:
                                                        preset.light.accent,
                                                }}
                                                aria-hidden
                                            />
                                        </div>
                                    </button>
                                );
                            })}
                        </div>

                        <div className="flex justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={resetPreset}
                            >
                                Reset ke Default UBG
                            </Button>
                        </div>
                    </section>
                </div>
            </DialogContent>
        </Dialog>
    );
}
