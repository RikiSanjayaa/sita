import { expect, test, type Page } from '@playwright/test';

import { authStatePath } from '../support/thesis-fixtures';

const COOKIE = 'sita_theme_preset';

const dataTheme = (page: Page): Promise<string | undefined> =>
    page.evaluate(() => document.documentElement.dataset.theme);

const computedPrimary = (page: Page): Promise<string> =>
    page.evaluate(() =>
        getComputedStyle(document.documentElement)
            .getPropertyValue('--primary')
            .trim(),
    );

const themeCookie = async (page: Page): Promise<string | undefined> =>
    (await page.context().cookies()).find(({ name }) => name === COOKIE)?.value;

async function openPublicPicker(page: Page): Promise<void> {
    await page.goto('/');
    await page
        .getByRole('button', { name: 'Buka pengaturan tampilan' })
        .click();
    await expect(
        page.getByRole('heading', { name: 'Skema warna' }),
    ).toBeVisible();
}

test.describe('Theme scheme, guest', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('default is UBG Blue and the picker lists every scheme without an "Aktif" badge', async ({
        page,
    }) => {
        await openPublicPicker(page);

        expect(await dataTheme(page)).toBe('ubg-blue');
        for (const label of ['UBG Blue', 'Indigo', 'Zaitun', 'Grafit']) {
            await expect(
                page.getByRole('button', { name: label }),
            ).toBeVisible();
        }
        await expect(
            page.getByRole('button', { name: 'UBG Blue' }),
        ).toHaveAttribute('aria-pressed', 'true');
        await expect(page.getByText('Aktif', { exact: true })).toHaveCount(0);
    });

    test('choosing a scheme sets data-theme from CSS tokens, never inline colours', async ({
        page,
    }) => {
        await openPublicPicker(page);
        const originalPrimary = await computedPrimary(page);
        await page.getByRole('button', { name: 'Indigo' }).click();

        await expect(
            page.getByRole('button', { name: 'Indigo' }),
        ).toHaveAttribute('aria-pressed', 'true');
        expect(await dataTheme(page)).toBe('indigo');
        expect(await computedPrimary(page)).not.toBe(originalPrimary);
        await expect(page.locator('html')).not.toHaveAttribute(
            'style',
            /--primary|--background|--ring/,
        );
        expect(
            await page.evaluate(() =>
                localStorage.getItem('sita_theme_preset'),
            ),
        ).toBe('indigo');
        expect(await themeCookie(page)).toBe('indigo');
    });

    test('dark mode switches to the scheme dark tokens', async ({ page }) => {
        await openPublicPicker(page);
        await page.getByRole('button', { name: 'Grafit' }).click();
        const lightPrimary = await computedPrimary(page);
        await page.getByRole('button', { name: 'Gelap' }).click();

        await expect(page.locator('html')).toHaveClass(/dark/);
        expect(await computedPrimary(page)).not.toBe(lightPrimary);
    });

    test('the server renders the saved scheme into the first HTML, on login too', async ({
        page,
    }) => {
        await openPublicPicker(page);
        await page.getByRole('button', { name: 'Grafit' }).click();
        const savedPrimary = await computedPrimary(page);

        const html = await (await page.context().request.get('/login')).text();
        expect(html).toMatch(/<html[^>]*data-theme="graphite"/);

        await page.goto('/login');
        expect(await dataTheme(page)).toBe('graphite');
        expect(await computedPrimary(page)).toBe(savedPrimary);
    });

    test('a reload keeps the scheme', async ({ page }) => {
        await openPublicPicker(page);
        await page.getByRole('button', { name: 'Indigo' }).click();
        await page.reload();

        expect(await dataTheme(page)).toBe('indigo');
    });

    test('storage restores the scheme before paint when the cookie is gone', async ({
        page,
    }) => {
        await openPublicPicker(page);
        await page.getByRole('button', { name: 'Indigo' }).click();
        await page.context().clearCookies({ name: COOKIE });

        // The inline head script runs while <body> does not exist yet, long before
        // the stylesheet or the app bundle, so a change seen then is pre-paint.
        await page.addInitScript(() => {
            new MutationObserver(() => {
                if (
                    document.documentElement.dataset.theme === 'indigo' &&
                    !document.body
                ) {
                    sessionStorage.setItem('indigo-prepaint', 'yes');
                }
            }).observe(document, {
                subtree: true,
                attributes: true,
                attributeFilter: ['data-theme'],
            });
        });
        await page.goto('/login');

        expect(
            await page.evaluate(() =>
                sessionStorage.getItem('indigo-prepaint'),
            ),
        ).toBe('yes');
        await expect.poll(() => themeCookie(page)).toBe('indigo');
    });

    test('an unknown cookie or stored value falls back to UBG Blue', async ({
        context,
        baseURL,
        page,
    }) => {
        await context.addCookies([
            { name: COOKIE, value: 'emerald', url: baseURL as string },
        ]);
        await page.addInitScript(() => {
            localStorage.setItem('sita_theme_preset', 'sunset');
        });
        await page.goto('/login');

        expect(await dataTheme(page)).toBe('ubg-blue');
        expect(await themeCookie(page)).toBe('ubg-blue');
    });

    for (const retired of ['rose', 'aubergine', 'plum', 'sepia']) {
        test(`saved retired scheme ${retired} falls back to UBG Blue`, async ({
            context,
            baseURL,
            page,
        }) => {
            await context.addCookies([
                { name: COOKIE, value: retired, url: baseURL as string },
            ]);
            await page.addInitScript((value) => {
                localStorage.setItem('sita_theme_preset', value);
            }, retired);
            await page.goto('/login');

            expect(await dataTheme(page)).toBe('ubg-blue');
            expect(await themeCookie(page)).toBe('ubg-blue');
            await openPublicPicker(page);
            await expect(
                page.locator('[data-theme="' + retired + '"]'),
            ).toHaveCount(0);
        });
    }
});

test.describe('Theme scheme, signed in', () => {
    test.use({ storageState: authStatePath('mahasiswa') });

    test('the settings picker is the same component and the choice follows every page', async ({
        page,
    }) => {
        await page.goto('/settings/appearance');

        await expect(
            page.getByRole('heading', { name: 'Skema warna' }),
        ).toBeVisible();
        await expect(
            page.getByRole('heading', { name: 'Mode tampilan' }),
        ).toBeVisible();
        await expect(page.getByText('Aktif', { exact: true })).toHaveCount(0);

        await page.getByRole('button', { name: 'Indigo' }).click();
        await expect(
            page.getByRole('button', { name: 'Indigo' }),
        ).toHaveAttribute('aria-pressed', 'true');

        await page.goto('/mahasiswa/dashboard');
        expect(await dataTheme(page)).toBe('indigo');

        await page.goto('/');
        expect(await dataTheme(page)).toBe('indigo');

        await page.goto('/settings/appearance');
        await page.getByRole('button', { name: 'UBG Blue' }).click();
        expect(await dataTheme(page)).toBe('ubg-blue');
    });
});
