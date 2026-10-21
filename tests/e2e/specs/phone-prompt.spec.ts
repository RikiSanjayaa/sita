import { expect, test } from '@playwright/test';

import { seedPlaywrightScenarios } from '../support/db';
import { authStatePath } from '../support/thesis-fixtures';

// A mahasiswa without a phone number is seeded through the dedicated
// `phonePrompt` account. Seeding runs again before this spec, so each run
// starts with an empty phone number.
test.use({ storageState: authStatePath('phonePrompt') });

test.describe('Mahasiswa phone number prompt', () => {
    test.beforeAll(() => {
        seedPlaywrightScenarios();
    });

    test('new mahasiswa login shows the pop-up, which closes only after a valid number is saved', async ({
        page,
    }) => {
        await page.goto('/mahasiswa/dashboard');

        const dialog = page.getByRole('dialog', {
            name: 'Lengkapi nomor HP',
        });
        await expect(dialog).toBeVisible();

        await page.keyboard.press('Escape');
        await expect(dialog).toBeVisible();

        await page.mouse.click(5, 5);
        await expect(dialog).toBeVisible();

        await dialog.getByRole('textbox', { name: 'Nomor HP' }).fill('12345');
        await page.getByRole('button', { name: 'Simpan nomor' }).click();
        await expect(
            dialog.getByText('Nomor HP harus berupa nomor seluler Indonesia'),
        ).toBeVisible();
        await expect(dialog).toBeVisible();

        await dialog
            .getByRole('textbox', { name: 'Nomor HP' })
            .fill('+62 812-3456-7890');
        await page.getByRole('button', { name: 'Simpan nomor' }).click();
        await expect(dialog).toBeHidden();

        await page.reload();
        await expect(page).toHaveURL(/\/mahasiswa\/dashboard/);
        await expect(
            page.getByRole('dialog', { name: 'Lengkapi nomor HP' }),
        ).toBeHidden();
    });
});
