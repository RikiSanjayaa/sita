import { Link } from '@inertiajs/react';

import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useActiveUrl } from '@/hooks/use-active-url';
import { type NavItem } from '@/types';

export function NavMain({
    items = [],
    label = 'Menu utama',
    hasUnreadMessages = false,
}: {
    items: NavItem[];
    label?: string;
    hasUnreadMessages?: boolean;
}) {
    const { urlIsActive } = useActiveUrl();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{label}</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => {
                    const hasUnread =
                        hasUnreadMessages &&
                        (item.href === '/mahasiswa/pesan' ||
                            item.href === '/dosen/pesan');
                    const accessibleLabel = hasUnread
                        ? `${item.title}, pesan belum dibaca`
                        : item.title;

                    return (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                className="relative max-md:h-11"
                                isActive={urlIsActive(item.href)}
                                tooltip={{ children: accessibleLabel }}
                            >
                                {typeof item.href === 'string' &&
                                item.href.startsWith('/admin') ? (
                                    <a href={item.href}>
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                    </a>
                                ) : (
                                    <Link
                                        href={item.href}
                                        prefetch
                                        aria-label={accessibleLabel}
                                        data-unread={hasUnread || undefined}
                                    >
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                        {hasUnread && (
                                            <span
                                                aria-hidden="true"
                                                className="absolute top-1 right-1 size-2 rounded-full bg-primary ring-2 ring-sidebar"
                                            />
                                        )}
                                    </Link>
                                )}
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
