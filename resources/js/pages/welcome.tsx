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

type FeatureLink = {
    href: string;
    title: string;
    description: string;
    icon: typeof CalendarClock;
    highlightKey: keyof typeof highlightMeta;
};

const featureLinks: FeatureLink[] = [
    {
        href: '/jadwal',
        title: 'Jadwal',
        description:
            'Lihat jadwal sempro dan sidang yang akan datang maupun yang sudah berlalu.',
        icon: CalendarClock,
        highlightKey: 'Jadwal',
    },
    {
        href: '/mahasiswa-aktif',
        title: 'Mahasiswa Aktif',
        description:
            'Lihat mahasiswa yang masih aktif mengambil tugas akhir, dari yang baru terdaftar sampai tahap sempro, bimbingan, dan sidang.',
        icon: GraduationCap,
        highlightKey: 'Mahasiswa Aktif',
    },
    {
        href: '/pembimbing',
        title: 'Pembimbing',
        description:
            'Telusuri dosen pembimbing aktif berdasarkan program studi dan konsentrasi.',
        icon: Users,
        highlightKey: 'Dosen',
    },
    {
        href: '/topik',
        title: 'Topik',
        description:
            'Telusuri topik skripsi yang benar-benar sudah final setelah sidang selesai dan dinyatakan lulus.',
        icon: BookOpenText,
        highlightKey: 'Topik',
    },
];

const highlightMeta = {
    Jadwal: {
        accentClassName: 'bg-primary/12 text-primary',
        valueClassName: 'text-primary',
        icon: CalendarClock,
    },
    Dosen: {
        accentClassName:
            'bg-emerald-500/12 text-emerald-700 dark:text-emerald-400',
        valueClassName: 'text-emerald-600 dark:text-emerald-400',
        icon: Users,
    },
    'Mahasiswa Aktif': {
        accentClassName: 'bg-cyan-500/12 text-cyan-700 dark:text-cyan-400',
        valueClassName: 'text-cyan-600 dark:text-cyan-400',
        icon: GraduationCap,
    },
    Topik: {
        accentClassName: 'bg-amber-500/12 text-amber-700 dark:text-amber-400',
        valueClassName: 'text-amber-600 dark:text-amber-400',
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
                                Jelajahi data publik SiTA dalam satu tempat.
                            </h3>
                        </div>

                        <Badge variant="outline" className="w-fit">
                            Live dari data publik SiTA
                        </Badge>
                    </div>

                    <div className="grid gap-0 divide-y rounded-3xl border sm:grid-cols-2 sm:divide-y-0 xl:grid-cols-4 xl:divide-x">
                        {featureLinks.map((item, index) => {
                            const Icon = item.icon;
                            const meta = highlightMeta[item.highlightKey];
                            const highlight = highlights.find(
                                (h) => h.label === item.highlightKey,
                            );

                            const isSecond = index === 1;
                            const isThird = index === 2;
                            const isFourth = index === 3;

                            return (
                                <div
                                    key={item.href}
                                    className={cn(
                                        'flex flex-col gap-4 p-6',
                                        isSecond && 'sm:border-l',
                                        isThird &&
                                            'sm:border-t xl:border-t-0 xl:border-l',
                                        isFourth &&
                                            'sm:border-t sm:border-l xl:border-t-0',
                                    )}
                                >
                                    <div className="flex items-start justify-between">
                                        <span
                                            className={cn(
                                                'inline-flex size-10 items-center justify-center rounded-xl',
                                                meta.accentClassName,
                                            )}
                                        >
                                            <Icon className="size-5" />
                                        </span>
                                        {highlight && (
                                            <CountUpNumber
                                                value={highlight.value}
                                                className={cn(
                                                    'text-4xl font-semibold tracking-tight sm:text-5xl',
                                                    meta.valueClassName,
                                                )}
                                            />
                                        )}
                                    </div>
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
                                            variant="secondary"
                                            size="sm"
                                            className="rounded-lg"
                                        >
                                            <Link href={item.href}>
                                                Buka {item.title}
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </section>
            </div>
        </PublicLayout>
    );
}
