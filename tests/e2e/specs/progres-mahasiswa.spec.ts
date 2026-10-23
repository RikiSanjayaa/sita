import { expect, test } from '@playwright/test';

import { seedPlaywrightScenarios } from '../support/db';
import { authStatePath } from '../support/thesis-fixtures';

// Progres Mahasiswa (/progres-mahasiswa) is read-only and open to every logged-in
// role. Search, filters and the detail link are all server round trips, so
// assertions read the URL and the rendered table rather than client state.
const rows = 'tbody tr';

test.describe('Progres Mahasiswa', () => {
    test.describe.configure({ mode: 'serial' });

    test.beforeAll(() => {
        seedPlaywrightScenarios();
    });

    test.describe('guest', () => {
        test.use({ storageState: { cookies: [], origins: [] } });

        test('redirects to login', async ({ page }) => {
            await page.goto('/progres-mahasiswa');

            await expect(page).toHaveURL(/\/login/);
        });
    });

    test.describe('mahasiswa', () => {
        test.use({ storageState: authStatePath('mahasiswa') });

        test('opens, searches, filters and opens a detail', async ({
            page,
        }) => {
            await page.goto('/progres-mahasiswa');

            await expect(
                page.getByRole('heading', { name: 'Progres Mahasiswa' }),
            ).toBeVisible();
            await expect(page.locator(rows).first()).toBeVisible();

            const firstName = (
                await page.locator(`${rows} a`).first().innerText()
            ).trim();

            // Search runs on the server and is carried in the URL.
            await page
                .getByPlaceholder('Cari nama, NIM, atau prodi...')
                .fill(firstName);
            await expect(page).toHaveURL(/search=/);
            await expect(page.locator(rows).first()).toContainText(firstName);

            await page
                .getByPlaceholder('Cari nama, NIM, atau prodi...')
                .fill('zzz-tidak-ada-998877');
            await expect(page).toHaveURL(/search=zzz-tidak-ada-998877/);
            await expect(page.locator(rows)).toHaveCount(0);

            await page
                .getByPlaceholder('Cari nama, NIM, atau prodi...')
                .fill('');
            await expect(page.locator(rows).first()).toBeVisible();

            // Phase pills are a single-select group; the second pill is a real phase.
            const phasePills = page.getByRole('group', {
                name: 'Tahap mahasiswa',
            });
            await phasePills.getByRole('radio').nth(1).click();
            await expect(page).toHaveURL(/phase=/);

            await phasePills.getByRole('radio').first().click();
            await expect(page).not.toHaveURL(/phase=/);

            // Program studi select filters in SQL and is reflected in the URL.
            await page.getByRole('combobox', { name: 'Program studi' }).click();
            await page.getByRole('option').nth(1).click();
            await expect(page).toHaveURL(/prodi=/);

            // Opening a detail leaves the list for the student's profile.
            await page.goto('/progres-mahasiswa');
            await page.locator(`${rows} a`).first().click();
            await expect(page).not.toHaveURL(/\/progres-mahasiswa/);
        });
    });

    test.describe('dosen', () => {
        test.use({ storageState: authStatePath('dosen1') });

        test('opens the progress list', async ({ page }) => {
            await page.goto('/progres-mahasiswa');

            await expect(
                page.getByRole('heading', { name: 'Progres Mahasiswa' }),
            ).toBeVisible();
        });
    });

    test.describe('kaprodi', () => {
        test.use({ storageState: authStatePath('kaprodi') });

        test('opens the progress list', async ({ page }) => {
            await page.goto('/progres-mahasiswa');

            await expect(
                page.getByRole('heading', { name: 'Progres Mahasiswa' }),
            ).toBeVisible();
        });
    });
});
