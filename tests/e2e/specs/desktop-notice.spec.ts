import { expect, test, type Page } from '@playwright/test';

import { authStatePath } from '../support/thesis-fixtures';

// Phones get a dismissible notice on portal pages; desktop, public pages,
// login and the Filament panel do not. Each test uses a fresh context, so
// sessionStorage starts empty.
const notice = (page: Page) =>
    page.getByText('Tampilan SiTA paling nyaman di laptop atau komputer.');

test.describe('Desktop notice on small screens', () => {
    test.describe('portal at 390px', () => {
        test.use({
            storageState: authStatePath('mahasiswa'),
            viewport: { width: 390, height: 844 },
        });

        test('shows, can be dismissed, and stays dismissed for the session', async ({
            page,
        }) => {
            await page.goto('/mahasiswa/dashboard');
            await expect(notice(page)).toBeVisible();

            await page
                .getByRole('button', { name: 'Tutup pemberitahuan' })
                .click();
            await expect(notice(page)).toHaveCount(0);

            await page.reload();
            await expect(
                page.getByRole('button', { name: 'Toggle Sidebar' }).first(),
            ).toBeAttached();
            await expect(notice(page)).toHaveCount(0);

            await page.goto('/settings/profile');
            await expect(notice(page)).toHaveCount(0);

            // A new browser session (empty sessionStorage) shows it again.
            await page.evaluate(() => sessionStorage.clear());
            await page.reload();
            await expect(notice(page)).toBeVisible();
        });
    });

    test.describe('portal at 1280px', () => {
        test.use({
            storageState: authStatePath('mahasiswa'),
            viewport: { width: 1280, height: 800 },
        });

        test('is not shown on desktop', async ({ page }) => {
            await page.goto('/mahasiswa/dashboard');
            await expect(page).toHaveURL(/\/mahasiswa\/dashboard$/);
            await expect(page.locator('h1').first()).toBeVisible();
            await expect(notice(page)).toHaveCount(0);
        });
    });

    test.describe('outside the portal at 390px', () => {
        test.use({
            storageState: { cookies: [], origins: [] },
            viewport: { width: 390, height: 844 },
        });

        test('is not shown on public pages or login', async ({ page }) => {
            for (const path of ['/', '/jadwal', '/login']) {
                await page.goto(path);
                await expect(page.getByRole('heading').first()).toBeVisible();
                await expect(notice(page)).toHaveCount(0);
            }
        });
    });

    test.describe('admin panel at 390px', () => {
        test.use({
            storageState: authStatePath('admin'),
            viewport: { width: 390, height: 844 },
        });

        test('is not shown in the Filament panel', async ({ page }) => {
            await page.goto('/admin');
            await expect(page).toHaveURL(/\/admin/);
            await expect(page.locator('main').first()).toBeVisible();
            await expect(notice(page)).toHaveCount(0);
        });
    });
});
