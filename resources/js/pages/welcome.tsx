import { Link, usePage } from '@inertiajs/react';
import {
    BookOpenText,
    CalendarClock,
    GraduationCap,
    Users,
} from 'lucide-react';
import { useMemo } from 'react';

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

export default function Welcome() {
    const { auth, highlights } = usePage<SharedData & WelcomePageProps>().props;
    const isAuthenticated = Boolean(auth.user);
    const chartHeights = useMemo(() => {
        const values = highlights.map((item) => Number(item.value) || 0);
        const maxValue = Math.max(...values, 1);

        return highlights.map((item) => {
            const value = Number(item.value) || 0;

            return Math.max(22, Math.round((value / maxValue) * 100));
        });
    }, [highlights]);

    return (
        <PublicLayout active="home" headTitle="Beranda">
            <div className="space-y-8">
                <section className="grid gap-8 lg:grid-cols-[1.15fr_0.85fr] lg:items-end">
                    <div className="space-y-6">
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="outline">
                                Universitas Bumigora
                            </Badge>
                        </div>

                        <div className="space-y-4">
                            <h2 className="max-w-3xl text-3xl font-semibold tracking-tight sm:text-4xl lg:text-[2.8rem]">
                                Akses cepat ke ritme tugas akhir yang sedang
                                berjalan.
                            </h2>
                            <p className="max-w-2xl text-sm leading-7 text-muted-foreground sm:text-base">
                                Beranda publik ini merangkum tiga hal utama:
                                jadwal seminar, direktori pembimbing aktif, dan
                                topik tugas akhir yang sudah bisa ditelusuri.
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-3">
                            {isAuthenticated ? (
                                <Button asChild>
                                    <Link href={dashboard().url}>
                                        Buka Dashboard
                                    </Link>
                                </Button>
                            ) : (
                                <Button asChild>
                                    <Link href={login().url}>
                                        Masuk ke SiTA
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>

                    <div className="grid gap-4 rounded-3xl border bg-muted/15 p-5 sm:grid-cols-[0.78fr_1.22fr] sm:items-end">
                        <div className="align-center flex h-full flex-col justify-center">
                            <p className="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                Snapshot Publik
                            </p>
                            <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                Sinyal cepat untuk melihat sebaran data publik
                                yang saat ini tersedia di SiTA.
                            </p>
                        </div>

                        <div className="grid grid-cols-3 items-end gap-4">
                            {highlights.map((item, index) => (
                                <div key={item.label} className="space-y-3">
                                    <div className="flex h-32 items-end rounded-2xl border bg-background/80 p-2">
                                        <div
                                            className={cn(
                                                'w-full rounded-xl bg-primary/80 transition-all',
                                                index === 1 &&
                                                    'bg-emerald-500/80',
                                                index === 2 &&
                                                    'bg-amber-500/80',
                                            )}
                                            style={{
                                                height: `${chartHeights[index]}%`,
                                            }}
                                        />
                                    </div>
                                    <div>
                                        <p className="text-xl font-semibold tracking-tight">
                                            {item.value}
                                        </p>
                                        <p className="text-xs leading-5 text-muted-foreground">
                                            {item.label}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
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
