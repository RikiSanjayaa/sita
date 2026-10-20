import { type BreadcrumbItem } from '@/types';

export function makeSettingsBreadcrumbs(
    currentTitle: string,
    currentHref: string,
): BreadcrumbItem[] {
    return [
        {
            title: 'Pengaturan Akun',
            href: '/settings',
        },
        {
            title: currentTitle,
            href: currentHref,
        },
    ];
}
