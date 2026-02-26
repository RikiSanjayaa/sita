import { type ReactNode } from 'react';

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface AdminLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    title?: ReactNode;
    subtitle?: ReactNode;
}

export default function AdminLayout({
    children,
    breadcrumbs,
    title,
    subtitle,
}: AdminLayoutProps) {
    return (
        <AppLayout
            role="admin"
            breadcrumbs={breadcrumbs}
            title={title}
            subtitle={subtitle}
        >
            {children}
        </AppLayout>
    );
}
