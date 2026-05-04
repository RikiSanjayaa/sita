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
            <div className="flex h-12 w-14 items-center justify-center text-sidebar-primary group-data-[collapsible=icon]:h-8 group-data-[collapsible=icon]:w-8">
                <AppLogoIcon className="h-12 w-14 fill-current text-sidebar-primary group-data-[collapsible=icon]:h-7 group-data-[collapsible=icon]:w-7" />
            </div>
            <div className="ml-1 grid flex-1 text-left leading-tight group-data-[collapsible=icon]:hidden">
                <span className="truncate text-sm font-semibold">SiTA</span>
                <span className="truncate text-xs text-muted-foreground">
                    {portalLabel}
                </span>
            </div>
        </>
    );
}
