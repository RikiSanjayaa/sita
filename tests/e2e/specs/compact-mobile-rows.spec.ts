import { expect, test } from '@playwright/test';

import { authStatePath } from '../support/thesis-fixtures';

// #129: below md a stackedTable row is a compact entry (identity and actions
// on the first line, secondary values on a meta line), not one block per cell.

test.describe('compact mobile table rows (#129)', () => {
    test('390px kaprodi rows pair short facts without clipping long progress', async ({
        browser,
    }) => {
        const context = await browser.newContext({
            viewport: { width: 390, height: 844 },
            storageState: authStatePath('kaprodi'),
        });
        const page = await context.newPage();
        await page.goto('/kaprodi/mahasiswa');

        const rows = page.locator('main table tbody tr');
        await expect(rows.first()).toBeVisible();

        for (const row of await rows.all()) {
            const cohort = await row.locator('td').nth(1).boundingBox();
            const risk = await row.locator('td').nth(3).boundingBox();
            expect(Math.abs(cohort!.y - risk!.y)).toBeLessThan(2);
            expect(risk!.x).toBeGreaterThan(cohort!.x);
        }
        expect(
            await page.evaluate(() => document.documentElement.scrollWidth),
        ).toBeLessThanOrEqual(390);

        // Identity and the row-actions menu share the first line.
        const first = rows.first();
        const identity = await first.locator('td').first().boundingBox();
        const actions = await first
            .getByRole('button', { name: 'Aksi lainnya' })
            .boundingBox();
        expect(identity).not.toBeNull();
        expect(actions).not.toBeNull();
        expect(Math.abs(actions!.y - identity!.y)).toBeLessThan(40);
        expect(actions!.height).toBeGreaterThanOrEqual(44);

        await context.close();
    });

    test('1280px kaprodi mahasiswa keeps the table layout', async ({
        browser,
    }) => {
        const context = await browser.newContext({
            viewport: { width: 1280, height: 800 },
            storageState: authStatePath('kaprodi'),
        });
        const page = await context.newPage();
        await page.goto('/kaprodi/mahasiswa');

        const row = page.locator('main table tbody tr').first();
        await expect(row).toBeVisible();
        await expect(
            page.locator('main table thead').getByRole('columnheader').first(),
        ).toBeVisible();

        const cells = await row
            .locator('td')
            .evaluateAll((nodes) =>
                nodes.map((node) => node.getBoundingClientRect().top),
            );
        expect(new Set(cells.map(Math.round)).size).toBe(1);

        await context.close();
    });
});
