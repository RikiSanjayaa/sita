import { Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BookOpenText,
    CalendarClock,
    GraduationCap,
    Users,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { PublicLayout } from '@/components/public/public-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard, login } from '@/routes';
import { type SharedData } from '@/types';

type Highlight = {
    label: string;
    value: string;
};

type WelcomePageProps = {
    highlights: Highlight[];
};

const featureLinks = [
    {
        href: '/jadwal',
        title: 'Jadwal',
        description:
            'Lihat jadwal sempro dan sidang yang akan datang maupun yang sudah berlalu.',
        icon: CalendarClock,
    },
    {
        href: '/mahasiswa-aktif',
        title: 'Mahasiswa Aktif',
        description:
            'Lihat mahasiswa yang masih aktif mengambil tugas akhir, dari yang baru terdaftar sampai tahap sempro, bimbingan, dan sidang.',
        icon: GraduationCap,
    },
    {
        href: '/pembimbing',
        title: 'Pembimbing',
        description:
            'Telusuri dosen pembimbing aktif berdasarkan program studi dan konsentrasi.',
        icon: Users,
    },
    {
        href: '/topik',
        title: 'Topik',
        description:
            'Telusuri topik tugas akhir yang sudah dipublikasikan beserta pembimbing aktifnya.',
        icon: BookOpenText,
    },
];

const highlightMeta = {
    Jadwal: {
        accentClassName: 'bg-primary/12 text-primary',
        valueClassName: 'text-primary',
        helper: 'Agenda sempro dan sidang yang saat ini tersedia untuk publik.',
        icon: CalendarClock,
    },
    Dosen: {
        accentClassName:
            'bg-emerald-500/12 text-emerald-700 dark:text-emerald-400',
        valueClassName: 'text-emerald-600 dark:text-emerald-400',
        helper: 'Pembimbing aktif yang bisa ditelusuri dari direktori publik.',
        icon: Users,
    },
    'Mahasiswa Aktif': {
        accentClassName: 'bg-cyan-500/12 text-cyan-700 dark:text-cyan-400',
        valueClassName: 'text-cyan-600 dark:text-cyan-400',
        helper: 'Mahasiswa yang masih menjalani proses tugas akhir hari ini.',
        icon: GraduationCap,
    },
    Topik: {
        accentClassName: 'bg-amber-500/12 text-amber-700 dark:text-amber-400',
        valueClassName: 'text-amber-600 dark:text-amber-400',
        helper: 'Topik yang sudah dipublikasikan sebagai referensi awal.',
        icon: BookOpenText,
    },
} as const;

function CountUpNumber({
    className,
    value,
}: {
    className?: string;
    value: string;
}) {
    const target = Number(value);
    const ref = useRef<HTMLParagraphElement | null>(null);
    const [displayValue, setDisplayValue] = useState(0);
    const [hasAnimated, setHasAnimated] = useState(false);

    useEffect(() => {
        if (!Number.isFinite(target)) {
            return;
        }

        const element = ref.current;

        if (element === null || hasAnimated) {
            return;
        }

        let frameId = 0;
        let observer: IntersectionObserver | null = null;

        const animate = () => {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                setDisplayValue(target);
                setHasAnimated(true);

                return;
            }

            const startedAt = performance.now();
            const duration = 900;

            const step = (timestamp: number) => {
                const progress = Math.min(
                    (timestamp - startedAt) / duration,
                    1,
                );
                const easedProgress = 1 - Math.pow(1 - progress, 3);

                setDisplayValue(Math.round(target * easedProgress));

                if (progress < 1) {
                    frameId = window.requestAnimationFrame(step);

                    return;
                }

                setDisplayValue(target);
                setHasAnimated(true);
            };

            frameId = window.requestAnimationFrame(step);
        };

        observer = new IntersectionObserver(
            (entries) => {
                if (!entries.some((entry) => entry.isIntersecting)) {
                    return;
                }

                observer?.disconnect();
                animate();
            },
            {
                threshold: 0.35,
            },
        );

        observer.observe(element);

        return () => {
            observer?.disconnect();

            if (frameId !== 0) {
                window.cancelAnimationFrame(frameId);
            }
        };
    }, [hasAnimated, target]);

    if (!Number.isFinite(target)) {
        return (
            <p ref={ref} className={className}>
                {value}
            </p>
        );
    }

    return (
        <p ref={ref} className={className}>
            {displayValue.toLocaleString('id-ID')}
        </p>
    );
}

