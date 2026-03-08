import { Link, usePage } from '@inertiajs/react';
import { BookOpenText, CalendarClock, Users } from 'lucide-react';

import { PublicLayout } from '@/components/public/public-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
            'Jelajahi daftar topik sempro, ringkasan, dan pasangan dosen pembimbingnya.',
        icon: BookOpenText,
    },
];

export default function Welcome() {
    const { auth, highlights } = usePage<SharedData & WelcomePageProps>().props;
    const isAuthenticated = Boolean(auth.user);

    return (
        <PublicLayout
            active="home"
            title="Portal Publik SiTA"
            description="SiTA adalah Sistem Informasi Tugas Akhir Universitas Bumigora yang membantu pengelolaan proses bimbingan, seminar proposal, dan sidang skripsi secara lebih terstruktur. Halaman publik ini menampilkan informasi umum yang dapat diakses tanpa login."
        >
            <div className="space-y-8">
                <section className="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
                    <Card className="shadow-sm">
                        <CardContent className="space-y-6 p-6 lg:p-8">
                            <div className="flex flex-wrap gap-2">
                                <Badge variant="outline">
                                    Informasi Publik
                                </Badge>
                                <Badge variant="outline">
                                    Sempro, Sidang, dan Pembimbing
                                </Badge>
                            </div>

                            <div className="space-y-4">
                                <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                                    Satu pintu untuk melihat gambaran umum
                                    proses tugas akhir.
                                </h2>
                                <p className="max-w-2xl text-sm leading-7 text-muted-foreground sm:text-base">
                                    Gunakan menu di navigasi atas untuk membuka
                                    halaman jadwal, daftar pembimbing, dan topik
                                    sempro. Tampilan publik ini disusun agar
                                    bersih, mudah dibaca, dan tetap relevan
                                    untuk mahasiswa maupun dosen.
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
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader>
                            <CardTitle>Ringkasan Cepat</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            {highlights.map((item) => (
                                <div
                                    key={item.label}
                                    className="rounded-xl border bg-muted/15 p-4"
                                >
                                    <p className="text-xs tracking-[0.18em] text-muted-foreground uppercase">
                                        {item.label}
                                    </p>
                                    <p className="mt-2 text-2xl font-semibold tracking-tight">
                                        {item.value}
                                    </p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </section>

                <section className="grid gap-4 md:grid-cols-3">
                    {featureLinks.map((item) => {
                        const Icon = item.icon;

                        return (
                            <Card key={item.href} className="shadow-sm">
                                <CardHeader className="gap-3">
                                    <span className="inline-flex size-10 items-center justify-center rounded-xl bg-muted text-primary">
                                        <Icon className="size-5" />
                                    </span>
                                    <CardTitle className="text-lg">
                                        {item.title}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <p className="text-sm leading-6 text-muted-foreground">
                                        {item.description}
                                    </p>
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="w-full"
                                    >
                                        <Link href={item.href}>
                                            Buka {item.title}
                                        </Link>
                                    </Button>
                                </CardContent>
                            </Card>
                        );
                    })}
                </section>
            </div>
        </PublicLayout>
    );
}
