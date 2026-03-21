import { Link } from '@inertiajs/react';

import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function Breadcrumbs({
    breadcrumbs,
}: {
    breadcrumbs: BreadcrumbItemType[];
}) {
    return (
        <Breadcrumb>
            <BreadcrumbList>
                <BreadcrumbItem>
                    <BreadcrumbLink asChild>
                        <Link href="/">Portal SiTA</Link>
                    </BreadcrumbLink>
                </BreadcrumbItem>

                {breadcrumbs.map((breadcrumb, index) => {
                    const isLast = index === breadcrumbs.length - 1;

                    return [
                        <BreadcrumbSeparator
                            key={`${breadcrumb.href}-separator`}
                        />,
                        <BreadcrumbItem key={breadcrumb.href}>
                            {isLast ? (
                                <BreadcrumbPage className="block max-w-[18rem] truncate xl:max-w-none">
                                    {breadcrumb.title}
                                </BreadcrumbPage>
                            ) : (
                                <BreadcrumbLink asChild>
                                    <Link
                                        href={breadcrumb.href}
                                        className="block max-w-[14rem] truncate xl:max-w-none"
                                    >
                                        {breadcrumb.title}
                                    </Link>
                                </BreadcrumbLink>
                            )}
                        </BreadcrumbItem>,
                    ];
                })}
            </BreadcrumbList>
        </Breadcrumb>
    );
}
