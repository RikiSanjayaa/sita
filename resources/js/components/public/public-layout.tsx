import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { type PropsWithChildren } from 'react';

import AppLogoIcon from '@/components/app-logo-icon';
import { ThemeSettingsFab } from '@/components/public/theme-settings-fab';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard, home, login } from '@/routes';
import { type SharedData } from '@/types';

type PublicLayoutProps = PropsWithChildren<{
    headTitle: string;
    pageTitle?: string;
    description?: string;
    active: 'home' | 'jadwal' | 'pembimbing' | 'topik' | 'mahasiswa';
}>;

const navItems = [
    { id: 'home', label: 'Beranda', href: '/' },
    { id: 'jadwal', label: 'Jadwal', href: '/jadwal' },
    { id: 'mahasiswa', label: 'Mahasiswa', href: '/mahasiswa-aktif' },
    { id: 'pembimbing', label: 'Pembimbing', href: '/pembimbing' },
    { id: 'topik', label: 'Topik', href: '/topik' },
] as const;

const footerLinks = [
    { label: 'Jadwal Publik', href: '/jadwal' },
    { label: 'Mahasiswa Aktif', href: '/mahasiswa-aktif' },
    { label: 'Direktori Pembimbing', href: '/pembimbing' },
    { label: 'Topik Tugas Akhir', href: '/topik' },
] as const;

export function PublicLayout({
    active,
    children,
    description,
    headTitle,
    pageTitle,
}: PublicLayoutProps) {
    const { auth } = usePage<SharedData>().props;
    const isAuthenticated = Boolean(auth.user);

    return (
        <>
            <Head title={headTitle} />

            <div className="min-h-screen bg-background text-foreground">
                <header className="sticky top-0 z-40 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80">
                    <div className="mx-auto flex w-full max-w-6xl items-center justify-between gap-3 px-5 py-4 lg:px-6">
                        <Link
                            href={home().url}
                            className="flex min-w-0 shrink-0 items-center gap-3"
                        >
                            <span className="flex h-12 w-14 shrink-0 items-center justify-center text-primary">
                                <AppLogoIcon className="h-12 w-14" />
                            </span>
                            <div className="grid min-w-0 leading-tight">
                                <span className="truncate text-[15px] font-semibold sm:text-sm">
                                    SiTA Universitas Bumigora
                                </span>
                                <span className="hidden truncate text-[11px] text-muted-foreground sm:block sm:text-xs">
                                    Sistem Informasi Tugas Akhir
                                </span>
                            </div>
                        </Link>

                        <div className="hidden min-w-0 flex-1 justify-center md:flex">
                            <nav className="flex items-center justify-center rounded-full border bg-muted/20 p-1">
                                {navItems.map((item) => (
                                    <Link
                                        key={item.id}
                                        href={item.href}
                                        className={cn(
                                            'rounded-full px-4 py-2 text-sm font-medium transition-colors',
                                            active === item.id
                                                ? 'bg-primary text-primary-foreground shadow-sm hover:bg-primary/90'
                                                : 'text-muted-foreground hover:bg-background/70 hover:text-foreground',
                                        )}
                                    >
                                        {item.label}
                                    </Link>
                                ))}
                            </nav>
                        </div>

                        <div className="flex shrink-0 items-center gap-2">
                            {isAuthenticated ? (
                                <Button
                                    asChild
                                    size="sm"
                                    className="h-9 rounded-lg px-3 sm:h-10 sm:px-4"
                                >
                                    <Link
                                        href={dashboard().url}
                                        className="inline-flex items-center gap-1.5"
                                    >
                                        Dashboard
                                        <ArrowRight className="hidden size-4 sm:block" />
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    asChild
                                    size="sm"
                                    className="h-9 rounded-lg px-3 sm:h-10 sm:px-4"
                                >
                                    <Link href={login().url}>Masuk</Link>
                                </Button>
                            )}
                        </div>
                    </div>

                    <div className="mx-auto flex w-full max-w-6xl flex-wrap gap-2 px-5 pb-4 md:hidden lg:px-6">
                        {navItems.map((item) => (
                            <Link
                                key={item.id}
                                href={item.href}
                                className={cn(
                                    'rounded-full border px-3 py-2 text-[13px] transition-colors sm:text-sm',
                                    active === item.id
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                )}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </div>
                </header>

                <main className="mx-auto w-full max-w-6xl px-5 py-8 lg:px-6 lg:py-10">
                    {pageTitle || description ? (
                        <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                            <div className="space-y-2">
                                <div className="space-y-1.5">
                                    {pageTitle ? (
                                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                                            {pageTitle}
                                        </h1>
                                    ) : null}
                                    {description ? (
                                        <p className="max-w-2xl text-sm leading-6 text-muted-foreground">
                                            {description}
                                        </p>
                                    ) : null}
                                </div>
                            </div>
                        </div>
                    ) : null}

                    {children}
                </main>

                <footer className="border-t bg-muted/10">
                    <div className="mx-auto grid w-full max-w-6xl gap-8 px-5 py-8 lg:grid-cols-[1.3fr_0.7fr] lg:px-6">
                        <div className="space-y-3">
                            <div className="flex items-center gap-3">
                                <span className="flex h-12 w-14 shrink-0 items-center justify-center text-primary">
                                    <AppLogoIcon className="h-12 w-14" />
                                </span>
                                <div className="grid leading-tight">
                                    <span className="text-sm font-semibold">
                                        SiTA Universitas Bumigora
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        Portal informasi publik tugas akhir.
                                    </span>
                                </div>
                            </div>
                            <p className="max-w-xl text-sm leading-6 text-muted-foreground">
                                Akses ringkas untuk melihat jadwal sempro dan
                                sidang, direktori pembimbing, mahasiswa aktif,
                                dan daftar topik tugas akhir yang sudah
                                dipublikasikan.
                            </p>
                        </div>

                        <div className="space-y-3">
                            <p className="text-sm font-semibold">
                                Navigasi Publik
                            </p>
                            <nav className="grid gap-2 text-sm text-muted-foreground">
                                {footerLinks.map((item) => (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        className="transition-colors hover:text-foreground"
                                    >
                                        {item.label}
                                    </Link>
                                ))}
                            </nav>
                        </div>
                    </div>
                </footer>

                <ThemeSettingsFab />
            </div>
        </>
    );
}
