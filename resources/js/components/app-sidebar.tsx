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
import { useUnreadMessages } from '@/hooks/use-unread-messages';
import { AppRole, SharedData, type NavItem } from '@/types';

import AppLogo from './app-logo';

const footerNavItems: NavItem[] = [];

interface AppSidebarProps {
    role?: AppRole | null;
}

export function AppSidebar({ role }: AppSidebarProps) {
    const { auth, academicTerminology } = usePage<SharedData>().props;
    const hasUnreadMessages = useUnreadMessages();
    const { currentUrl } = useActiveUrl();
    const activeRole = role ?? auth.activeRole ?? 'mahasiswa';
    const navigation = roleNavigationConfig[activeRole];
    const resolvedMainNavigation =
        activeRole === 'mahasiswa'
            ? navigation.main.map((item) =>
                  item.href === '/mahasiswa/tugas-akhir'
                      ? { ...item, title: academicTerminology.finalWork }
                      : item,
              )
            : navigation.main;
    const canViewDocuments = auth.kaprodiCapabilities?.view_documents ?? true;
    const visibleItems = (items: NavItem[]): NavItem[] =>
        activeRole === 'kaprodi'
            ? items.filter(
                  (item) =>
                      item.href !== '/kaprodi/dokumen' || canViewDocuments,
              )
            : items;
    const mainNavigation = visibleItems(resolvedMainNavigation);
    const sections = (navigation.sections ?? []).map((section) => ({
        ...section,
        items: visibleItems(section.items),
    }));
    const settingsIsActive = currentUrl.startsWith('/settings');

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
                <NavMain
                    items={mainNavigation}
                    label={navigation.mainLabel}
                    hasUnreadMessages={hasUnreadMessages}
                />
                {sections.map((section) => (
                    <NavMain
                        key={section.label}
                        items={section.items}
                        label={section.label}
                        hasUnreadMessages={hasUnreadMessages}
                    />
                ))}
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
                                    className="max-md:h-11"
                                    isActive={settingsIsActive}
                                    tooltip={{
                                        children: navigation.settings.title,
                                    }}
                                >
                                    <Link
                                        href={navigation.settings.href}
                                        prefetch
                                    >
                                        {navigation.settings.icon && (
                                            <navigation.settings.icon />
                                        )}
                                        <span>{navigation.settings.title}</span>
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
