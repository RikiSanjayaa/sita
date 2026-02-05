import { Link } from '@inertiajs/react';
import {
    BookOpen,
    CalendarClock,
    FileText,
    LayoutGrid,
    MessageSquareText,
    Settings,
    Upload,
} from 'lucide-react';

import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard, tugasAkhir } from '@/routes';
import { type NavItem } from '@/types';

import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
        icon: LayoutGrid,
    },
    {
        title: 'Tugas Akhir saya',
        href: tugasAkhir().url,
        icon: FileText,
    },
    {
        title: 'Jadwal Bimbingan',
        href: '#',
        icon: CalendarClock,
    },
    {
        title: 'Upload Dokumen',
        href: '#',
        icon: Upload,
    },
    {
        title: 'Pesan',
        href: '#',
        icon: MessageSquareText,
    },
    {
        title: 'Panduan',
        href: '#',
        icon: BookOpen,
    },
];

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard().url} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                {footerNavItems.length > 0 && (
                    <NavFooter items={footerNavItems} className="mt-auto" />
                )}
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            tooltip="Settings"
                            type="button"
                            disabled
                            data-test="sidebar-settings-placeholder"
                        >
                            <Settings />
                            <span>Settings</span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>
        </Sidebar>
    );
}
