import AppearanceTabs from '@/components/appearance-tabs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { usePrimaryColor } from '@/hooks/use-primary-color';
import { cn } from '@/lib/utils';

export default function AppearanceSettingsPanel() {
    const { presetId, presets, updatePreset, resetPreset } = usePrimaryColor();

    return (
        <div className="space-y-6">
            <Card className="overflow-hidden py-0 shadow-sm">
                <CardHeader className="border-b bg-muted/20 px-6 py-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div className="space-y-1">
                            <CardTitle>Tema</CardTitle>
                            <CardDescription>
                                Pilih tampilan terang, gelap, atau ikuti sistem
                                perangkat Anda.
                            </CardDescription>
                        </div>
                        <AppearanceTabs className="self-start" />
                    </div>
                </CardHeader>
            </Card>

            <Card className="overflow-hidden py-0 shadow-sm">
                <CardHeader className="border-b bg-muted/20 px-6 py-4">
                    <CardTitle>Preset warna</CardTitle>
                    <CardDescription>
                        Pilih preset agar warna primary dan nuansa antarmuka
                        berubah seragam di seluruh aplikasi.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 pb-6">
                    <div className="grid gap-3 sm:grid-cols-2">
                        {presets.map((preset) => {
                            const isActive = preset.id === presetId;

                            return (
                                <button
                                    key={preset.id}
                                    type="button"
                                    onClick={() => updatePreset(preset.id)}
                                    className={cn(
                                        'rounded-xl border p-4 text-left transition-colors',
                                        isActive
                                            ? 'border-primary bg-primary/10'
                                            : 'hover:bg-muted/60',
                                    )}
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium">
                                                {preset.label}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {preset.description}
                                            </p>
                                        </div>

                                        {isActive ? (
                                            <Badge
                                                variant="secondary"
                                                className="shrink-0"
                                            >
                                                Aktif
                                            </Badge>
                                        ) : null}
                                    </div>

                                    <div className="mt-3 flex items-center gap-2">
                                        <span className="text-xs text-muted-foreground">
                                            Preview
                                        </span>
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
                                                backgroundColor: preset.primary,
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

                    <p className="text-xs text-muted-foreground">
                        Preset tersimpan di browser dan akan tetap dipakai saat
                        Anda membuka ulang aplikasi.
                    </p>
                </CardContent>
            </Card>
        </div>
    );
}
