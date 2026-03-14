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
                <header className="border-b bg-background/95 backdrop-blur">
                    <div className="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between px-5 py-4 lg:px-6">
                        <Link
                            href={home().url}
                            className="flex min-w-0 items-center gap-3"
                        >
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                                <AppLogoIcon className="size-5" />
                            </span>
                            <div className="grid min-w-0 leading-tight">
                                <span className="truncate text-[15px] font-semibold sm:text-sm">
                                    SiTA Universitas Bumigora
                                </span>
                                <span className="truncate text-[11px] text-muted-foreground sm:text-xs">
                                    Sistem Informasi Tugas Akhir
                                </span>
                            </div>
                        </Link>

                        <nav className="hidden items-center rounded-full border bg-muted/20 p-1 md:flex">
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

                        <div className="flex items-center gap-2">
                            {isAuthenticated ? (
                                <Button
                                    asChild
                                    size="sm"
                                    className="h-10 rounded-lg px-4"
                                >
                                    <Link href={dashboard().url}>
                                        Dashboard
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    asChild
                                    size="sm"
                                    variant="outline"
                                    className="h-10 rounded-lg px-4"
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

                <ThemeSettingsFab />
            </div>
        </>
    );
}
