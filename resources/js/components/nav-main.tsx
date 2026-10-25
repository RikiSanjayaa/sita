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
    unreadMessageCount = 0,
}: {
    items: NavItem[];
    label?: string;
    unreadMessageCount?: number;
}) {
    const { urlIsActive } = useActiveUrl();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{label}</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => {
                    const hasUnread =
                        unreadMessageCount > 0 &&
                        (item.href === '/mahasiswa/pesan' ||
                            item.href === '/dosen/pesan');
                    const accessibleLabel = hasUnread
                        ? `${item.title}, ${unreadMessageCount} pesan belum dibaca`
                        : item.title;

                    return (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                className="relative group-data-[collapsible=icon]:overflow-visible max-md:h-11"
                                isActive={urlIsActive(item.href)}
                                tooltip={{ children: accessibleLabel }}
                            >
                                {typeof item.href === 'string' &&
                                item.href.startsWith('/admin') ? (
                                    <a
                                        href={item.href}
                                        aria-label={accessibleLabel}
                                    >
                                        {item.icon && <item.icon />}
                                        <span className="min-w-0 truncate group-data-[collapsible=icon]:hidden">
                                            {item.title}
                                        </span>
                                    </a>
                                ) : (
                                    <Link
                                        href={item.href}
                                        prefetch
                                        aria-label={accessibleLabel}
                                        data-unread={hasUnread || undefined}
                                    >
                                        {item.icon && <item.icon />}
                                        <span className="min-w-0 truncate group-data-[collapsible=icon]:hidden">
                                            {item.title}
                                        </span>
                                        {hasUnread && (
                                            <span
                                                aria-hidden="true"
                                                className="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-destructive px-1 text-xs leading-none font-medium text-destructive-foreground tabular-nums group-data-[collapsible=icon]:absolute group-data-[collapsible=icon]:-top-1.5 group-data-[collapsible=icon]:-right-3 group-data-[collapsible=icon]:ring-2 group-data-[collapsible=icon]:ring-sidebar"
                                            >
                                                {unreadMessageCount > 99
                                                    ? '99+'
                                                    : unreadMessageCount}
                                            </span>
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
