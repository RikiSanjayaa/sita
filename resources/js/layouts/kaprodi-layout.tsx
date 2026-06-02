import { type ReactNode } from 'react';

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface KaprodiLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    title?: ReactNode;
    subtitle?: ReactNode;
}

export default function KaprodiLayout({
    children,
    breadcrumbs,
    title,
    subtitle,
}: KaprodiLayoutProps) {
    return (
        <AppLayout
            role="kaprodi"
            breadcrumbs={breadcrumbs}
            title={title}
            subtitle={subtitle}
        >
            {children}
        </AppLayout>
    );
}
