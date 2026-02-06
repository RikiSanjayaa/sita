import { Link, usePage } from '@inertiajs/react';

import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { ROLE_DASHBOARD_PATHS, ROLE_PORTAL_LABELS } from '@/lib/roles';
import { AppRole, type BreadcrumbItem as BreadcrumbItemType, type SharedData } from '@/types';

export function Breadcrumbs({
    breadcrumbs,
}: {
    breadcrumbs: BreadcrumbItemType[];
}) {
    const { auth } = usePage<SharedData>().props;
    const activeRole = (auth.activeRole ?? 'mahasiswa') as AppRole;
    const currentPage = breadcrumbs[breadcrumbs.length - 1]?.title ?? '-';
    const portalLabel = ROLE_PORTAL_LABELS[activeRole];
    const portalHref = ROLE_DASHBOARD_PATHS[activeRole];

    return (
        <Breadcrumb>
            <BreadcrumbList>
                <BreadcrumbItem>
                    <BreadcrumbLink asChild>
                        <Link href={portalHref}>{portalLabel}</Link>
                    </BreadcrumbLink>
                </BreadcrumbItem>
                <BreadcrumbSeparator />
                <BreadcrumbItem>
                    <BreadcrumbPage>{currentPage}</BreadcrumbPage>
                </BreadcrumbItem>
            </BreadcrumbList>
        </Breadcrumb>
    );
}
