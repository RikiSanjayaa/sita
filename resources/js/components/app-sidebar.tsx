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
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useActiveUrl } from '@/hooks/use-active-url';
import {
    dashboard,
    jadwalBimbingan,
    panduan,
    pesan,
    settingNotifikasi,
    tugasAkhir,
    uploadDokumen,
} from '@/routes';
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
        href: jadwalBimbingan().url,
        icon: CalendarClock,
    },
    {
        title: 'Upload Dokumen',
        href: uploadDokumen().url,
        icon: Upload,
    },
    {
        title: 'Pesan',
        href: pesan().url,
        icon: MessageSquareText,
    },
    {
        title: 'Panduan',
        href: panduan().url,
        icon: BookOpen,
    },
];

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    const { urlIsActive } = useActiveUrl();

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

                <SidebarGroup className="px-2 py-0">
                    <SidebarGroupLabel>Pengaturan</SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    size="lg"
                                    asChild
                                    isActive={urlIsActive(
                                        settingNotifikasi().url,
                                    )}
                                    tooltip={{ children: 'Settings' }}
                                >
                                    <Link
                                        href={settingNotifikasi().url}
                                        prefetch
                                    >
                                        <Settings />
                                        <span className="group-data-[collapsible=icon]:hidden">
                                            Settings
                                        </span>
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
