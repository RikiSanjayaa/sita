import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';

import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuAction,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { roleNavigationConfig } from '@/config/role-navigation';
import { useActiveUrl } from '@/hooks/use-active-url';
import { AppRole, SharedData, type NavItem } from '@/types';

import AppLogo from './app-logo';

const footerNavItems: NavItem[] = [];

interface AppSidebarProps {
    role?: AppRole | null;
}

export function AppSidebar({ role }: AppSidebarProps) {
    const { auth } = usePage<SharedData>().props;
    const { currentUrl, urlIsActive } = useActiveUrl();
    const activeRole = role ?? auth.activeRole ?? 'mahasiswa';
    const navigation = roleNavigationConfig[activeRole];
    const settingsIsActive =
        currentUrl === '/settings' || currentUrl.startsWith('/settings/');
    const [settingsOpen, setSettingsOpen] = useState(settingsIsActive);

    const settingsItems: NavItem[] = [
        {
            title: 'Profil',
            href: '/settings/profile',
        },
        {
            title: 'Password',
            href: '/settings/password',
        },
        {
            title: 'Notifikasi',
            href: '/settings/notifications',
        },
        ...(activeRole === 'mahasiswa' || activeRole === 'dosen'
            ? [
                  {
                      title: 'CSAT',
                      href: '/settings/csat',
                  },
              ]
            : []),
        {
            title: 'Dua Faktor',
            href: '/settings/two-factor',
        },
        {
            title: 'Tampilan',
            href: '/settings/appearance',
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navigation.main} />
            </SidebarContent>

            <SidebarFooter>
                {footerNavItems.length > 0 && (
                    <NavFooter items={footerNavItems} className="mt-auto" />
                )}

                <SidebarGroup className="px-2 py-0">
                    <SidebarGroupLabel>Pengaturan</SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            <Collapsible
                                asChild
                                open={settingsIsActive || settingsOpen}
                                onOpenChange={setSettingsOpen}
                            >
                                <SidebarMenuItem>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={settingsIsActive}
                                        tooltip={{ children: 'Settings' }}
                                    >
                                        <Link
                                            href={navigation.settings.href}
                                            prefetch
                                        >
                                            {navigation.settings.icon && (
                                                <navigation.settings.icon />
                                            )}
                                            <span>Settings</span>
                                        </Link>
                                    </SidebarMenuButton>

                                    <CollapsibleTrigger asChild>
                                        <SidebarMenuAction
                                            className="data-[state=open]:rotate-90"
                                            showOnHover
                                        >
                                            <ChevronRight />
                                            <span className="sr-only">
                                                Toggle settings menu
                                            </span>
                                        </SidebarMenuAction>
                                    </CollapsibleTrigger>

                                    <CollapsibleContent>
                                        <SidebarMenuSub>
                                            {settingsItems.map((item) => (
                                                <SidebarMenuSubItem
                                                    key={item.title}
                                                >
                                                    <SidebarMenuSubButton
                                                        asChild
                                                        isActive={urlIsActive(
                                                            item.href,
                                                        )}
                                                    >
                                                        <Link
                                                            href={item.href}
                                                            prefetch
                                                        >
                                                            <span>
                                                                {item.title}
                                                            </span>
                                                        </Link>
                                                    </SidebarMenuSubButton>
                                                </SidebarMenuSubItem>
                                            ))}
                                        </SidebarMenuSub>
                                    </CollapsibleContent>
                                </SidebarMenuItem>
                            </Collapsible>
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </SidebarFooter>
        </Sidebar>
    );
}
