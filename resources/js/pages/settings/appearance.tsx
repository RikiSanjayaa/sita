import { Head } from '@inertiajs/react';

import AppearanceSettingsPanel from '@/components/settings/appearance-settings-panel';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { makeSettingsBreadcrumbs } from '@/pages/settings/breadcrumbs';
import { edit as editAppearance } from '@/routes/appearance';

const breadcrumbs = makeSettingsBreadcrumbs('Tampilan', editAppearance().url);

export default function Appearance() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pengaturan tampilan" />

            <SettingsLayout>
                <AppearanceSettingsPanel />
            </SettingsLayout>
        </AppLayout>
    );
}
