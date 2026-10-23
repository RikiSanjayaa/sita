import { Head } from '@inertiajs/react';

import NotificationSettingsPanel from '@/components/settings/notification-settings-panel';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { makeSettingsBreadcrumbs } from '@/pages/settings/breadcrumbs';

const breadcrumbs = makeSettingsBreadcrumbs(
    'Notifikasi',
    '/settings/notifications',
);

export default function NotificationsPage() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pengaturan notifikasi" />

            <SettingsLayout>
                <NotificationSettingsPanel />
            </SettingsLayout>
        </AppLayout>
    );
}
