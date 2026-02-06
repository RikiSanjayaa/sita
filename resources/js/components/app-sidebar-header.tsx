import { usePage } from '@inertiajs/react';
import {
    Bell,
    CalendarClock,
    CheckCircle2,
    ChevronsUpDown,
    Megaphone,
    PencilLine,
    type LucideIcon,
} from 'lucide-react';

import { Breadcrumbs } from '@/components/breadcrumbs';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import {
    type BreadcrumbItem as BreadcrumbItemType,
    type SharedData,
} from '@/types';

type NotificationTone = 'success' | 'warning' | 'info';

type NotificationItem = {
    title: string;
    description: string;
    time: string;
    icon: LucideIcon;
    tone: NotificationTone;
    unread: boolean;
};

const notifications: NotificationItem[] = [
    {
        title: 'Revisi Bimbingan',
        description: 'Dosen pembimbing memberikan catatan revisi pada BAB II',
        time: '2 jam lalu',
        icon: PencilLine,
        tone: 'warning',
        unread: true,
    },
    {
        title: 'Jadwal Bimbingan',
        description: 'Bimbingan dengan Dr. Budi Santoso besok pukul 10.00 WIB',
        time: '5 jam lalu',
        icon: CalendarClock,
        tone: 'info',
        unread: true,
    },
    {
        title: 'Dokumen Disetujui',
        description: 'BAB I telah disetujui oleh pembimbing',
        time: '1 hari lalu',
        icon: CheckCircle2,
        tone: 'success',
        unread: false,
    },
    {
        title: 'Pengumuman',
        description: 'Pendaftaran seminar proposal gelombang 2 dibuka',
        time: '2 hari lalu',
        icon: Megaphone,
        tone: 'info',
        unread: false,
    },
];

function HeaderNotifications() {
    const unreadCount = notifications.filter((n) => n.unread).length;

    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="relative h-9 w-9 p-0"
                    aria-label="Open notifications"
                >
                    <Bell className="size-4" />
                    {unreadCount > 0 && (
                        <span className="absolute -top-1 -right-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-destructive px-1 text-[11px] leading-none font-medium text-destructive-foreground">
                            {unreadCount}
                        </span>
                    )}
                </Button>
            </SheetTrigger>
            <SheetContent side="right" className="p-0">
                <div className="flex h-full flex-col">
                    <div className="flex items-center justify-between gap-3 p-4 pr-12">
                        <div className="flex items-center gap-2">
                            <div className="text-sm leading-tight font-semibold">
                                Notifikasi
                            </div>
                            <Badge variant="destructive" className="px-1.5">
                                {unreadCount}
                            </Badge>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            className="h-8 px-2 text-xs"
                        >
                            Tandai Semua Dibaca
                        </Button>
                    </div>

                    <Separator />

                    <div className="grid flex-1 auto-rows-min gap-3 overflow-auto p-4">
                        {notifications.map((n) => {
                            const Icon = n.icon;
                            const toneClasses =
                                n.tone === 'success'
                                    ? 'text-green-600'
                                    : n.tone === 'warning'
                                      ? 'text-orange-500'
                                      : 'text-blue-600';

                            return (
                                <div
                                    key={n.title}
                                    className="flex items-start gap-3 rounded-lg border bg-background p-4"
                                >
                                    <span
                                        className={
                                            'mt-0.5 inline-flex size-9 items-center justify-center rounded-md bg-muted ' +
                                            toneClasses
                                        }
                                    >
                                        <Icon className="size-4" />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-start justify-between gap-3">
                                            <p className="text-sm font-medium">
                                                {n.title}
                                            </p>
                                            {n.unread && (
                                                <span className="mt-1 size-2 rounded-[3px] bg-blue-600" />
                                            )}
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {n.description}
                                        </p>
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            {n.time}
                                        </p>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}

function HeaderUserMenu() {
    const { auth } = usePage<SharedData>().props;
    const getInitials = useInitials();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-9 gap-2 px-2"
                    data-test="header-user-menu-trigger"
                >
                    <Avatar className="h-8 w-8 overflow-hidden rounded-full">
                        <AvatarImage
                            src={auth.user.avatar}
                            alt={auth.user.name}
                        />
                        <AvatarFallback className="rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                            {getInitials(auth.user.name)}
                        </AvatarFallback>
                    </Avatar>
                    <span className="hidden max-w-40 truncate text-sm font-medium md:inline">
                        {auth.user.name}
                    </span>
                    <ChevronsUpDown className="size-4 text-muted-foreground" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="min-w-56 rounded-lg" align="end">
                <UserMenuContent user={auth.user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex min-w-0 items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <div className="flex min-w-0 items-center gap-3">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
            </div>
            <div className="ml-auto flex items-center gap-2">
                <HeaderNotifications />
                <Separator orientation="vertical" className="mx-1 h-6" />
                <HeaderUserMenu />
            </div>
        </header>
    );
}
