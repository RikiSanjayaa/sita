import { Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import { type SharedData } from '@/types';

interface AuthLayoutProps {
    title?: string;
    description?: string;
}

const stats = [
    { value: '500+', label: 'Mahasiswa' },
    { value: '80+', label: 'Dosen' },
    { value: '12+', label: 'Prodi' },
];

export default function AuthSplitLayout({
    children,
    title,
    description,
}: PropsWithChildren<AuthLayoutProps>) {
    const { name } = usePage<SharedData>().props;

    return (
        <div className="flex min-h-dvh">
            {/* ── Dark Sidebar ── */}
            <aside className="relative hidden w-[26%] shrink-0 flex-col justify-between overflow-hidden bg-[#0d1b2e] p-8 lg:flex">
                {/* Decorative circle */}
                <div className="absolute -right-10 top-4 h-44 w-44 rounded-full bg-[#1a3a4a] opacity-60" />

                {/* Logo */}
                <Link
                    href={home().url}
                    className="relative z-10 flex items-center gap-2"
                >
                    <AppLogoIcon className="size-5 fill-current text-white" />
                    <span className="text-sm font-bold tracking-wide text-white">
                        {name}
                    </span>
                </Link>

                {/* Bottom section */}
                <div className="relative z-10 space-y-5">
                    {/* Badge */}
                    <div className="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1">
                        <span className="h-1.5 w-1.5 rounded-full bg-emerald-400" />
                        <span className="text-xs font-medium text-emerald-300">
                            Sistem Resmi
                        </span>
                    </div>

                    {/* Headline */}
                    <div className="space-y-2">
                        <h2 className="text-2xl font-bold leading-tight text-white">
                            Selamat Datang
                            <br />
                            di {name}
                        </h2>
                        <p className="text-xs leading-relaxed text-slate-400">
                            Platform untuk pengelolaan tugas akhir mahasiswa
                            secara efisien dan terintegrasi.
                        </p>
                    </div>

                    {/* Stats */}
                    <div className="flex gap-3">
                        {stats.map((s) => (
                            <div
                                key={s.label}
                                className="flex-1 rounded-lg bg-white/5 px-3 py-2.5 ring-1 ring-white/8"
                            >
                                <p className="text-base font-bold text-white">
                                    {s.value}
                                </p>
                                <p className="text-[10px] text-slate-400">
                                    {s.label}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Copyright */}
                <p className="relative z-10 text-[10px] text-slate-600">
                    © {new Date().getFullYear()} {name}
                </p>
            </aside>

            {/* ── Form Panel ── */}
            <main className="flex flex-1 flex-col items-center justify-center bg-slate-50 px-6 py-10 dark:bg-background">
                {/* Mobile logo */}
                <Link
                    href={home().url}
                    className="mb-8 flex items-center gap-2 lg:hidden"
                >
                    <AppLogoIcon className="size-7 fill-current text-primary" />
                    <span className="font-semibold">{name}</span>
                </Link>

                <div className="w-full max-w-sm">
                    <div className="mb-7 space-y-1">
                        <h1 className="text-xl font-bold text-gray-900 dark:text-foreground">
                            {title}
                        </h1>
                        {description && (
                            <p className="text-sm text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                    {children}
                </div>
            </main>
        </div>
    );
}
