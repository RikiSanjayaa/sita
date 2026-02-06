import { type ReactNode } from 'react';

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface DosenLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    title?: ReactNode;
    subtitle?: ReactNode;
}

export default function DosenLayout({
    children,
    breadcrumbs,
    title,
    subtitle,
}: DosenLayoutProps) {
    return (
        <AppLayout
            role="dosen"
            breadcrumbs={breadcrumbs}
            title={title}
            subtitle={subtitle}
        >
            {children}
        </AppLayout>
    );
}
