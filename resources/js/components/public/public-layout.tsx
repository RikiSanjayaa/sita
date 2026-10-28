import { Head, Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

import AppLogoIcon from '@/components/app-logo-icon';
import { PageHeader } from '@/components/patterns/page-header';
import { PublicIntro } from '@/components/public/public-intro';
import { ThemeSettingsButton } from '@/components/public/theme-settings-button';
import { Button } from '@/components/ui/button';
import { neutralAcademicTerminology as n } from '@/lib/academic-terminology';
import { cn } from '@/lib/utils';
import { dashboard, home, login } from '@/routes';
import { type SharedData } from '@/types';

type PublicLayoutProps = PropsWithChildren<{
    headTitle: string;
    /** Page title (h1). Omit when the page renders its own h1. */
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
    { label: `Topik ${n.finalWork}`, href: '/topik' },
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
    const accountLink = isAuthenticated
        ? { href: dashboard().url, label: 'Dashboard' }
        : { href: login().url, label: 'Masuk' };

    return (
        <>
            <Head title={headTitle} />

            <div className="min-h-screen bg-background text-foreground">
                <header className="border-b bg-background md:sticky md:top-0 md:z-40 md:bg-background/95 md:backdrop-blur">
                    <div className="mx-auto flex w-full max-w-6xl items-center justify-between gap-3 px-4 py-3 md:px-6 md:py-4">
                        <Link
                            href={home().url}
                            className="flex min-w-0 items-center gap-3 rounded-md focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            <AppLogoIcon className="h-10 w-12 shrink-0 text-primary md:h-12 md:w-14" />
                            <span className="grid min-w-0 leading-tight">
                                <span className="text-sm font-semibold">
                                    SiTA Universitas Bumigora
                                </span>
                                <span className="hidden text-xs text-muted-foreground sm:block">
                                    Sistem Informasi Tugas Akhir
                                </span>
                            </span>
                        </Link>

                        <nav
                            aria-label="Navigasi utama"
                            className="hidden items-center rounded-full border bg-muted/40 p-1 md:flex"
                        >
                            {navItems.map((item) => (
                                <Link
                                    key={item.id}
                                    href={item.href}
                                    aria-current={
                                        active === item.id ? 'page' : undefined
                                    }
                                    className={cn(
                                        'rounded-full px-4 py-2 text-sm font-medium transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                        active === item.id
                                            ? 'bg-primary text-primary-foreground'
                                            : 'text-muted-foreground hover:bg-background hover:text-foreground',
                                    )}
                                >
                                    {item.label}
                                </Link>
                            ))}
                        </nav>

                        <div className="flex shrink-0 items-center gap-2">
                            <ThemeSettingsButton />
                            <Button asChild className="h-11 px-4 md:h-10">
                                <Link href={accountLink.href}>
                                    {accountLink.label}
                                </Link>
                            </Button>
                        </div>
                    </div>

                    <nav
                        aria-label="Navigasi utama"
                        className="mx-auto flex w-full max-w-6xl items-center justify-between gap-1 px-4 pb-1 md:hidden"
                    >
                        {navItems.map((item) => (
                            <Link
                                key={item.id}
                                href={item.href}
                                aria-current={
                                    active === item.id ? 'page' : undefined
                                }
                                className={cn(
                                    'inline-flex min-h-11 min-w-11 items-center justify-center text-xs font-medium whitespace-nowrap underline-offset-8 transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                    active === item.id
                                        ? 'text-primary underline decoration-2'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>
                </header>

                <main className="mx-auto w-full max-w-6xl space-y-6 px-4 py-6 md:px-6 md:py-10">
                    {pageTitle ? (
                        <PublicIntro>
                            <PageHeader
                                title={pageTitle}
                                description={description}
                                className="[&_p]:max-w-2xl"
                            />
                        </PublicIntro>
                    ) : null}

                    {children}
                </main>

                <footer className="border-t">
                    <div className="mx-auto grid w-full max-w-6xl gap-8 px-4 py-8 md:px-6 lg:grid-cols-[1.3fr_0.7fr]">
                        <div className="space-y-3">
                            <div className="flex items-center gap-3">
                                <AppLogoIcon className="h-10 w-12 shrink-0 text-primary" />
                                <div className="grid leading-tight">
                                    <span className="text-sm font-semibold">
                                        SiTA Universitas Bumigora
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {`Portal informasi publik ${n.finalWorkLower}.`}
                                    </span>
                                </div>
                            </div>
                            <p className="max-w-xl text-sm leading-6 text-muted-foreground">
                                Akses ringkas untuk melihat jadwal{' '}
                                {n.proposalExamShort} dan {n.finalExam},
                                direktori pembimbing, mahasiswa aktif, dan
                                daftar topik {n.finalWork.toLowerCase()} yang
                                sudah dipublikasikan.
                            </p>
                        </div>

                        <nav aria-label="Navigasi publik" className="space-y-1">
                            <p className="text-sm font-semibold">
                                Navigasi Publik
                            </p>
                            <ul className="text-sm text-muted-foreground">
                                {footerLinks.map((item) => (
                                    <li key={item.href}>
                                        <Link
                                            href={item.href}
                                            className="-mx-2 inline-flex min-h-11 items-center rounded-md px-2 transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none md:min-h-9"
                                        >
                                            {item.label}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </nav>
                    </div>
                </footer>
            </div>
        </>
    );
}
