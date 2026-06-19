import { expect, test, type Locator, type Page } from '@playwright/test';

import { runArtisan } from '../support/db';
import { authStatePath } from '../support/thesis-fixtures';

function resetAnnouncementsAndNotifications(email: string): void {
    runArtisan([
        'tinker',
        `--execute=$user = App\\Models\\User::query()->where("email", "${email}")->firstOrFail(); $user->notifications()->delete(); App\\Models\\SystemAnnouncement::query()->delete();`,
    ]);
}

async function openNotifications(page: Page): Promise<Locator> {
    await page.getByRole('button', { name: 'Open notifications' }).click();

    const panel = page.getByRole('dialog').last();

    await expect(panel.getByText('Notifikasi').first()).toBeVisible();

    return panel;
}

test.describe('System announcements', () => {
    test('admin can publish an announcement and targeted mahasiswa sees it in notifications', async ({
        browser,
    }) => {
        resetAnnouncementsAndNotifications('farhan@sita.test');

        const adminContext = await browser.newContext({
            storageState: authStatePath('superadmin'),
        });
        const mahasiswaContext = await browser.newContext({
            storageState: authStatePath('farhan'),
        });

        const title = `Pengumuman Playwright ${Date.now()}`;
        const body =
            'Maintenance akademik terjadwal malam ini pukul 22.00 WITA.';

        try {
            const adminPage = await adminContext.newPage();
            const mahasiswaPage = await mahasiswaContext.newPage();

            await adminPage.goto('/admin/system-announcements/create');
            await expect(adminPage).toHaveURL(
                /\/admin\/system-announcements\/create/,
            );
            await expect(adminPage.getByLabel('Judul')).toBeVisible();

            await adminPage.getByLabel('Judul').fill(title);
            await adminPage.getByLabel('Isi Pengumuman').fill(body);
            await adminPage
                .getByLabel('URL Tujuan')
                .fill('/mahasiswa/dashboard');
            await adminPage
                .getByRole('checkbox', { name: 'Mahasiswa' })
                .check();
            await adminPage.getByRole('button', { name: 'Draft' }).click();
            await adminPage
                .getByRole('option', { name: 'Publish sekarang', exact: true })
                .click();
            await adminPage
                .getByRole('button', { name: 'Create', exact: true })
                .click();

            await expect(adminPage).toHaveURL(/\/admin\/system-announcements$/);
            await expect(adminPage.getByText(title).first()).toBeVisible();
            await expect(
                adminPage.getByText('Published').first(),
            ).toBeVisible();
            await expect(
                adminPage.getByText('Mahasiswa').first(),
            ).toBeVisible();

            await mahasiswaPage.goto('/mahasiswa/dashboard');
            await expect(mahasiswaPage).toHaveTitle(/Dashboard/i);

            const notificationPanel = await openNotifications(mahasiswaPage);
            await expect(
                notificationPanel.getByText(title).first(),
            ).toBeVisible();
            await expect(
                notificationPanel.getByText(body).first(),
            ).toBeVisible();

            await notificationPanel
                .locator('button')
                .filter({ hasText: title })
                .first()
                .click();

            await expect(mahasiswaPage).toHaveURL(/\/mahasiswa\/dashboard/);
        } finally {
            await adminContext.close();
            await mahasiswaContext.close();
        }
    });

    test('announcement body links remain clickable without an action URL', async ({
        browser,
    }) => {
        resetAnnouncementsAndNotifications('farhan@sita.test');

        const adminContext = await browser.newContext({
            storageState: authStatePath('superadmin'),
        });
        const mahasiswaContext = await browser.newContext({
            storageState: authStatePath('farhan'),
        });
        const title = `Pengumuman Tautan ${Date.now()}`;
        const firstUrl = 'https://example.com/panduan';
        const secondUrl = 'www.example.org/bantuan';
        const body = `Baca ${firstUrl} atau ${secondUrl}.`;

        try {
            const adminPage = await adminContext.newPage();
            const mahasiswaPage = await mahasiswaContext.newPage();

            await adminPage.goto('/admin/system-announcements/create');
            await adminPage.getByLabel('Judul').fill(title);
            await adminPage.getByLabel('Isi Pengumuman').fill(body);
            await adminPage
                .getByRole('checkbox', { name: 'Mahasiswa' })
                .check();
            await adminPage.getByRole('button', { name: 'Draft' }).click();
            await adminPage
                .getByRole('option', { name: 'Publish sekarang', exact: true })
                .click();
            await adminPage
                .getByRole('button', { name: 'Create', exact: true })
                .click();

            await mahasiswaPage.goto('/mahasiswa/dashboard');
            const notificationPanel = await openNotifications(mahasiswaPage);
            const firstLink = notificationPanel.getByRole('link', {
                name: firstUrl,
            });
            const secondLink = notificationPanel.getByRole('link', {
                name: secondUrl,
            });

            await expect(firstLink).toHaveAttribute('href', firstUrl);
            await expect(firstLink).toHaveAttribute('target', '_blank');
            await expect(secondLink).toHaveAttribute(
                'href',
                `https://${secondUrl}`,
            );
            await expect(secondLink).toHaveAttribute('target', '_blank');

            const popupPromise = mahasiswaPage.waitForEvent('popup');
            await firstLink.click();
            const popup = await popupPromise;
            await popup.close();

            await expect(
                notificationPanel.getByText('0', { exact: true }),
            ).toBeVisible();
        } finally {
            await adminContext.close();
            await mahasiswaContext.close();
        }
    });
});
