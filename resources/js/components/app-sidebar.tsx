import { Link, usePage } from '@inertiajs/react';

import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { roleNavigationConfig } from '@/config/role-navigation';
import { useActiveUrl } from '@/hooks/use-active-url';
import { ROLE_DASHBOARD_PATHS } from '@/lib/roles';
import { AppRole, SharedData, type NavItem } from '@/types';

import AppLogo from './app-logo';

const footerNavItems: NavItem[] = [];

interface AppSidebarProps {
    role?: AppRole | null;
}

export function AppSidebar({ role }: AppSidebarProps) {
    const { auth } = usePage<SharedData>().props;
    const { currentUrl } = useActiveUrl();
    const activeRole = role ?? auth.activeRole ?? 'mahasiswa';
    const navigation = roleNavigationConfig[activeRole];
    const dashboardHref = ROLE_DASHBOARD_PATHS[activeRole];
    const settingsIsActive =
        currentUrl === '/settings' || currentUrl.startsWith('/settings/');

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardHref} prefetch>
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
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    asChild
                                    isActive={settingsIsActive}
                                    tooltip={{ children: 'Settings' }}
                                >
                                    <Link href={navigation.settings.href} prefetch>
                                        {navigation.settings.icon && (
                                            <navigation.settings.icon />
                                        )}
                                        <span>Settings</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </SidebarFooter>
        </Sidebar>
    );
}
