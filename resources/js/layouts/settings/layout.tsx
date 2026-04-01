import { Link, usePage } from '@inertiajs/react';
import { Bell, Lock, Palette, ShieldCheck, Star, User } from 'lucide-react';
import { type PropsWithChildren } from 'react';

import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';

type SettingsLayoutProps = PropsWithChildren<{
    width?: 'full' | 'compact';
}>;

type SettingsNavItem = {
    title: string;
    href: string;
    icon: typeof User;
    roles?: string[];
};

const settingsNavItems: SettingsNavItem[] = [
    {
        title: 'Profil',
        href: '/settings/profile',
        icon: User,
    },
    {
        title: 'Keamanan',
        href: '/settings/password',
        icon: Lock,
    },
    {
        title: 'Dua Faktor',
        href: '/settings/two-factor',
        icon: ShieldCheck,
    },
    {
        title: 'Notifikasi',
        href: '/settings/notifications',
        icon: Bell,
    },
    {
        title: 'CSAT',
        href: '/settings/csat',
        icon: Star,
        roles: ['mahasiswa', 'dosen'],
    },
    {
        title: 'Tampilan',
        href: '/settings/appearance',
        icon: Palette,
    },
];

export default function SettingsLayout({
    children,
    width = 'full',
}: SettingsLayoutProps) {
    const page = usePage<SharedData>();
    const { auth } = page.props;
    const activeRole = auth.activeRole ?? 'mahasiswa';
    const currentPath = new URL(page.url, 'https://x').pathname;

    const visibleItems = settingsNavItems.filter(
        (item) => !item.roles || item.roles.includes(activeRole),
    );

    return (
        <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 md:px-6">
            <div className="flex flex-col gap-8 lg:flex-row lg:gap-12">
                {/* Settings sidebar navigation */}
                <aside className="w-full shrink-0 lg:sticky lg:top-16 lg:max-h-[calc(100svh-4rem)] lg:w-48 lg:self-start lg:overflow-y-auto lg:pt-6">
                    <nav className="flex flex-row flex-wrap gap-1 lg:flex-col">
                        {visibleItems.map((item) => {
                            const Icon = item.icon;
                            const isActive =
                                currentPath === item.href ||
                                currentPath.startsWith(item.href + '/');

                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    prefetch
                                    className={cn(
                                        'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                                        isActive
                                            ? 'bg-accent text-accent-foreground'
                                            : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                                    )}
                                >
                                    <Icon className="size-4 shrink-0" />
                                    <span>{item.title}</span>
                                </Link>
                            );
                        })}
                    </nav>
                </aside>

                {/* Settings content */}
                <section
                    className={cn(
                        'min-w-0 flex-1 space-y-8 py-6',
                        width === 'compact' && 'max-w-xl',
                    )}
                >
                    {children}
                </section>
            </div>
        </div>
    );
}
