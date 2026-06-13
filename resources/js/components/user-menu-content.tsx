import { Link, router, usePage } from '@inertiajs/react';
import { LogOut, User as UserIcon } from 'lucide-react';

import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import { AppRole, type SharedData, type User } from '@/types';

interface UserMenuContentProps {
    user: User;
}

export function UserMenuContent({ user }: UserMenuContentProps) {
    const { auth } = usePage<SharedData>().props;
    const cleanup = useMobileNavigation();
    const activeRole = auth.activeRole;
    const canSwitchDosenKaprodi =
        auth.availableRoles.includes('dosen') &&
        auth.availableRoles.includes('kaprodi');
    const kaprodiIsActive = activeRole === 'kaprodi';

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    const handleRoleSwitch = (role: AppRole) => {
        if (role === activeRole) {
            return;
        }

        cleanup();

        router.post(
            '/role/switch',
            { role },
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={edit().url}
                        prefetch
                        onClick={cleanup}
                    >
                        <UserIcon className="mr-2" />
                        Edit Profile
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            {canSwitchDosenKaprodi && (
                <>
                    <DropdownMenuGroup>
                        <div className="px-2 py-1.5">
                            <button
                                type="button"
                                role="switch"
                                aria-checked={kaprodiIsActive}
                                aria-label="Pilih portal dosen atau kaprodi"
                                className="grid h-9 w-full grid-cols-2 rounded-md border bg-muted p-0.5 text-xs font-medium text-muted-foreground transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                onClick={() =>
                                    handleRoleSwitch(
                                        kaprodiIsActive ? 'dosen' : 'kaprodi',
                                    )
                                }
                            >
                                <span
                                    className={cn(
                                        'flex items-center justify-center rounded-sm px-2 transition-colors',
                                        !kaprodiIsActive &&
                                            'bg-background text-foreground shadow-sm',
                                    )}
                                >
                                    Dosen
                                </span>
                                <span
                                    className={cn(
                                        'flex items-center justify-center rounded-sm px-2 transition-colors',
                                        kaprodiIsActive &&
                                            'bg-primary text-primary-foreground shadow-sm',
                                    )}
                                >
                                    Kaprodi
                                </span>
                            </button>
                        </div>
                    </DropdownMenuGroup>
                    <DropdownMenuSeparator />
                </>
            )}
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout().url}
                    as="button"
                    method="post"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    Log out
                </Link>
            </DropdownMenuItem>
        </>
    );
}
