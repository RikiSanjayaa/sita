import { Head } from '@inertiajs/react';
import {
    Bell,
    CalendarClock,
    CheckCircle2,
    FileText,
    Info,
    Megaphone,
    MessageSquareText,
    Timer,
} from 'lucide-react';
import { useMemo, useState } from 'react';

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
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { usePrimaryColor } from '@/hooks/use-primary-color';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { dashboard, settingNotifikasi } from '@/routes';
import { type BreadcrumbItem } from '@/types';

type NotificationSettings = {
    browserNotifications: boolean;
    pesanBaru: boolean;
    statusTugasAkhir: boolean;
    jadwalBimbingan: boolean;
    feedbackDokumen: boolean;
    reminderDeadline: boolean;
    pengumumanSistem: boolean;
    konfirmasiBimbingan: boolean;
};

type JenisNotifikasiItem = {
    key: Exclude<keyof NotificationSettings, 'browserNotifications'>;
    title: string;
    description: string;
    icon: typeof Bell;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Settings',
        href: settingNotifikasi().url,
    },
];

export default function SettingNotifikasi() {
    const { presetId, presets, updatePreset, resetPreset } = usePrimaryColor();
    const [settings, setSettings] = useState<NotificationSettings>(() => ({
        browserNotifications: true,
        pesanBaru: true,
        statusTugasAkhir: true,
        jadwalBimbingan: true,
        feedbackDokumen: true,
        reminderDeadline: true,
        pengumumanSistem: true,
        konfirmasiBimbingan: true,
    }));

    const items: JenisNotifikasiItem[] = useMemo(
        () => [
            {
                key: 'pesanBaru',
                title: 'Pesan Baru',
                description:
                    'Notifikasi saat ada pesan baru dari pembimbing atau penguji',
                icon: MessageSquareText,
            },
            {
                key: 'statusTugasAkhir',
                title: 'Update Status Tugas Akhir',
                description:
                    'Notifikasi saat status pengajuan judul atau proposal berubah',
                icon: FileText,
            },
            {
                key: 'jadwalBimbingan',
                title: 'Jadwal Bimbingan',
                description:
                    'Reminder 1 hari dan 1 jam sebelum jadwal bimbingan',
                icon: CalendarClock,
            },
            {
                key: 'feedbackDokumen',
                title: 'Feedback Dokumen',
                description:
                    'Notifikasi saat ada feedback atau revisi dokumen tersedia',
                icon: Info,
            },
            {
                key: 'reminderDeadline',
                title: 'Reminder Deadline',
                description:
                    'Pengingat untuk deadline proposal, sidang, dan submission',
                icon: Timer,
            },
            {
                key: 'pengumumanSistem',
                title: 'Pengumuman Sistem',
                description:
                    'Notifikasi pengumuman penting dari admin atau koordinator',
                icon: Megaphone,
            },
            {
                key: 'konfirmasiBimbingan',
                title: 'Konfirmasi Bimbingan',
                description:
                    'Notifikasi saat jadwal bimbingan dikonfirmasi atau dibatalkan',
                icon: CheckCircle2,
            },
        ],
        [],
    );

    const enabledJenisCount = useMemo(
        () => items.filter((i) => settings[i.key]).length,
        [items, settings],
    );

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Settings"
            subtitle="Kelola preferensi tema dan notifikasi"
        >
            <Head title="Settings" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6">
                <div>
                    <h1 className="text-xl font-semibold">Settings</h1>
                    <p className="text-sm text-muted-foreground">
                        Kelola preferensi tema dan notifikasi akun Anda
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div className="space-y-1">
                                <CardTitle>Tema</CardTitle>
                                <CardDescription>
                                    Pilih tampilan aplikasi sesuai preferensi
                                    Anda
                                </CardDescription>
                            </div>
                            <AppearanceTabs className="self-start" />
                        </div>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader className="gap-1">
                        <CardTitle>Preset Warna</CardTitle>
                        <CardDescription>
                            Pilih preset agar warna primary dan background
                            berubah seragam di seluruh aplikasi
                        </CardDescription>
                    </CardHeader>
                    <Separator />
                    <CardContent className="grid gap-4 pt-6">
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
                                            {isActive && (
                                                <Badge
                                                    variant="secondary"
                                                    className="shrink-0"
                                                >
                                                    Aktif
                                                </Badge>
                                            )}
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
                                                    backgroundColor:
                                                        preset.primary,
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
                            Preset tersimpan di browser dan akan tetap dipakai
                            saat Anda membuka ulang aplikasi.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="gap-1">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <CardTitle>Notifikasi Browser</CardTitle>
                                <CardDescription>
                                    Aktifkan notifikasi desktop untuk
                                    mendapatkan pemberitahuan real-time
                                </CardDescription>
                            </div>
                            <Badge variant="secondary" className="mt-0.5">
                                Disarankan
                            </Badge>
                        </div>
                    </CardHeader>

                    <Separator />

                    <CardContent className="pt-6">
                        <div className="flex items-start justify-between gap-6 rounded-xl border bg-background p-4">
                            <div className="min-w-0">
                                <div className="text-sm font-medium">
                                    Aktifkan Notifikasi Browser
                                </div>
                                <div className="mt-1 text-sm text-muted-foreground">
                                    Terima notifikasi meskipun browser tidak
                                    aktif
                                </div>
                                <div className="mt-3 text-xs text-muted-foreground">
                                    Jika belum diizinkan, browser akan meminta
                                    izin saat fitur ini diaktifkan.
                                </div>
                            </div>
                            <Switch
                                checked={settings.browserNotifications}
                                onCheckedChange={(checked) =>
                                    setSettings((prev) => ({
                                        ...prev,
                                        browserNotifications: checked,
                                    }))
                                }
                                aria-label="Aktifkan Notifikasi Browser"
                                className="mt-1"
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="gap-1">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <CardTitle>Jenis Notifikasi</CardTitle>
                                <CardDescription>
                                    Pilih jenis notifikasi yang ingin Anda
                                    terima
                                </CardDescription>
                            </div>
                            <Badge variant="outline" className="mt-0.5">
                                {enabledJenisCount}/{items.length} aktif
                            </Badge>
                        </div>
                    </CardHeader>

                    <Separator />

                    <CardContent className="pt-2">
                        <div className="divide-y">
                            {items.map((item) => {
                                const Icon = item.icon;
                                const checked = settings[item.key];
                                return (
                                    <div
                                        key={item.key}
                                        className="flex items-start justify-between gap-6 py-4"
                                    >
                                        <div className="flex min-w-0 items-start gap-3">
                                            <span
                                                className={`mt-0.5 inline-flex size-10 shrink-0 items-center justify-center rounded-lg ${
                                                    settings.browserNotifications
                                                        ? 'bg-muted text-muted-foreground'
                                                        : 'bg-muted/50 text-muted-foreground/50'
                                                }`}
                                            >
                                                <Icon className="size-4" />
                                            </span>
                                            <div
                                                className={`min-w-0 ${
                                                    settings.browserNotifications
                                                        ? ''
                                                        : 'opacity-50'
                                                }`}
                                            >
                                                <div className="text-sm font-medium">
                                                    {item.title}
                                                </div>
                                                <div className="mt-1 text-sm text-muted-foreground">
                                                    {item.description}
                                                </div>
                                            </div>
                                        </div>

                                        <Switch
                                            checked={checked}
                                            onCheckedChange={(next) =>
                                                setSettings((prev) => ({
                                                    ...prev,
                                                    [item.key]: next,
                                                }))
                                            }
                                            aria-label={`Aktifkan ${item.title}`}
                                            className="mt-1"
                                            disabled={
                                                !settings.browserNotifications
                                            }
                                        />
                                    </div>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
