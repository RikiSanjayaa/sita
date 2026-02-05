import { type PropsWithChildren, type ReactNode } from 'react';

import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { type BreadcrumbItem } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
    title,
    subtitle,
}: PropsWithChildren<{
    breadcrumbs?: BreadcrumbItem[];
    title?: ReactNode;
    subtitle?: ReactNode;
}>) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader
                    breadcrumbs={breadcrumbs}
                    title={title}
                    subtitle={subtitle}
                />
                {children}
            </AppContent>
        </AppShell>
    );
}