export default function Welcome() {
    const { auth, highlights } = usePage<SharedData & WelcomePageProps>().props;
    const isAuthenticated = Boolean(auth.user);

    return (
        <PublicLayout active="home" headTitle="Beranda">
            <div className="space-y-10 lg:space-y-14">
                <section className="relative overflow-hidden rounded-[2rem] border bg-gradient-to-br from-background via-background to-primary/6 px-6 py-8 sm:px-8 lg:px-10 lg:py-12">
                    <div className="absolute top-0 right-0 h-48 w-48 rounded-full bg-primary/8 blur-3xl" />
                    <div className="absolute bottom-0 left-10 h-32 w-32 rounded-full bg-cyan-500/8 blur-3xl" />

                    <div className="relative max-w-4xl space-y-6">
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="outline">
                                Universitas Bumigora
                            </Badge>
                            <Badge variant="outline">Portal Publik SiTA</Badge>
                        </div>

                        <div className="space-y-4">
                            <h2 className="max-w-4xl text-4xl font-semibold tracking-tight text-foreground sm:text-5xl lg:text-[4rem] lg:leading-[1.05]">
                                Akses cepat ke ritme tugas akhir yang sedang
                                berjalan.
                            </h2>
                            <p className="max-w-3xl text-base leading-8 text-muted-foreground lg:text-lg">
                                Portal publik ini merangkum jadwal seminar,
                                mahasiswa aktif, direktori pembimbing, dan topik
                                tugas akhir yang sudah dapat ditelusuri tanpa
                                membuat pengalaman terasa seperti dashboard
                                internal.
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-3">
                            {isAuthenticated ? (
                                <Button
                                    asChild
                                    size="lg"
                                    className="rounded-xl px-6"
                                >
                                    <Link href={dashboard().url}>
                                        Buka Dashboard
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    asChild
                                    size="lg"
                                    className="rounded-xl px-6"
                                >
                                    <Link href={login().url}>
                                        Masuk ke SiTA
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                            )}
                            <p className="text-sm text-muted-foreground">
                                Data publik diperbarui mengikuti isi sistem yang
                                sedang aktif.
                            </p>
                        </div>
                    </div>
                </section>

                <section className="space-y-5">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                        <div className="space-y-2">
                            <p className="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                Snapshot Publik
                            </p>
                            <h3 className="text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                                Angka penting yang paling sering dicari.
                            </h3>
                            <p className="max-w-2xl text-sm leading-7 text-muted-foreground sm:text-base">
                                Ringkasan ini memberi gambaran cepat tentang
                                aktivitas publik SiTA sebelum pengunjung masuk
                                ke halaman detailnya.
                            </p>
                        </div>

                        <Badge variant="outline" className="w-fit">
                            Live dari data publik SiTA
                        </Badge>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {highlights.map((item) => {
                            const meta =
                                highlightMeta[
                                    item.label as keyof typeof highlightMeta
                                ] ?? highlightMeta.Jadwal;
                            const Icon = meta.icon;

                            return (
                                <div
                                    key={item.label}
                                    className="group relative overflow-hidden rounded-[1.75rem] border bg-background/90 p-5 shadow-sm transition-colors hover:bg-background"
                                >
                                    <div className="absolute top-0 right-0 h-24 w-24 rounded-full bg-primary/5 blur-2xl transition-opacity group-hover:opacity-100" />
                                    <div className="relative flex h-full flex-col gap-5">
                                        <div className="flex items-start justify-between gap-3">
                                            <div
                                                className={cn(
                                                    'inline-flex size-11 items-center justify-center rounded-2xl',
                                                    meta.accentClassName,
                                                )}
                                            >
                                                <Icon className="size-5" />
                                            </div>
                                            <Badge
                                                variant="outline"
                                                className="rounded-full"
                                            >
                                                {item.label}
                                            </Badge>
                                        </div>

                                        <div className="space-y-2">
                                            <CountUpNumber
                                                value={item.value}
                                                className={cn(
                                                    'text-4xl font-semibold tracking-tight sm:text-5xl',
                                                    meta.valueClassName,
                                                )}
                                            />
                                            <p className="text-base font-semibold text-foreground">
                                                {item.label}
                                            </p>
                                            <p className="text-sm leading-6 text-muted-foreground">
                                                {meta.helper}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </section>

                <section className="grid gap-4 border-b md:grid-cols-2 md:gap-0 md:divide-x md:rounded-3xl md:border xl:grid-cols-4">
                    {featureLinks.map((item) => {
                        const Icon = item.icon;

                        return (
                            <div
                                key={item.href}
                                className="flex flex-col gap-4 p-6"
                            >
                                <span className="inline-flex size-10 items-center justify-center rounded-xl bg-muted text-primary">
                                    <Icon className="size-5" />
                                </span>
                                <div className="space-y-2">
                                    <h3 className="text-lg font-semibold">
                                        {item.title}
                                    </h3>
                                    <p className="text-sm leading-6 text-muted-foreground">
                                        {item.description}
                                    </p>
                                </div>
                                <div className="mt-auto pt-2">
                                    <Button
                                        asChild
                                        variant="ghost"
                                        className="px-0"
                                    >
                                        <Link href={item.href}>
                                            Buka {item.title}
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        );
                    })}
                </section>
            </div>
        </PublicLayout>
    );
}
