import { type BreadcrumbItem } from '@/types';

export function makeSettingsBreadcrumbs(
    currentTitle: string,
    currentHref: string,
): BreadcrumbItem[] {
    return [
        {
            title: 'Settings',
            href: '/settings',
        },
        {
            title: currentTitle,
            href: currentHref,
        },
    ];
}
