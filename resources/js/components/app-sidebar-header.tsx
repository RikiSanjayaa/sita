import { router, usePage } from '@inertiajs/react';
import {
    Bell,
    BellOff,
    CalendarClock,
    CheckCircle2,
    ChevronsUpDown,
    Megaphone,
    MessageSquareText,
    PencilLine,
    Repeat,
    Trash2,
    type LucideIcon,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';

import { Breadcrumbs } from '@/components/breadcrumbs';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { ROLE_LABELS, UI_ROLES } from '@/lib/roles';
import {
    AppRole,
    type BreadcrumbItem as BreadcrumbItemType,
    type HeaderNotification,
    type SharedData,
} from '@/types';

declare global {
    interface Window {
        activeMentorshipThreadId?: number;
    }
}

const notificationIconMap: Record<string, LucideIcon> = {
    bell: Bell,
    'calendar-clock': CalendarClock,
    'check-circle': CheckCircle2,
    megaphone: Megaphone,
    'message-square': MessageSquareText,
    'file-text': PencilLine,
};

type IncomingNotification = {
    id?: string;
    data?: {
        title?: string;
        description?: string;
        icon?: string;
        url?: string;
        createdAt?: string;
    };
    read_at?: string | null;
};

function csrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

function mapIncomingNotification(
    payload: IncomingNotification,
): HeaderNotification {
    const data = (payload.data ?? payload) as Record<string, unknown>;
    const notificationId =
        typeof payload.id === 'string'
            ? payload.id
            : typeof data.id === 'string'
              ? data.id
              : String(Math.random());
    const readAt =
        typeof payload.read_at === 'string' || payload.read_at === null
            ? payload.read_at
            : typeof data.read_at === 'string' || data.read_at === null
              ? (data.read_at as string | null)
              : null;

    return {
        id: notificationId,
        title: typeof data.title === 'string' ? data.title : 'Notifikasi baru',
        description:
            typeof data.description === 'string' ? data.description : '',
        icon: typeof data.icon === 'string' ? data.icon : 'bell',
        url: typeof data.url === 'string' ? data.url : null,
        time: 'baru saja',
        unread: readAt === null,
    };
}

function mergeNotifications(
    incoming: HeaderNotification[],
    current: HeaderNotification[],
): HeaderNotification[] {
    const currentById = new Map(current.map((item) => [item.id, item]));

    return incoming.map((item) => {
        const existing = currentById.get(item.id);

        if (!existing) {
            return item;
        }

        return {
            ...item,
            unread: existing.unread === false ? false : item.unread,
        };
    });
}

function HeaderNotifications() {
    const {
        auth,
        notifications = [],
        notificationSettings,
    } = usePage<SharedData>().props;

    const [notificationItems, setNotificationItems] =
        useState<HeaderNotification[]>(notifications);
    const [toastNotification, setToastNotification] =
        useState<HeaderNotification | null>(null);
    const toastTimeoutRef = useRef<number | null>(null);
    const unreadCount = useMemo(
        () => notificationItems.filter((item) => item.unread).length,
        [notificationItems],
    );
    const readCount = useMemo(
        () => notificationItems.filter((item) => !item.unread).length,
        [notificationItems],
    );

    useEffect(() => {
        setNotificationItems((current) =>
            mergeNotifications(notifications, current),
        );
    }, [notifications]);

    useEffect(() => {
        return () => {
            if (toastTimeoutRef.current !== null) {
                window.clearTimeout(toastTimeoutRef.current);
            }
        };
    }, []);

    useEffect(() => {
        if (typeof window === 'undefined' || !window.Echo || !auth.user?.id) {
            return;
        }

        const channelName = `App.Models.User.${auth.user.id}`;
        const channel = window.Echo.private(channelName);

        channel.notification((payload: IncomingNotification) => {
            const nextNotification = mapIncomingNotification(payload);

            let isThreadCurrentlyOpen = false;
            const url = nextNotification.url || '';
            const threadMatch = url.match(/[?&]thread=(\d+)/);

            if (threadMatch) {
                const threadId = parseInt(threadMatch[1], 10);
                if (
                    window.activeMentorshipThreadId === threadId &&
                    document.visibilityState === 'visible'
                ) {
                    isThreadCurrentlyOpen = true;
                }
            }

            if (isThreadCurrentlyOpen) {
                setNotificationItems((current) => {
                    const withoutDuplicate = current.filter(
                        (item) => item.id !== nextNotification.id,
                    );

                    return [
                        {
                            ...nextNotification,
                            unread: false,
                        },
                        ...withoutDuplicate,
                    ].slice(0, 15);
                });

                fetch(`/settings/notifications/${nextNotification.id}/read`, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({}),
                }).catch(console.error);
                return;
            }

            setNotificationItems((current) => {
                const existing = current.find(
                    (item) => item.id === nextNotification.id,
                );
                const nextItem = existing
                    ? {
                          ...existing,
                          ...nextNotification,
                          unread:
                              existing.unread === false
                                  ? false
                                  : nextNotification.unread,
                      }
                    : nextNotification;

                const withoutDuplicate = current.filter(
                    (item) => item.id !== nextItem.id,
                );

                return [nextItem, ...withoutDuplicate].slice(0, 15);
            });
            setToastNotification(nextNotification);

            if (toastTimeoutRef.current !== null) {
                window.clearTimeout(toastTimeoutRef.current);
            }

            toastTimeoutRef.current = window.setTimeout(() => {
                setToastNotification(null);
            }, 4500);

            if (
                notificationSettings?.browserNotifications &&
                'Notification' in window &&
                window.Notification.permission === 'granted'
            ) {
                const nativeNotification = new window.Notification(
                    nextNotification.title,
                    {
                        body: nextNotification.description,
                    },
                );

                nativeNotification.onclick = () => {
                    window.focus();

                    if (nextNotification.url) {
                        router.visit(nextNotification.url);
                    }
                };
            }
        });

        return () => {
            window.Echo.leave(channelName);
        };
    }, [auth.user?.id, notificationSettings?.browserNotifications]);

    const markNotificationAsRead = async (notificationId: string) => {
        await fetch(`/settings/notifications/${notificationId}/read`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({}),
        });
    };

    const handleMarkAllAsRead = async () => {
        if (unreadCount === 0) {
            return;
        }

        setNotificationItems((current) =>
            current.map((notification) => ({
                ...notification,
                unread: false,
            })),
        );

        await fetch('/settings/notifications/read-all', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({}),
        });
    };

    const handleDeleteReadNotifications = async () => {
        if (readCount === 0) {
            return;
        }

        setNotificationItems((current) =>
            current.filter((notification) => notification.unread),
        );

        await fetch('/settings/notifications/read-items', {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({}),
        });
    };

    const handleDeleteNotification = async (notificationId: string) => {
        setNotificationItems((current) =>
            current.filter((item) => item.id !== notificationId),
        );

        if (toastNotification?.id === notificationId) {
            setToastNotification(null);
        }

        await fetch(`/settings/notifications/${notificationId}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({}),
        });
    };

    const handleNotificationClick = async (
        notification: HeaderNotification,
    ) => {
        if (notification.unread) {
            setNotificationItems((current) =>
                current.map((item) =>
                    item.id === notification.id
                        ? {
                              ...item,
                              unread: false,
                          }
                        : item,
                ),
            );

            await markNotificationAsRead(notification.id);
        }

        if (notification.url) {
            router.visit(notification.url);
        }
    };

    const toast = toastNotification;

    return (
        <>
            <Sheet>
                <SheetTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="relative h-9 w-9 p-0 transition-colors hover:bg-primary/10 hover:text-primary focus-visible:ring-2 focus-visible:ring-primary/40"
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
                        <div className="flex flex-col gap-2 p-4 pr-12">
                            <div className="flex items-center gap-2">
                                <div className="text-sm leading-tight font-semibold">
                                    Notifikasi
                                </div>
                                <Badge variant="destructive" className="px-1.5">
                                    {unreadCount}
                                </Badge>
                            </div>
                            <div className="flex items-center gap-4">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="h-auto p-0 text-sm text-primary hover:bg-transparent hover:text-primary/80"
                                    onClick={handleMarkAllAsRead}
                                    disabled={unreadCount === 0}
                                >
                                    Baca semua
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="h-auto p-0 text-sm text-destructive hover:bg-transparent hover:text-destructive/80"
                                    onClick={handleDeleteReadNotifications}
                                    disabled={readCount === 0}
                                >
                                    Bersihkan
                                </Button>
                            </div>
                        </div>

                        <Separator />

                        <div className="grid flex-1 auto-rows-min gap-3 overflow-auto p-4">
                            {notificationItems.length === 0 ? (
                                <div className="flex h-full min-h-72 flex-col items-center justify-center gap-2 rounded-lg border border-dashed bg-muted/30 p-6 text-center">
                                    <BellOff className="size-5 text-muted-foreground" />
                                    <p className="text-sm font-medium">
                                        Belum ada notifikasi baru
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Notifikasi baru akan muncul di sini saat
                                        ada aktivitas.
                                    </p>
                                </div>
                            ) : (
                                notificationItems.map(
                                    (n: HeaderNotification) => {
                                        const Icon =
                                            notificationIconMap[n.icon] ?? Bell;

                                        return (
                                            <div
                                                key={n.id}
                                                className="flex items-start gap-3 rounded-lg border bg-background p-4 transition-colors hover:bg-primary/10"
                                            >
                                                <span
                                                    className={
                                                        'mt-0.5 inline-flex size-9 items-center justify-center rounded-md bg-muted text-primary'
                                                    }
                                                >
                                                    <Icon className="size-4" />
                                                </span>
                                                <button
                                                    type="button"
                                                    className="min-w-0 flex-1 text-left focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:outline-none"
                                                    onClick={() =>
                                                        handleNotificationClick(
                                                            n,
                                                        )
                                                    }
                                                >
                                                    <div className="flex items-start justify-between gap-3">
                                                        <p className="text-sm font-medium">
                                                            {n.title}
                                                        </p>
                                                        {n.unread && (
                                                            <span className="mt-1 size-2 rounded-[3px] bg-primary" />
                                                        )}
                                                    </div>
                                                    <p className="text-sm text-muted-foreground">
                                                        {n.description}
                                                    </p>
                                                    <p className="mt-2 text-xs text-muted-foreground">
                                                        {n.time}
                                                    </p>
                                                </button>
                                                {!n.unread && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="mt-0.5 size-8 shrink-0 text-muted-foreground hover:text-foreground"
                                                        onClick={() =>
                                                            handleDeleteNotification(
                                                                n.id,
                                                            )
                                                        }
                                                        aria-label="Hapus notifikasi"
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        );
                                    },
                                )
                            )}
                        </div>
                    </div>
                </SheetContent>
            </Sheet>

            {toast !== null && (
                <button
                    type="button"
                    className="group fixed top-4 left-1/2 z-50 w-[min(520px,calc(100vw-2rem))] -translate-x-1/2 overflow-hidden rounded-xl border border-primary/30 bg-background p-4 text-left shadow-2xl shadow-primary/20 focus-visible:ring-2 focus-visible:ring-primary/50 focus-visible:outline-none"
                    onClick={() => handleNotificationClick(toast)}
                >
                    <div className="pointer-events-none absolute inset-0 bg-primary/10 transition-colors group-hover:bg-primary/20" />
                    <div className="relative z-10">
                        <p className="text-sm font-semibold text-primary">
                            {toast.title}
                        </p>
                        <p className="mt-1 text-sm text-foreground/85">
                            {toast.description}
                        </p>
                        <p className="mt-2 text-[11px] text-primary/80">
                            Klik untuk buka detail
                        </p>
                    </div>
                </button>
            )}
        </>
    );
}

function HeaderUserMenu() {
    const { auth } = usePage<SharedData>().props;
    const getInitials = useInitials();
    const user = auth.user;

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
                        <AvatarImage src={user.avatar} alt={user.name} />
                        <AvatarFallback className="rounded-lg bg-primary/15 text-primary">
                            {getInitials(user.name)}
                        </AvatarFallback>
                    </Avatar>
                    <span className="hidden max-w-40 truncate text-sm font-medium md:inline">
                        {user.name}
                    </span>
                    <ChevronsUpDown className="size-4 text-muted-foreground" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="min-w-56 rounded-lg" align="end">
                <UserMenuContent user={user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function HeaderRoleSwitcher() {
    const { auth } = usePage<SharedData>().props;
    const activeRole = auth.activeRole as AppRole | null;
    const availableRoles = auth.availableRoles.filter((role) =>
        UI_ROLES.includes(role),
    ) as AppRole[];

    if (activeRole === null || availableRoles.length <= 1) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm" className="h-9 gap-2">
                    <Repeat className="size-4" />
                    <span className="hidden sm:inline">
                        {ROLE_LABELS[activeRole]}
                    </span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-48">
                <DropdownMenuLabel>Pilih Role Aktif</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {availableRoles.map((role) => (
                    <DropdownMenuItem
                        key={role}
                        className="cursor-pointer"
                        disabled={role === activeRole}
                        onClick={() => {
                            router.post(
                                '/role/switch',
                                { role },
                                { preserveScroll: true },
                            );
                        }}
                    >
                        {ROLE_LABELS[role]}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function AppSidebarHeader({
    breadcrumbs = [],
    title,
    subtitle,
}: {
    breadcrumbs?: BreadcrumbItemType[];
    title?: ReactNode;
    subtitle?: ReactNode;
}) {
    const mobileTitle = title ?? breadcrumbs[breadcrumbs.length - 1]?.title;

    return (
        <header className="sticky top-0 z-50 flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 bg-background px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex min-w-0 flex-1 items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <div className="flex min-w-0 flex-1 items-center gap-3">
                    <div className="min-w-0 md:hidden">
                        {mobileTitle ? (
                            <h1 className="truncate text-sm font-semibold">
                                {mobileTitle}
                            </h1>
                        ) : null}
                    </div>

                    <div className="hidden min-w-0 md:block">
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    </div>
                </div>
            </div>
            <div className="ml-auto flex items-center gap-2">
                <HeaderRoleSwitcher />
                <HeaderNotifications />
                <Separator orientation="vertical" className="mx-1 h-6" />
                <HeaderUserMenu />
            </div>
        </header>
    );
}
