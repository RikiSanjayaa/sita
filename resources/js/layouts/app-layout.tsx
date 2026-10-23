import { usePage } from '@inertiajs/react';
import { type ReactNode } from 'react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { type AppRole, type BreadcrumbItem, type SharedData } from '@/types';

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
}: AppLayoutProps) => {
    const { mentorshipTicket } = usePage<SharedData>().props;

    return (
        <AppLayoutTemplate
            breadcrumbs={breadcrumbs}
            title={title}
            subtitle={subtitle}
            role={role}
            {...props}
        >
            {mentorshipTicket?.suspended && (
                <Alert variant="destructive" className="mb-4">
                    <AlertTitle>Tiket bimbingan habis</AlertTitle>
                    <AlertDescription>
                        {mentorshipTicket.message}
                    </AlertDescription>
                </Alert>
            )}
            {children}
        </AppLayoutTemplate>
    );
};
