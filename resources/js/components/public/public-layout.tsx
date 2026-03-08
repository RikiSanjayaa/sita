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
    title: string;
    description?: string;
    active: 'home' | 'jadwal' | 'pembimbing' | 'topik';
}>;

const navItems = [
    { id: 'home', label: 'Beranda', href: '/' },
    { id: 'jadwal', label: 'Jadwal', href: '/jadwal' },
    { id: 'pembimbing', label: 'Pembimbing', href: '/pembimbing' },
    { id: 'topik', label: 'Topik', href: '/topik' },
] as const;

export function PublicLayout({
    active,
    children,
    description,
    title,
}: PublicLayoutProps) {
    const { auth } = usePage<SharedData>().props;
    const isAuthenticated = Boolean(auth.user);

    return (
        <>
            <Head title={title} />

            <div className="min-h-screen bg-background text-foreground">
                <header className="border-b bg-background/95 backdrop-blur">
                    <div className="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-4 px-5 py-4 lg:px-6">
                        <div className="flex flex-wrap items-center gap-6">
                            <Link
                                href={home().url}
                                className="flex items-center gap-3"
                            >
                                <span className="flex size-10 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                                    <AppLogoIcon className="size-5" />
                                </span>
                                <div className="grid leading-tight">
                                    <span className="text-sm font-semibold">
                                        SiTA Universitas Bumigora
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        Sistem Informasi Tugas Akhir
                                    </span>
                                </div>
                            </Link>

                            <nav className="hidden items-center gap-1 md:flex">
                                {navItems.map((item) => (
                                    <Link
                                        key={item.id}
                                        href={item.href}
                                        className={cn(
                                            'rounded-md px-3 py-2 text-sm transition-colors',
                                            active === item.id
                                                ? 'bg-accent text-accent-foreground'
                                                : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                        )}
                                    >
                                        {item.label}
                                    </Link>
                                ))}
                            </nav>
                        </div>

                        <div className="flex items-center gap-2">
                            {isAuthenticated ? (
                                <Button asChild>
                                    <Link href={dashboard().url}>
                                        Dashboard
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                            ) : (
                                <Button asChild>
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
                                    'rounded-md px-3 py-2 text-sm transition-colors',
                                    active === item.id
                                        ? 'bg-accent text-accent-foreground'
                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                )}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </div>
                </header>

                <main className="mx-auto w-full max-w-6xl px-5 py-8 lg:px-6 lg:py-10">
                    <div className="mb-8 max-w-3xl space-y-3">
                        <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">
                            {title}
                        </h1>
                        {description ? (
                            <p className="text-sm leading-7 text-muted-foreground sm:text-base">
                                {description}
                            </p>
                        ) : null}
                    </div>

                    {children}
                </main>

                <ThemeSettingsFab />
            </div>
        </>
    );
}
