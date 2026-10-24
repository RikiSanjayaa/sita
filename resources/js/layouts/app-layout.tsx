import { usePage } from '@inertiajs/react';
import { CalendarX2 } from 'lucide-react';
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
                <div className="mx-auto w-full max-w-7xl px-4 pt-4 md:px-6">
                    <Alert variant="destructive">
                        <CalendarX2 aria-hidden />
                        <AlertTitle>Masa aktif bimbingan berakhir</AlertTitle>
                        <AlertDescription className="text-pretty">
                            {mentorshipTicket.message}
                        </AlertDescription>
                    </Alert>
                </div>
            )}
            {children}
        </AppLayoutTemplate>
    );
};
