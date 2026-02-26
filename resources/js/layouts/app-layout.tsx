import { type ReactNode } from 'react';

import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { type AppRole, type BreadcrumbItem } from '@/types';

interface AppLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    title?: ReactNode;
    subtitle?: ReactNode;
    role?: AppRole | null;
}

export default ({
    children,
    breadcrumbs,
    title,
    subtitle,
    role,
    ...props
}: AppLayoutProps) => (
    <AppLayoutTemplate
        breadcrumbs={breadcrumbs}
        title={title}
        subtitle={subtitle}
        role={role}
        {...props}
    >
        {children}
    </AppLayoutTemplate>
);
