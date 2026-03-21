import { Head, Link, router, usePage } from '@inertiajs/react';
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
import { useEffect, useMemo, useState } from 'react';

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
import { Switch } from '@/components/ui/switch';
import { usePrimaryColor } from '@/hooks/use-primary-color';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { dashboard, settingNotifikasi } from '@/routes';
import {
    type BreadcrumbItem,
    type NotificationSettings,
    type SharedData,
} from '@/types';

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
    const { auth, notificationSettings } = usePage<SharedData>().props;
    const { presetId, presets, updatePreset, resetPreset } = usePrimaryColor();
    const [isSaving, setIsSaving] = useState(false);
    const canAccessCsat =
        auth.activeRole === 'mahasiswa' || auth.activeRole === 'dosen';
    const [browserPermission, setBrowserPermission] = useState<
        NotificationPermission | 'unsupported'
    >(() => {
        if (typeof window === 'undefined' || !('Notification' in window)) {
            return 'unsupported';
        }

        return window.Notification.permission;
    });

    const [settings, setSettings] = useState<NotificationSettings>(() => ({
        browserNotifications:
            notificationSettings?.browserNotifications ?? false,
        pesanBaru: notificationSettings?.pesanBaru ?? true,
        statusTugasAkhir: notificationSettings?.statusTugasAkhir ?? true,
        jadwalBimbingan: notificationSettings?.jadwalBimbingan ?? true,
        feedbackDokumen: notificationSettings?.feedbackDokumen ?? true,
        reminderDeadline: notificationSettings?.reminderDeadline ?? true,
        pengumumanSistem: notificationSettings?.pengumumanSistem ?? true,
        konfirmasiBimbingan: notificationSettings?.konfirmasiBimbingan ?? true,
    }));

    const persistSettings = (nextSettings: NotificationSettings) => {
        setIsSaving(true);

        const payload: Record<string, boolean> = {
            ...nextSettings,
        };

        router.patch('/settings/notifications', payload, {
            preserveScroll: true,
            onFinish: () => setIsSaving(false),
        });
    };

    const setAndPersistSettings = (nextSettings: NotificationSettings) => {
        setSettings(nextSettings);
        persistSettings(nextSettings);
    };

    useEffect(() => {
        if (typeof window === 'undefined' || !('Notification' in window)) {
            return;
        }

        const syncPermission = () => {
            setBrowserPermission(window.Notification.permission);
        };

        window.addEventListener('focus', syncPermission);
        document.addEventListener('visibilitychange', syncPermission);

        return () => {
            window.removeEventListener('focus', syncPermission);
            document.removeEventListener('visibilitychange', syncPermission);
        };
    }, []);

    const requestBrowserPermission = async () => {
        if (typeof window === 'undefined' || !('Notification' in window)) {
            setBrowserPermission('unsupported');

            return;
        }

        const permission = await window.Notification.requestPermission();
        setBrowserPermission(permission);

        if (permission === 'granted' && !settings.browserNotifications) {
            setAndPersistSettings({
                ...settings,
                browserNotifications: true,
            });
        }
    };

    const handleBrowserNotificationToggle = async (checked: boolean) => {
        if (!checked) {
            setAndPersistSettings({
                ...settings,
                browserNotifications: false,
            });

            return;
        }

        if (browserPermission === 'unsupported') {
            return;
        }

        if (browserPermission === 'denied') {
            return;
        }

        if (browserPermission === 'default') {
            const permission = await window.Notification.requestPermission();
            setBrowserPermission(permission);

            if (permission !== 'granted') {
                setAndPersistSettings({
                    ...settings,
                    browserNotifications: false,
                });

                return;
            }
        }

        setAndPersistSettings({
            ...settings,
            browserNotifications: true,
        });
    };

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
                title: 'Update Status Skripsi',
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
                    <p className="max-w-lg text-sm leading-5 text-muted-foreground sm:leading-6">
                        Kelola tema, preset warna, dan notifikasi akun.
                    </p>
                </div>

                <Card className="overflow-hidden py-0 shadow-sm">
                    <CardHeader className="border-b bg-muted/20 px-6 py-4">
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

                <Card className="overflow-hidden py-0 shadow-sm">
                    <CardHeader className="border-b bg-muted/20 px-6 py-4">
                        <CardTitle>Preset Warna</CardTitle>
                        <CardDescription>
                            Pilih preset agar warna primary dan background
                            berubah seragam di seluruh aplikasi
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

                {canAccessCsat ? (
                    <Card className="overflow-hidden py-0 shadow-sm">
                        <CardHeader className="border-b bg-muted/20 px-6 py-4">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div className="min-w-0 space-y-1">
                                    <CardTitle>CSAT & Umpan Balik</CardTitle>
                                    <CardDescription className="max-w-xl leading-6">
                                        Nilai pengalaman Anda menggunakan SiTA
                                        dan kirimkan kritik atau saran penting
                                        yang dapat dibaca oleh admin.
                                    </CardDescription>
                                </div>
                                <Badge
                                    variant="outline"
                                    className="w-fit max-w-full self-start"
                                >
                                    Limit: 1 submission tiap 30 hari
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent className="pb-6">
                            <div className="flex flex-col gap-4 rounded-xl border bg-background p-4 md:flex-row md:items-center md:justify-between">
                                <div className="min-w-0">
                                    <div className="text-sm font-medium">
                                        Bantu kami membaca kualitas pengalaman
                                        Anda
                                    </div>
                                </div>
                                <Button asChild>
                                    <Link href="/settings/csat">Isi CSAT</Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                ) : null}

                <Card className="overflow-hidden py-0 shadow-sm">
                    <CardHeader className="border-b bg-muted/20 px-6 py-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div className="min-w-0 space-y-1">
                                <CardTitle>Notifikasi Browser</CardTitle>
                                <CardDescription className="max-w-xl leading-6">
                                    Aktifkan notifikasi desktop untuk
                                    mendapatkan pemberitahuan real-time
                                </CardDescription>
                            </div>
                            <div className="flex flex-wrap items-center gap-2 self-start">
                                <Badge variant="secondary">Disarankan</Badge>
                                <Badge variant="outline" className="capitalize">
                                    {browserPermission === 'unsupported'
                                        ? 'tidak didukung'
                                        : browserPermission}
                                </Badge>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent className="pb-6">
                        <div className="flex flex-col gap-4 rounded-xl border bg-background p-4 sm:flex-row sm:items-start sm:justify-between">
                            <div className="min-w-0">
                                <div className="text-sm font-medium">
                                    Aktifkan Notifikasi Browser
                                </div>
                                <div className="mt-1 text-sm text-muted-foreground">
                                    Terima notifikasi meskipun browser tidak
                                    aktif
                                </div>
                                <div className="mt-3 text-xs text-muted-foreground">
                                    {browserPermission === 'denied'
                                        ? 'Izin notifikasi ditolak di browser. Ubah melalui pengaturan browser untuk mengaktifkan kembali.'
                                        : 'Jika belum diizinkan, browser akan meminta izin saat fitur ini diaktifkan.'}
                                </div>
                                {(browserPermission === 'default' ||
                                    browserPermission === 'denied') && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="mt-3"
                                        onClick={requestBrowserPermission}
                                        disabled={isSaving}
                                    >
                                        {browserPermission === 'default'
                                            ? 'Minta Izin Notifikasi'
                                            : 'Coba Minta Izin Lagi'}
                                    </Button>
                                )}
                            </div>
                            <Switch
                                checked={settings.browserNotifications}
                                onCheckedChange={
                                    handleBrowserNotificationToggle
                                }
                                aria-label="Aktifkan Notifikasi Browser"
                                className="self-start sm:mt-1"
                                disabled={
                                    browserPermission === 'unsupported' ||
                                    browserPermission === 'denied' ||
                                    isSaving
                                }
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card className="overflow-hidden py-0 shadow-sm">
                    <CardHeader className="border-b bg-muted/20 px-6 py-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div className="min-w-0 space-y-1">
                                <CardTitle>Jenis Notifikasi</CardTitle>
                                <CardDescription className="max-w-xl leading-6">
                                    Pilih jenis notifikasi yang ingin Anda
                                    terima
                                </CardDescription>
                            </div>
                            <Badge
                                variant="outline"
                                className="w-fit self-start"
                            >
                                {enabledJenisCount}/{items.length} aktif
                            </Badge>
                        </div>
                    </CardHeader>

                    <CardContent className="pb-6">
                        <div className="divide-y">
                            {items.map((item) => {
                                const Icon = item.icon;
                                const checked = settings[item.key];
                                return (
                                    <div
                                        key={item.key}
                                        className="flex flex-col gap-4 py-4 sm:flex-row sm:items-start sm:justify-between"
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
                                                setAndPersistSettings({
                                                    ...settings,
                                                    [item.key]: next,
                                                })
                                            }
                                            aria-label={`Aktifkan ${item.title}`}
                                            className="self-start sm:mt-1"
                                            disabled={
                                                !settings.browserNotifications ||
                                                isSaving
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
