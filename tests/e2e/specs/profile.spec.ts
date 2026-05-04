import { expect, test } from '@playwright/test';

import { runArtisan } from '../support/db';
import { authStatePath } from '../support/thesis-fixtures';

function resetProfileForUser(email: string): void {
    runArtisan([
        'tinker',
        `--execute=$user = App\\Models\\User::query()->where("email", "${email}")->firstOrFail(); $user->forceFill(["name" => "Farhan Maulana", "email" => "farhan@sita.test", "phone_number" => null])->save();`,
    ]);
}

test.describe('Profile settings', () => {
    test('user can update profile fields and see them persisted after reload', async ({
        browser,
    }) => {
        resetProfileForUser('farhan@sita.test');

        const context = await browser.newContext({
            storageState: authStatePath('farhan'),
        });

        const nextName = 'Farhan Maulana Playwright';
        const nextEmail = 'farhan.playwright@sita.test';
        const nextPhone = '081234560001';

        try {
            const page = await context.newPage();

            await page.goto('/settings/profile');
            await expect(page).toHaveTitle(/Profil Saya/i);
            await expect(
                page.getByRole('heading', { name: 'Perbarui Profil' }),
            ).toBeVisible();

            await page.getByLabel('Nama lengkap').fill(nextName);
            await page.getByLabel('Email').fill(nextEmail);
            await page.getByLabel('Nomor HP').fill(nextPhone);
            await page
                .getByRole('button', { name: 'Simpan perubahan' })
                .click();

            await expect(page).toHaveURL(/\/settings\/profile/);
            await expect(page.getByLabel('Nama lengkap')).toHaveValue(nextName);
            await expect(page.getByLabel('Email')).toHaveValue(nextEmail);
            await expect(page.getByLabel('Nomor HP')).toHaveValue(nextPhone);

            await page.reload();

            await expect(page.getByLabel('Nama lengkap')).toHaveValue(nextName);
            await expect(page.getByLabel('Email')).toHaveValue(nextEmail);
            await expect(page.getByLabel('Nomor HP')).toHaveValue(nextPhone);

            await page
                .locator('[data-test="header-user-menu-trigger"]')
                .click();
            await expect(page.getByText(nextName).first()).toBeVisible();
            await expect(page.getByText(nextEmail).first()).toBeVisible();
        } finally {
            await context.close();
        }
    });
});
