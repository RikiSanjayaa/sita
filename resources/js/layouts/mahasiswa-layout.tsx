import { type ReactNode } from 'react';

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface MahasiswaLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    title?: ReactNode;
    subtitle?: ReactNode;
}

export default function MahasiswaLayout({
    children,
    breadcrumbs,
    title,
    subtitle,
}: MahasiswaLayoutProps) {
    return (
        <AppLayout
            role="mahasiswa"
            breadcrumbs={breadcrumbs}
            title={title}
            subtitle={subtitle}
        >
            {children}
        </AppLayout>
    );
}
