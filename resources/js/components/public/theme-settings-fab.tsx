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

            <DialogContent className="top-0 left-0 h-dvh max-w-none translate-x-0 translate-y-0 rounded-none border-0 p-0 sm:top-[50%] sm:left-[50%] sm:h-auto sm:max-h-[85vh] sm:w-full sm:max-w-2xl sm:translate-x-[-50%] sm:translate-y-[-50%] sm:rounded-lg sm:border sm:p-6">
                <div className="flex h-full flex-col overflow-hidden sm:block">
                    <DialogHeader>
                        <div className="border-b px-5 pt-12 pb-4 sm:border-none sm:px-0 sm:pt-0 sm:pb-0">
                            <DialogTitle>Pengaturan Tampilan</DialogTitle>
                            <DialogDescription>
                                Ubah mode terang-gelap dan preset warna tanpa
                                mengganggu fokus halaman publik.
                            </DialogDescription>
                        </div>
                    </DialogHeader>

                    <div className="flex-1 space-y-6 overflow-y-auto px-5 py-5 sm:max-h-[70vh] sm:px-0 sm:py-0">
                        <section className="space-y-3">
                            <div>
                                <p className="text-sm font-semibold">
                                    Mode Tampilan
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Pilih light, dark, atau ikuti sistem
                                    perangkat.
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
                                    Terapkan skema warna yang konsisten di
                                    landing page dan area aplikasi lain.
                                </p>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                {THEME_PRESETS.map((preset) => {
                                    const isActive = preset.id === presetId;

                                    return (
                                        <button
                                            key={preset.id}
                                            type="button"
                                            onClick={() =>
                                                updatePreset(preset.id)
                                            }
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
                                                            preset.light
                                                                .background,
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
                </div>
            </DialogContent>
        </Dialog>
    );
}
