import { expect, test } from '@playwright/test';

import { runArtisan } from '../support/db';
import { authStatePath } from '../support/thesis-fixtures';

function resetCsatResponsesForUser(email: string): void {
    runArtisan([
        'tinker',
        `--execute=$user = App\\Models\\User::query()->where("email", "${email}")->firstOrFail(); App\\Models\\CsatResponse::query()->where("user_id", $user->id)->delete();`,
    ]);
}

test.describe('CSAT flow', () => {
    test('mahasiswa can submit CSAT once and admin can review the saved response in Filament', async ({
        browser,
    }) => {
        resetCsatResponsesForUser('farhan@sita.test');

        const mahasiswaContext = await browser.newContext({
            storageState: authStatePath('farhan'),
        });
        const adminContext = await browser.newContext({
            storageState: authStatePath('superadmin'),
        });

        const kritik = `CSAT kritik Playwright ${Date.now()}`;
        const saran = `CSAT saran Playwright ${Date.now()}`;

        try {
            const mahasiswaPage = await mahasiswaContext.newPage();
            const adminPage = await adminContext.newPage();

            await mahasiswaPage.goto('/settings/csat');
            await expect(mahasiswaPage).toHaveTitle(/CSAT/i);
            await expect(
                mahasiswaPage.getByText('Kepuasan pengguna (CSAT)').first(),
            ).toBeVisible();
            await expect(
                mahasiswaPage.getByText('Limit 1 submit/').first(),
            ).toBeVisible();

            await mahasiswaPage
                .getByRole('button', { name: /4\. Puas/i })
                .click();
            await mahasiswaPage.getByLabel('Kritik').fill(kritik);
            await mahasiswaPage.getByLabel('Saran').fill(saran);
            await mahasiswaPage
                .getByRole('button', { name: 'Kirim CSAT' })
                .click();

            await expect(
                mahasiswaPage.getByText('Feedback berhasil dikirim').first(),
            ).toBeVisible();
            await expect(
                mahasiswaPage
                    .getByText(
                        'Terima kasih. Umpan balik Anda sudah tersimpan.',
                    )
                    .first(),
            ).toBeVisible();
            await expect(
                mahasiswaPage.getByText('Anda belum bisa submit lagi').first(),
            ).toBeVisible();
            await expect(
                mahasiswaPage.getByText('Skor terakhir Anda: 4/5.').first(),
            ).toBeVisible();
            await expect(
                mahasiswaPage.getByRole('button', { name: 'Kirim CSAT' }),
            ).toBeDisabled();

            await adminPage.goto('/admin/csat-responses');
            await expect(adminPage).toHaveURL(/\/admin\/csat-responses/);
            await expect(
                adminPage.getByRole('heading', { name: 'CSAT & Umpan Balik' }),
            ).toBeVisible();

            const searchInput = adminPage.getByRole('searchbox', {
                name: 'Search',
                exact: true,
            });
            await expect(searchInput).toBeVisible();
            await searchInput.fill('Farhan Maulana');
            await searchInput.press('Enter');

            const responseRow = adminPage
                .locator('tr')
                .filter({ hasText: 'Farhan Maulana' })
                .first();

            await expect(responseRow).toBeVisible();
            await expect(responseRow).toContainText('Mahasiswa');
            await expect(responseRow).toContainText('Ilmu Komputer');
            await expect(responseRow).toContainText('4/5');
            await expect(responseRow).toContainText(kritik);
        } finally {
            await mahasiswaContext.close();
            await adminContext.close();
        }
    });
});
