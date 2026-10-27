import { usePage } from '@inertiajs/react';
import {
    Bell,
    CalendarClock,
    CheckCircle2,
    FileText,
    Info,
    Megaphone,
    MessageSquareText,
    Timer,
    TriangleAlert,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';

import NotificationSettingsController from '@/actions/App/Http/Controllers/Settings/NotificationSettingsController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { neutralAcademicTerminology as n } from '@/lib/academic-terminology';
import {
    disablePush,
    enablePush,
    PushError,
    type PushState,
    type PushStatus,
    readPushStatus,
} from '@/lib/web-push';
import { type NotificationSettings, type SharedData } from '@/types';

type JenisNotifikasiItem = {
    key: Exclude<keyof NotificationSettings, 'browserNotifications'>;
    title: string;
    description: string;
    icon: typeof Bell;
};

const stateLabels: Record<PushState, string> = {
    loading: 'Memeriksa',
    unsupported: 'Tidak didukung',
    insecure: 'Butuh HTTPS',
    'ios-install': 'Pasang di Layar Utama',
    unavailable: 'Belum disiapkan',
    denied: 'Izin ditolak',
    inactive: 'Belum aktif',
    active: 'Aktif',
    error: 'Gagal membaca status',
};

function deviceDescription(
    status: PushStatus,
    accountEnabled: boolean,
): string {
    switch (status.state) {
        case 'loading':
            return 'Memeriksa izin, langganan browser, dan data di server.';
        case 'unsupported':
            return 'Browser ini tidak mendukung notifikasi push. Coba browser lain atau pasang SiTA di perangkat yang mendukung.';
        case 'insecure':
            return 'Notifikasi push hanya jalan lewat HTTPS. Buka SiTA dengan alamat https.';
        case 'ios-install':
            return 'Di iPhone dan iPad, tambahkan SiTA ke Layar Utama lewat menu Bagikan, lalu buka dari ikonnya dan aktifkan notifikasi di sini.';
        case 'unavailable':
            return 'Server belum diatur untuk mengirim notifikasi push. Hubungi admin SiTA.';
        case 'denied':
            return 'Izin notifikasi ditolak di browser. Ubah izin situs lewat pengaturan browser, lalu kembali ke halaman ini.';
        case 'error':
            return (
                status.message ??
                'Status notifikasi perangkat ini tidak bisa dibaca.'
            );
        case 'active':
            return accountEnabled
                ? 'Perangkat terhubung. Notifikasi dapat masuk saat tab ditutup jika browser atau layanan push sistem berjalan.'
                : 'Perangkat ini terhubung, tetapi notifikasi browser akun dimatikan, jadi belum ada yang dikirim.';
        case 'inactive':
            return status.hasLocalSubscription
                ? 'Browser masih menyimpan langganan, tetapi server tidak mengenalinya untuk akun dan sesi ini. Aktifkan ulang untuk menghubungkannya.'
                : status.permission === 'granted'
                  ? 'Izin sudah diberikan, tetapi perangkat ini belum terhubung ke akun Anda.'
                  : 'Browser akan meminta izin saat Anda mengaktifkan notifikasi di perangkat ini.';
    }
}

export default function NotificationSettingsPanel() {
    const {
        auth,
        notificationSettings,
        academicTerminology: terms,
    } = usePage<SharedData>().props;
    const userId = auth.user.id;
    const [isSaving, setIsSaving] = useState(false);
    const [deviceStatus, setDeviceStatus] = useState<PushStatus>({
        state: 'loading',
        permission: 'default',
        hasLocalSubscription: false,
    });
    const [isDeviceBusy, setIsDeviceBusy] = useState(false);
    const [deviceError, setDeviceError] = useState<string | null>(null);
    const statusRequest = useRef(0);
    const deviceAction = useRef(false);

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

    const setAndPersistSettings = async (
        nextSettings: NotificationSettings,
    ): Promise<void> => {
        setIsSaving(true);
        try {
            let response: Response;
            try {
                response = await fetch(
                    NotificationSettingsController.update.url(),
                    {
                        method: 'PATCH',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN':
                                document
                                    .querySelector('meta[name="csrf-token"]')
                                    ?.getAttribute('content') ?? '',
                        },
                        body: JSON.stringify(nextSettings),
                    },
                );
            } catch {
                throw new PushError(
                    'Pengiriman akun belum tersimpan. Periksa koneksi lalu coba lagi.',
                    'account-settings: network',
                );
            }
            if (!response.ok) {
                throw new PushError(
                    'Pengiriman akun belum tersimpan. Coba lagi.',
                    `account-settings: HTTP ${response.status}`,
                );
            }
            setSettings(nextSettings);
        } finally {
            setIsSaving(false);
        }
    };

    // Read-only refresh: the latest request wins, so a slow answer cannot
    // overwrite a newer state.
    const refreshDeviceStatus = async () => {
        if (deviceAction.current) return;
        const request = ++statusRequest.current;
        const next = await readPushStatus(userId);

        if (request === statusRequest.current) {
            setDeviceStatus(next);
        }
    };

    useEffect(() => {
        const refresh = () => {
            if (document.visibilityState === 'visible') {
                void refreshDeviceStatus();
            }
        };

        refresh();
        window.addEventListener('focus', refresh);
        document.addEventListener('visibilitychange', refresh);

        return () => {
            window.removeEventListener('focus', refresh);
            document.removeEventListener('visibilitychange', refresh);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [userId]);

    // Runs from a click handler. `enablePush` asks for permission before its
    // first await, which iOS requires, so nothing may be awaited before it.
    const runDeviceAction = async (
        action: typeof enablePush | typeof disablePush,
        successMessage: string,
    ) => {
        if (deviceAction.current) return;
        deviceAction.current = true;
        ++statusRequest.current;

        setIsDeviceBusy(true);
        setDeviceError(null);

        try {
            const next = await action(userId);
            if (
                action === enablePush &&
                next.state === 'active' &&
                !settings.browserNotifications
            ) {
                await setAndPersistSettings({
                    ...settings,
                    browserNotifications: true,
                });
            }

            ++statusRequest.current;
            setDeviceStatus(next);

            if (
                (action === enablePush && next.state === 'active') ||
                (action === disablePush && next.state === 'inactive')
            ) {
                toast.success(successMessage);
            } else {
                setDeviceError(
                    next.message ??
                        'Aktivasi belum dapat dipastikan. Coba lagi.',
                );
            }
        } catch (error) {
            setDeviceError(
                error instanceof PushError
                    ? error.message
                    : 'Terjadi kesalahan. Coba lagi.',
            );
            const next = await readPushStatus(userId);
            ++statusRequest.current;
            setDeviceStatus(next);
        } finally {
            deviceAction.current = false;
            setIsDeviceBusy(false);
        }
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
                title: `Update status ${terms.finalWorkLower}`,
                description: `Notifikasi saat status judul, ${n.proposalDocument.toLowerCase()}, ${n.finalExam.toLowerCase()}, atau pembimbing berubah.`,
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
                description: `Pengingat untuk deadline ${n.proposalDocument.toLowerCase()}, ${n.finalExam.toLowerCase()}, dan submission.`,
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
        [terms.finalWorkLower],
    );

    const enabledJenisCount = useMemo(
        () => items.filter((item) => settings[item.key]).length,
        [items, settings],
    );

    const canEnableDevice =
        deviceStatus.state === 'inactive' ||
        deviceStatus.state === 'error' ||
        (deviceStatus.state === 'active' && !settings.browserNotifications);
    const isLoading = deviceStatus.state === 'loading';

    return (
        <div className="space-y-8">
            <div className="space-y-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 className="text-lg font-semibold">
                            Notifikasi browser
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Pengaturan akun. Berlaku untuk semua perangkat yang
                            Anda hubungkan.
                        </p>
                    </div>
                </div>

                <div className="flex flex-col gap-4 rounded-xl border bg-muted/10 p-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0">
                        <label
                            htmlFor="browser-notifications"
                            className="text-sm font-medium"
                        >
                            Kirim ke semua perangkat terhubung
                        </label>
                        <div className="mt-1 text-sm text-muted-foreground">
                            Jika dimatikan, tidak ada perangkat yang menerima
                            notifikasi. Perangkat tetap terhubung dan menerima
                            lagi saat ini dinyalakan.
                        </div>
                    </div>

                    <Switch
                        id="browser-notifications"
                        checked={settings.browserNotifications}
                        onCheckedChange={(checked) =>
                            void setAndPersistSettings({
                                ...settings,
                                browserNotifications: checked,
                            }).catch((error: unknown) =>
                                toast.error(
                                    error instanceof PushError
                                        ? error.message
                                        : 'Pengaturan belum tersimpan. Coba lagi.',
                                ),
                            )
                        }
                        aria-label="Kirim ke semua perangkat terhubung"
                        className="self-start sm:mt-1"
                        disabled={isSaving || isDeviceBusy}
                    />
                </div>

                <div className="space-y-3 rounded-xl border p-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div className="min-w-0">
                            <h3 className="text-sm font-medium">
                                Perangkat ini
                            </h3>
                            <p
                                className="mt-1 text-sm text-pretty text-muted-foreground"
                                aria-live="polite"
                            >
                                {deviceDescription(
                                    deviceStatus,
                                    settings.browserNotifications,
                                )}
                            </p>
                        </div>
                        <Badge variant="outline" className="w-fit self-start">
                            {isLoading ? <Spinner className="size-3" /> : null}
                            {deviceStatus.state === 'active' &&
                            !settings.browserNotifications
                                ? 'Terhubung, pengiriman dijeda'
                                : stateLabels[deviceStatus.state]}
                        </Badge>
                    </div>

                    {deviceError ? (
                        <Alert variant="destructive">
                            <TriangleAlert />
                            <AlertTitle>
                                Pengaturan perangkat belum selesai
                            </AlertTitle>
                            <AlertDescription>{deviceError}</AlertDescription>
                        </Alert>
                    ) : null}

                    {canEnableDevice ? (
                        <p className="text-sm text-muted-foreground">
                            Aktivasi menghubungkan perangkat ini dan menyalakan
                            pengiriman akun ke semua perangkat yang sudah
                            terhubung. Izin browser akan diminta jika
                            diperlukan.
                        </p>
                    ) : null}
                    <div className="flex flex-wrap gap-2">
                        {canEnableDevice ? (
                            <Button
                                type="button"
                                size="sm"
                                className="max-md:h-auto max-md:min-h-11 max-md:whitespace-normal"
                                onClick={() =>
                                    void runDeviceAction(
                                        enablePush,
                                        'Notifikasi aktif di perangkat ini.',
                                    )
                                }
                                disabled={isDeviceBusy || isSaving}
                            >
                                {isDeviceBusy ? <Spinner /> : null}
                                Aktifkan notifikasi di perangkat ini
                            </Button>
                        ) : null}

                        {deviceStatus.hasLocalSubscription ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="max-md:min-h-11"
                                onClick={() =>
                                    void runDeviceAction(
                                        disablePush,
                                        'Notifikasi dimatikan di perangkat ini.',
                                    )
                                }
                                disabled={isDeviceBusy || isSaving}
                            >
                                {isDeviceBusy ? <Spinner /> : null}
                                {deviceStatus.state === 'active'
                                    ? 'Matikan di perangkat ini'
                                    : 'Lepaskan langganan lama'}
                            </Button>
                        ) : null}

                        {deviceStatus.state === 'denied' ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="max-md:min-h-11"
                                onClick={() => void refreshDeviceStatus()}
                                disabled={isDeviceBusy}
                            >
                                Periksa izin lagi
                            </Button>
                        ) : null}
                    </div>
                </div>
            </div>

            <Separator />

            <div className="space-y-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 className="text-lg font-semibold">
                            Jenis notifikasi
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {settings.browserNotifications
                                ? 'Pilih jenis notifikasi yang ingin Anda terima.'
                                : 'Nyalakan notifikasi browser untuk memilih jenis notifikasi yang ingin Anda terima.'}
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
                                className="flex items-start justify-between gap-4 py-4"
                            >
                                <div className="flex min-w-0 items-start gap-3">
                                    <span className="mt-0.5 inline-flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary-muted text-primary-muted-foreground">
                                        <Icon className="size-4" />
                                    </span>

                                    <label
                                        htmlFor={`notification-${item.key}`}
                                        className="min-w-0"
                                    >
                                        <span className="block text-sm font-medium">
                                            {item.title}
                                        </span>
                                        <span className="mt-1 block text-sm text-pretty text-muted-foreground">
                                            {item.description}
                                        </span>
                                    </label>
                                </div>

                                <Switch
                                    id={`notification-${item.key}`}
                                    checked={checked}
                                    onCheckedChange={(next) =>
                                        void setAndPersistSettings({
                                            ...settings,
                                            [item.key]: next,
                                        }).catch((error: unknown) =>
                                            toast.error(
                                                error instanceof PushError
                                                    ? error.message
                                                    : 'Pengaturan belum tersimpan. Coba lagi.',
                                            ),
                                        )
                                    }
                                    aria-label={`Aktifkan ${item.title}`}
                                    className="mt-1 shrink-0"
                                    disabled={
                                        !settings.browserNotifications ||
                                        isSaving ||
                                        isDeviceBusy
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
