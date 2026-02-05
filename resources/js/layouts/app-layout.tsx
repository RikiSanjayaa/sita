import { type ReactNode } from 'react';

import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { type BreadcrumbItem } from '@/types';

interface AppLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    title?: ReactNode;
    subtitle?: ReactNode;
}

export default ({
    children,
    breadcrumbs,
    title,
    subtitle,
    ...props
}: AppLayoutProps) => (
    <AppLayoutTemplate
        breadcrumbs={breadcrumbs}
        title={title}
        subtitle={subtitle}
        {...props}
    >
        {children}
    </AppLayoutTemplate>
);
