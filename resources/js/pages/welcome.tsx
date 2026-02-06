import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarClock,
    FileText,
    MessageSquareText,
    Moon,
    Sun,
    type LucideIcon,
} from 'lucide-react';

import AppLogoIcon from '@/components/app-logo-icon';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useAppearance } from '@/hooks/use-appearance';
import { dashboard, home, login, register } from '@/routes';
import { type SharedData } from '@/types';

type InfoItem = {
    id: string;
    title: string;
    description: string;
    icon: LucideIcon;
};

const infoItems: InfoItem[] = [
    {
        id: 'jadwal',
        title: 'Pengajuan Jadwal',
        description:
            'Mahasiswa mengajukan jadwal bimbingan dan status persetujuan terlihat langsung.',
        icon: CalendarClock,
    },
    {
        id: 'dokumen',
        title: 'Unggah Dokumen',
        description:
            'File tugas akhir dikelola dalam versi yang rapi untuk memudahkan penelusuran revisi.',
        icon: FileText,
    },
    {
        id: 'komunikasi',
        title: 'Komunikasi Bimbingan',
        description:
            'Mahasiswa dan dosen pembimbing berdiskusi di satu ruang agar informasi tidak tercecer.',
        icon: MessageSquareText,
    },
];

const steps = [
    'Ajukan jadwal bimbingan sesuai kebutuhan.',
    'Unggah dokumen dan pantau catatan revisi.',
    'Finalisasi berkas hingga siap tahap berikutnya.',
];

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth } = usePage<SharedData>().props;
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const isAuthenticated = Boolean(auth.user);
    const isDark = resolvedAppearance === 'dark';

    return (
        <>
            <Head title="SiTA UBG | Portal Mahasiswa" />

            <div className="relative min-h-screen bg-background text-foreground">
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-0"
                >
                    <div className="absolute top-0 right-0 h-56 w-56 rounded-full bg-amber-200/25 blur-3xl dark:bg-amber-500/10" />
                </div>

                <header className="relative z-10 border-b bg-background/95 backdrop-blur">
                    <div className="mx-auto flex w-full max-w-5xl flex-wrap items-center justify-between gap-3 px-5 py-4">
                        <Link
                            href={home().url}
                            className="flex items-center gap-3"
                        >
                            <span className="flex size-10 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                <AppLogoIcon className="size-5" />
                            </span>
                            <span className="grid leading-tight">
                                <span className="text-sm font-semibold">
                                    SiTA
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    Universitas Bumigora
                                </span>
                            </span>
                        </Link>

                        <div className="flex flex-wrap items-center justify-end gap-2">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="border border-border bg-muted/50"
                                onClick={() =>
                                    updateAppearance(isDark ? 'light' : 'dark')
                                }
                                aria-label={
                                    isDark
                                        ? 'Aktifkan mode terang'
                                        : 'Aktifkan mode gelap'
                                }
                                title={
                                    isDark
                                        ? 'Aktifkan mode terang'
                                        : 'Aktifkan mode gelap'
                                }
                            >
                                {isDark ? (
                                    <Sun className="size-4" />
                                ) : (
                                    <Moon className="size-4" />
                                )}
                            </Button>
                            {isAuthenticated ? (
                                <Button asChild>
                                    <Link href={dashboard().url}>
                                        Dashboard
                                    </Link>
                                </Button>
                            ) : (
                                <>
                                    <Button asChild variant="ghost">
                                        <Link href={login().url}>Masuk</Link>
                                    </Button>
                                    {canRegister && (
                                        <Button asChild>
                                            <Link href={register().url}>
                                                Daftar
                                            </Link>
                                        </Button>
                                    )}
                                </>
                            )}
                        </div>
                    </div>
                </header>

                <main className="relative z-10 mx-auto w-full max-w-5xl px-5 py-10 md:py-14">
                    <section className="space-y-4">
                        <Badge variant="outline" className="w-fit">
                            Portal internal mahasiswa
                        </Badge>
                        <h1 className="max-w-3xl text-3xl font-semibold tracking-tight sm:text-4xl">
                            Sistem Informasi Tugas Akhir
                            <span className="block text-muted-foreground">
                                Universitas Bumigora.
                            </span>
                        </h1>
                        <p className="max-w-3xl text-sm leading-relaxed text-muted-foreground sm:text-base">
                            SiTA membantu proses bimbingan menjadi terstruktur
                            mulai dari pengajuan jadwal, unggah dokumen, hingga
                            komunikasi revisi dengan dosen pembimbing.
                        </p>
                        <div className="flex flex-wrap items-center gap-3 pt-1">
                            {isAuthenticated ? (
                                <Button asChild>
                                    <Link href={dashboard().url}>
                                        Lanjut ke dashboard
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                            ) : (
                                <>
                                    <Button asChild>
                                        <Link href={login().url}>
                                            Masuk ke SiTA
                                        </Link>
                                    </Button>
                                    {canRegister && (
                                        <Button asChild variant="secondary">
                                            <Link href={register().url}>
                                                Daftar akun
                                            </Link>
                                        </Button>
                                    )}
                                </>
                            )}
                        </div>
                    </section>

                    <section className="mt-10 grid gap-4 md:grid-cols-3">
                        {infoItems.map((item) => {
                            const Icon = item.icon;
                            return (
                                <Card key={item.id}>
                                    <CardHeader className="gap-3">
                                        <span className="inline-flex size-9 items-center justify-center rounded-md bg-muted">
                                            <Icon className="size-4 text-muted-foreground" />
                                        </span>
                                        <CardTitle className="text-base">
                                            {item.title}
                                        </CardTitle>
                                        <CardDescription>
                                            {item.description}
                                        </CardDescription>
                                    </CardHeader>
                                </Card>
                            );
                        })}
                    </section>

                    <section className="mt-8 rounded-xl border bg-card p-5">
                        <h2 className="text-lg font-semibold">
                            Alur singkat penggunaan
                        </h2>
                        <CardContent className="px-0 pt-4 pb-0">
                            <ol className="space-y-2 text-sm text-muted-foreground">
                                {steps.map((step, index) => (
                                    <li
                                        key={step}
                                        className="flex items-start gap-3"
                                    >
                                        <span className="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium text-foreground">
                                            {index + 1}
                                        </span>
                                        <span>{step}</span>
                                    </li>
                                ))}
                            </ol>
                        </CardContent>
                    </section>
                </main>
            </div>
        </>
    );
}
