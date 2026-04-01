import { router, usePage } from '@inertiajs/react';
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

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { type NotificationSettings, type SharedData } from '@/types';

type JenisNotifikasiItem = {
    key: Exclude<keyof NotificationSettings, 'browserNotifications'>;
    title: string;
    description: string;
    icon: typeof Bell;
};

export default function NotificationSettingsPanel() {
    const { notificationSettings } = usePage<SharedData>().props;
    const [isSaving, setIsSaving] = useState(false);
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

        router.patch(
            '/settings/notifications',
            { ...nextSettings },
            {
                preserveScroll: true,
                onFinish: () => setIsSaving(false),
            },
        );
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

        if (
            browserPermission === 'unsupported' ||
            browserPermission === 'denied'
        ) {
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
                title: 'Pesan baru',
                description:
                    'Notifikasi saat ada pesan baru dari pembimbing atau penguji.',
                icon: MessageSquareText,
            },
            {
                key: 'statusTugasAkhir',
                title: 'Update status skripsi',
                description:
                    'Notifikasi saat status judul, sempro, sidang, atau pembimbing berubah.',
                icon: FileText,
            },
            {
                key: 'jadwalBimbingan',
                title: 'Jadwal bimbingan',
                description:
                    'Reminder 1 hari dan 1 jam sebelum jadwal bimbingan.',
                icon: CalendarClock,
            },
            {
                key: 'feedbackDokumen',
                title: 'Feedback dokumen',
                description:
                    'Notifikasi saat ada feedback atau revisi dokumen tersedia.',
                icon: Info,
            },
            {
                key: 'reminderDeadline',
                title: 'Reminder deadline',
                description:
                    'Pengingat untuk deadline proposal, sidang, dan submission.',
                icon: Timer,
            },
            {
                key: 'pengumumanSistem',
                title: 'Pengumuman sistem',
                description:
                    'Notifikasi pengumuman penting dari admin atau koordinator.',
                icon: Megaphone,
            },
            {
                key: 'konfirmasiBimbingan',
                title: 'Konfirmasi bimbingan',
                description:
                    'Notifikasi saat jadwal bimbingan dikonfirmasi atau dibatalkan.',
                icon: CheckCircle2,
            },
        ],
        [],
    );

    const enabledJenisCount = useMemo(
        () => items.filter((item) => settings[item.key]).length,
        [items, settings],
    );

    return (
        <div className="space-y-8">
            {/* Notifikasi browser section */}
            <div className="space-y-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 className="text-lg font-semibold">
                            Notifikasi browser
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Aktifkan notifikasi desktop untuk mendapatkan
                            pemberitahuan real-time.
                        </p>
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

                <div className="flex flex-col gap-4 rounded-xl border bg-muted/10 p-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0">
                        <div className="text-sm font-medium">
                            Aktifkan notifikasi browser
                        </div>
                        <div className="mt-1 text-sm text-muted-foreground">
                            Terima notifikasi meskipun browser tidak aktif.
                        </div>
                        <div className="mt-3 text-xs text-muted-foreground">
                            {browserPermission === 'denied'
                                ? 'Izin notifikasi ditolak di browser. Ubah melalui pengaturan browser untuk mengaktifkan kembali.'
                                : 'Jika belum diizinkan, browser akan meminta izin saat fitur ini diaktifkan.'}
                        </div>

                        {browserPermission === 'default' ||
                        browserPermission === 'denied' ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="mt-3"
                                onClick={requestBrowserPermission}
                                disabled={isSaving}
                            >
                                {browserPermission === 'default'
                                    ? 'Minta izin notifikasi'
                                    : 'Coba minta izin lagi'}
                            </Button>
                        ) : null}
                    </div>

                    <Switch
                        checked={settings.browserNotifications}
                        onCheckedChange={handleBrowserNotificationToggle}
                        aria-label="Aktifkan notifikasi browser"
                        className="self-start sm:mt-1"
                        disabled={
                            browserPermission === 'unsupported' ||
                            browserPermission === 'denied' ||
                            isSaving
                        }
                    />
                </div>
            </div>

            <Separator />

            {/* Jenis notifikasi section */}
            <div className="space-y-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 className="text-lg font-semibold">
                            Jenis notifikasi
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Pilih jenis notifikasi yang ingin Anda terima.
                        </p>
                    </div>
                    <Badge variant="outline" className="w-fit self-start">
                        {enabledJenisCount}/{items.length} aktif
                    </Badge>
                </div>

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
            </div>
        </div>
    );
}
