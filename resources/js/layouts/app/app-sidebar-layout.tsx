import { usePage } from '@inertiajs/react';
import { type PropsWithChildren, type ReactNode } from 'react';

import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import PhonePromptDialog from '@/components/phone-prompt-dialog';
import { type AppRole, type BreadcrumbItem, type SharedData } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
    title,
    subtitle,
    role,
}: PropsWithChildren<{
    breadcrumbs?: BreadcrumbItem[];
    title?: ReactNode;
    subtitle?: ReactNode;
    role?: AppRole | null;
}>) {
    const { phonePrompt } = usePage<SharedData>().props;

    return (
        <AppShell variant="sidebar">
            <AppSidebar role={role} />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader
                    breadcrumbs={breadcrumbs}
                    title={title}
                    subtitle={subtitle}
                />
                {children}
            </AppContent>
            {phonePrompt && <PhonePromptDialog prompt={phonePrompt} />}
        </AppShell>
    );
}
