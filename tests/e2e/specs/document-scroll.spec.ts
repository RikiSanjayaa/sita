import { expect, test, type Page } from '@playwright/test';

import { authStatePath } from '../support/thesis-fixtures';

// #126: portal pages scroll the document, not <main data-slot="sidebar-inset">.

type Metrics = {
    documentHeight: number;
    viewportHeight: number;
    mainScrollHeight: number;
    mainClientHeight: number;
    mainOverflowY: string;
};

async function metrics(page: Page): Promise<Metrics> {
    return page.evaluate(() => {
        const main = document.querySelector<HTMLElement>(
            '[data-slot="sidebar-inset"]',
        )!;

        return {
            documentHeight: document.scrollingElement!.scrollHeight,
            viewportHeight: window.innerHeight,
            mainScrollHeight: main.scrollHeight,
            mainClientHeight: main.clientHeight,
            mainOverflowY: getComputedStyle(main).overflowY,
        };
    });
}

const widths = [1280, 390];

test.describe('document scroll on a long portal page (#126)', () => {
    test.use({ storageState: authStatePath('kaprodi') });

    for (const width of widths) {
        test(`${width}px kaprodi mahasiswa scrolls the document`, async ({
            browser,
        }) => {
            const context = await browser.newContext({
                viewport: { width, height: 800 },
                storageState: authStatePath('kaprodi'),
            });
            const page = await context.newPage();
            await page.goto('/kaprodi/mahasiswa');
            await expect(
                page.locator('main table tbody tr').first(),
            ).toBeVisible();

            const before = await metrics(page);
            expect(before.mainOverflowY).toBe('visible');
            expect(before.documentHeight).toBeGreaterThan(
                before.viewportHeight,
            );
            // <main> grows with its content, so it never scrolls on its own.
            expect(before.mainScrollHeight).toBeLessThanOrEqual(
                before.mainClientHeight,
            );
            expect(before.documentHeight).toBeGreaterThanOrEqual(
                before.mainClientHeight,
            );

            const shot = await page.screenshot({ fullPage: true });
            const after = await metrics(page);
            expect(shot.readUInt32BE(20)).toBe(after.documentHeight);
            expect(shot.readUInt32BE(20)).toBeGreaterThan(
                before.viewportHeight * 1.5,
            );

            await page.mouse.wheel(0, 600);
            await expect
                .poll(() =>
                    page.evaluate(() => document.scrollingElement!.scrollTop),
                )
                .toBeGreaterThan(300);

            // Header stays pinned to the viewport while the document scrolls.
            const header = page.locator('[data-slot="sidebar-inset"] > header');
            await expect
                .poll(async () => (await header.boundingBox())!.y)
                .toBe(0);

            await context.close();
        });
    }
});

test.describe('chat stays a single screen (#126)', () => {
    for (const width of widths) {
        test(`${width}px mahasiswa pesan keeps the composer in view`, async ({
            browser,
        }) => {
            const context = await browser.newContext({
                viewport: { width, height: 800 },
                storageState: authStatePath('mahasiswa'),
            });
            const page = await context.newPage();
            await page.goto('/mahasiswa/pesan');
            await page
                .locator('button')
                .filter({ hasText: 'Bimbingan' })
                .first()
                .click();
            const composer = page.getByPlaceholder('Tulis pesan...');
            await expect(composer).toBeVisible();

            const data = await metrics(page);
            expect(data.documentHeight).toBeLessThanOrEqual(
                data.viewportHeight,
            );

            const box = await composer.boundingBox();
            expect(box!.y + box!.height).toBeLessThanOrEqual(
                data.viewportHeight,
            );

            await context.close();
        });
    }
});
