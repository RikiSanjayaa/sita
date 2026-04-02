import { Head, router } from '@inertiajs/react';
import { AlertTriangle, Home, MoveLeft, ServerCrash, ShieldX } from 'lucide-react';

import { Button } from '@/components/ui/button';

interface ErrorPageProps {
    status: 503 | 500 | 404 | 403;
}

const errors = {
    503: {
        code: '503',
        icon: ServerCrash,
        title: 'Sedang Dalam Pemeliharaan',
        description:
            'Sistem sedang dalam proses pemeliharaan. Silakan kembali lagi beberapa saat.',
    },
    500: {
        code: '500',
        icon: AlertTriangle,
        title: 'Terjadi Kesalahan Server',
        description:
            'Terjadi kesalahan pada server kami. Tim teknis telah diberitahu dan sedang menangani masalah ini.',
    },
    404: {
        code: '404',
        icon: MoveLeft,
        title: 'Halaman Tidak Ditemukan',
        description:
            'Halaman yang Anda cari tidak ada atau mungkin telah dipindahkan. Periksa kembali URL yang Anda masukkan.',
    },
    403: {
        code: '403',
        icon: ShieldX,
        title: 'Akses Ditolak',
        description:
            'Anda tidak memiliki izin untuk mengakses halaman ini. Pastikan Anda sudah masuk dengan akun yang benar.',
    },
} as const;

export default function ErrorPage({ status }: ErrorPageProps) {
    const error = errors[status] ?? errors[404];
    const Icon = error.icon;

    return (
        <div className="flex min-h-dvh flex-col items-center justify-center bg-background px-6">
            <Head title={`${error.code} — ${error.title}`} />

            <div className="w-full max-w-md space-y-8 text-center">
                {/* Status code */}
                <div className="relative inline-block">
                    <span className="select-none text-[9rem] font-black leading-none tracking-tighter text-muted/40 dark:text-muted-foreground/10">
                        {error.code}
                    </span>
                    {/* Icon overlaid on the number */}
                    <div className="absolute inset-0 flex items-center justify-center">
                        <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10 ring-1 ring-primary/20">
                            <Icon className="h-8 w-8 text-primary" />
                        </div>
                    </div>
                </div>

                {/* Text */}
                <div className="space-y-2">
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">
                        {error.title}
                    </h1>
                    <p className="text-sm leading-relaxed text-muted-foreground">
                        {error.description}
                    </p>
                </div>

                {/* Divider */}
                <div className="border-t" />

                {/* Actions */}
                <div className="flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
                    <Button
                        variant="default"
                        onClick={() => router.visit('/')}
                        className="w-full sm:w-auto"
                    >
                        <Home className="mr-2 h-4 w-4" />
                        Ke Beranda
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() => window.history.back()}
                        className="w-full sm:w-auto"
                    >
                        <MoveLeft className="mr-2 h-4 w-4" />
                        Kembali
                    </Button>
                </div>
            </div>
        </div>
    );
}
