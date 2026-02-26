import { usePage } from '@inertiajs/react';

import { ROLE_PORTAL_LABELS } from '@/lib/roles';
import { AppRole, SharedData } from '@/types';

import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    const { auth } = usePage<SharedData>().props;
    const activeRole = (auth.activeRole ?? 'mahasiswa') as AppRole;
    const portalLabel = ROLE_PORTAL_LABELS[activeRole];

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                <AppLogoIcon className="size-5 fill-current text-sidebar-primary-foreground" />
            </div>
            <div className="ml-1 grid flex-1 text-left leading-tight">
                <span className="truncate text-sm font-semibold">SiTA</span>
                <span className="truncate text-xs text-muted-foreground">
                    {portalLabel}
                </span>
            </div>
        </>
    );
}
