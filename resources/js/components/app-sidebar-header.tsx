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
    Trash2,
    type LucideIcon,
} from 'lucide-react';
import {
    useEffect,
    useEffectEvent,
    useMemo,
    useState,
    type MouseEvent,
    type ReactNode,
} from 'react';

import { Breadcrumbs } from '@/components/breadcrumbs';
import { type ChatReadSnapshot } from '@/components/chat/types';
import { LinkifiedText, normalizeUrl } from '@/components/linkified-text';
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
import { dismissNotification, showNotification } from '@/lib/toast';
import { cn } from '@/lib/utils';
import {
    type BreadcrumbItem as BreadcrumbItemType,
    type HeaderNotification,
    type SharedData,
} from '@/types';

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

type NotificationOverride = {
    deleted?: boolean;
    unread?: boolean;
};

function visitNotificationUrl(url: string): void {
    const normalizedUrl = normalizeUrl(url);
    const target = new URL(normalizedUrl, window.location.origin);

    if (target.origin === window.location.origin) {
        router.visit(`${target.pathname}${target.search}${target.hash}`);

        return;
    }

    window.open(target.href, '_blank', 'noopener,noreferrer');
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

function applyNotificationOverride(
    notification: HeaderNotification,
    override?: NotificationOverride,
): HeaderNotification | null {
    if (override?.deleted) {
        return null;
    }

    return {
        ...notification,
        unread: override?.unread ?? notification.unread,
    };
}

function isNotificationControl(target: EventTarget | null): boolean {
    return target instanceof Element && target.closest('a, button') !== null;
}

function deriveNotificationItems(
    notifications: HeaderNotification[],
    sessionNotifications: HeaderNotification[],
    overrides: Record<string, NotificationOverride>,
): HeaderNotification[] {
    const serverIds = new Set(notifications.map((item) => item.id));
    const sessionById = new Map(
        sessionNotifications.map((item) => [item.id, item]),
    );

    const sessionOnlyItems = sessionNotifications
        .filter((item) => !serverIds.has(item.id))
        .map((item) => applyNotificationOverride(item, overrides[item.id]))
        .filter((item): item is HeaderNotification => item !== null);

    const mergedServerItems = notifications
        .map((item) => {
            const sessionItem = sessionById.get(item.id);
            const mergedItem = sessionItem ? { ...item, ...sessionItem } : item;

            return applyNotificationOverride(mergedItem, overrides[item.id]);
        })
        .filter((item): item is HeaderNotification => item !== null);

    return [...sessionOnlyItems, ...mergedServerItems].slice(0, 15);
}

function upsertNotification(
    notifications: HeaderNotification[],
    nextNotification: HeaderNotification,
): HeaderNotification[] {
    const withoutDuplicate = notifications.filter(
        (item) => item.id !== nextNotification.id,
    );

    return [nextNotification, ...withoutDuplicate].slice(0, 15);
}

function HeaderNotifications() {
    const { auth, notifications = [] } = usePage<SharedData>().props;

    const [sessionNotifications, setSessionNotifications] = useState<
        HeaderNotification[]
    >([]);
    const [notificationOverrides, setNotificationOverrides] = useState<
        Record<string, NotificationOverride>
    >({});
    const notificationItems = useMemo(
        () =>
            deriveNotificationItems(
                notifications,
                sessionNotifications,
                notificationOverrides,
            ),
        [notifications, notificationOverrides, sessionNotifications],
    );
    const unreadCount = useMemo(
        () => notificationItems.filter((item) => item.unread).length,
        [notificationItems],
    );
    const readCount = useMemo(
        () => notificationItems.filter((item) => !item.unread).length,
        [notificationItems],
    );

    useEffect(() => {
        const onRead = (event: Event) => {
            const { userId, readNotificationIds } = (
                event as CustomEvent<
                    Pick<ChatReadSnapshot, 'userId' | 'readNotificationIds'>
                >
            ).detail;
            if (userId !== auth.user.id || readNotificationIds.length === 0) {
                return;
            }
            setNotificationOverrides((current) => {
                const next = { ...current };
                for (const id of readNotificationIds) {
                    next[id] = { ...current[id], unread: false };
                }
                return next;
            });
            for (const id of readNotificationIds) {
                dismissNotification(id);
            }
        };
        window.addEventListener('chat:notifications-read', onRead);
        return () =>
            window.removeEventListener('chat:notifications-read', onRead);
    }, [auth.user.id]);

    const receiveNotification = useEffectEvent(
        (payload: IncomingNotification) => {
            const nextNotification = mapIncomingNotification(payload);
            const data = (payload.data ?? payload) as Record<string, unknown>;
            const target = new URL(
                nextNotification.url ?? '/',
                window.location.origin,
            );
            const threadId = Number(target.searchParams.get('thread'));
            const chatEvent = new CustomEvent<
                Pick<ChatReadSnapshot, 'userId' | 'threadId'>
            >('chat:notification', {
                detail: { userId: auth.user.id, threadId },
                cancelable: true,
            });
            const isVisibleChat =
                data.preferenceKey === 'pesanBaru' &&
                target.origin === window.location.origin &&
                ['/mahasiswa/pesan', '/dosen/pesan'].includes(
                    target.pathname,
                ) &&
                Number.isSafeInteger(threadId) &&
                threadId > 0 &&
                !window.dispatchEvent(chatEvent);

            setSessionNotifications((current) => {
                const existing = current.find(
                    (item) => item.id === nextNotification.id,
                );

                return upsertNotification(
                    current,
                    existing
                        ? {
                              ...existing,
                              ...nextNotification,
                              unread:
                                  existing.unread === false
                                      ? false
                                      : nextNotification.unread,
                          }
                        : nextNotification,
                );
            });

            if (isVisibleChat) {
                return;
            }
            const NotificationIcon =
                notificationIconMap[nextNotification.icon] ?? Bell;
            showNotification(
                <button
                    type="button"
                    className="text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    onClick={() =>
                        void handleNotificationClick(nextNotification)
                    }
                >
                    {nextNotification.title}
                </button>,
                {
                    id: `notification:${nextNotification.id}`,
                    icon: <NotificationIcon className="size-5 text-primary" />,
                    description: (
                        <LinkifiedText
                            text={nextNotification.description}
                            onLinkClick={() =>
                                void markNotificationRead(nextNotification)
                            }
                        />
                    ),
                    action: {
                        label: 'Buka',
                        onClick: () =>
                            void handleNotificationClick(nextNotification),
                    },
                },
            );
        },
    );

    useEffect(() => {
        if (typeof window === 'undefined' || !window.Echo || !auth.user?.id)
            return;
        const channelName = `App.Models.User.${auth.user.id}`;
        const echo = window.Echo;
        echo.private(channelName).notification(receiveNotification);

        return () => {
            echo.leave(channelName);
        };
    }, [auth.user?.id]);

    const submitNotificationMutation = (
        url: string,
        method: 'post' | 'delete',
    ): Promise<boolean> => {
        return new Promise((resolve) => {
            let succeeded = false;

            router.visit(url, {
                method,
                data: {},
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    succeeded = true;
                },
                onFinish: () => resolve(succeeded),
            });
        });
    };

    const markNotificationAsRead = (notificationId: string): Promise<boolean> =>
        submitNotificationMutation(
            `/settings/notifications/${notificationId}/read`,
            'post',
        );

    const handleMarkAllAsRead = async () => {
        if (unreadCount === 0) {
            return;
        }

        setNotificationOverrides((current) => {
            const next = { ...current };

            for (const notification of notificationItems) {
                next[notification.id] = {
                    ...next[notification.id],
                    unread: false,
                };
            }

            return next;
        });

        const succeeded = await submitNotificationMutation(
            '/settings/notifications/read-all',
            'post',
        );

        if (!succeeded) {
            setNotificationOverrides((current) => {
                const next = { ...current };

                for (const notification of notificationItems) {
                    if (notification.unread) {
                        next[notification.id] = {
                            ...next[notification.id],
                            unread: true,
                        };
                    }
                }

                return next;
            });
        }
    };

    const handleDeleteReadNotifications = async () => {
        if (readCount === 0) {
            return;
        }

        const readNotificationIds = new Set(
            notificationItems
                .filter((notification) => !notification.unread)
                .map((notification) => notification.id),
        );

        setSessionNotifications((current) =>
            current.filter(
                (notification) => !readNotificationIds.has(notification.id),
            ),
        );
        setNotificationOverrides((current) => {
            const next = { ...current };

            for (const notificationId of readNotificationIds) {
                next[notificationId] = {
                    ...next[notificationId],
                    deleted: true,
                };
            }

            return next;
        });

        await submitNotificationMutation(
            '/settings/notifications/read-items',
            'delete',
        );
    };

    const handleDeleteNotification = async (notificationId: string) => {
        setSessionNotifications((current) =>
            current.filter((item) => item.id !== notificationId),
        );
        setNotificationOverrides((current) => ({
            ...current,
            [notificationId]: {
                ...current[notificationId],
                deleted: true,
            },
        }));

        dismissNotification(notificationId);

        await submitNotificationMutation(
            `/settings/notifications/${notificationId}`,
            'delete',
        );
    };

    const markNotificationRead = async (
        notification: HeaderNotification,
    ): Promise<boolean> => {
        if (!notification.unread) {
            return true;
        }

        setNotificationOverrides((current) => ({
            ...current,
            [notification.id]: {
                ...current[notification.id],
                unread: false,
            },
        }));

        const succeeded = await markNotificationAsRead(notification.id);

        if (!succeeded) {
            setNotificationOverrides((current) => ({
                ...current,
                [notification.id]: {
                    ...current[notification.id],
                    unread: true,
                },
            }));
        }

        return succeeded;
    };

    const handleNotificationClick = async (
        notification: HeaderNotification,
    ) => {
        const markedAsRead = await markNotificationRead(notification);

        if (markedAsRead) dismissNotification(notification.id);
        if (markedAsRead && notification.url) {
            visitNotificationUrl(notification.url);
        }
    };

    const handleNotificationCardClick = (
        event: MouseEvent<HTMLDivElement>,
        notification: HeaderNotification,
    ) => {
        if (isNotificationControl(event.target)) {
            return;
        }

        void handleNotificationClick(notification);
    };

    return (
        <>
            <Sheet>
                <SheetTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="relative size-9 max-md:size-11"
                        aria-label="Open notifications"
                    >
                        <Bell className="size-4" />
                        {unreadCount > 0 && (
                            <span className="absolute top-0 -right-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-destructive px-1 text-xs leading-none font-medium text-destructive-foreground">
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
                                    className="h-auto p-0 text-sm text-primary hover:bg-transparent hover:text-primary/80 max-md:min-h-11"
                                    onClick={handleMarkAllAsRead}
                                    disabled={unreadCount === 0}
                                >
                                    Baca semua
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="h-auto p-0 text-sm text-destructive hover:bg-transparent hover:text-destructive/80 max-md:min-h-11"
                                    onClick={handleDeleteReadNotifications}
                                    disabled={readCount === 0}
                                >
                                    Bersihkan
                                </Button>
                            </div>
                        </div>

                        <Separator />

                        <div className="flex flex-1 flex-col divide-y overflow-auto">
                            {notificationItems.length === 0 ? (
                                <div className="m-4 flex min-h-72 flex-1 flex-col items-center justify-center gap-2 rounded-lg border border-dashed bg-muted/30 p-6 text-center">
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
                                                data-testid="notification-card"
                                                className={cn(
                                                    'flex cursor-pointer items-start gap-3 px-4 py-4 transition-colors hover:bg-accent',
                                                    n.unread &&
                                                        'bg-primary-muted/40',
                                                )}
                                                onClick={(event) =>
                                                    handleNotificationCardClick(
                                                        event,
                                                        n,
                                                    )
                                                }
                                            >
                                                <span className="mt-0.5 inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary-muted text-primary-muted-foreground">
                                                    <Icon className="size-4" />
                                                </span>
                                                <div className="min-w-0 flex-1">
                                                    <button
                                                        type="button"
                                                        className="block w-full text-left focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:outline-none"
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
                                                                <span className="mt-1.5 size-2 shrink-0 rounded-full bg-primary" />
                                                            )}
                                                        </div>
                                                    </button>
                                                    <p className="text-sm text-muted-foreground">
                                                        <LinkifiedText
                                                            text={n.description}
                                                            onLinkClick={() =>
                                                                void markNotificationRead(
                                                                    n,
                                                                )
                                                            }
                                                        />
                                                    </p>
                                                    <p className="mt-2 text-xs text-muted-foreground">
                                                        {n.time}
                                                    </p>
                                                </div>
                                                {!n.unread && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="mt-0.5 size-8 shrink-0 text-muted-foreground hover:text-foreground max-md:size-11"
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
                    className="h-9 gap-2 px-2 max-md:h-11"
                    data-test="header-user-menu-trigger"
                >
                    <Avatar className="h-8 w-8 overflow-hidden rounded-full">
                        <AvatarImage src={user.avatar} alt={user.name} />
                        <AvatarFallback className="rounded-lg bg-primary-muted text-primary-muted-foreground">
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

export function AppSidebarHeader({
    breadcrumbs = [],
    title,
}: {
    breadcrumbs?: BreadcrumbItemType[];
    title?: ReactNode;
    subtitle?: ReactNode;
}) {
    const mobileTitle = title ?? breadcrumbs[breadcrumbs.length - 1]?.title;

    return (
        <header className="sticky top-0 z-50 flex h-12 shrink-0 items-center gap-2 border-b bg-background px-4">
            <div className="flex min-w-0 flex-1 items-center gap-2">
                <SidebarTrigger className="-ml-1 size-9 max-md:size-11" />
                <div className="flex min-w-0 flex-1 items-center gap-3">
                    <div className="min-w-0 md:hidden">
                        {mobileTitle ? (
                            <p className="truncate text-sm font-semibold">
                                {mobileTitle}
                            </p>
                        ) : null}
                    </div>

                    <div className="hidden min-w-0 md:block">
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    </div>
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
