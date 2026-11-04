import { expect, test } from '@playwright/test';

import { authStatePath } from '../support/thesis-fixtures';

test.use({ storageState: authStatePath('kaprodi') });

test('kaprodi opens the scheduled packet and downloads a filtered report', async ({
    page,
}, testInfo) => {
    await page.goto('/kaprodi/sempro-sidang');
    await page
        .getByPlaceholder('Cari nama, NIM, judul, atau dosen...')
        .fill('Sinta Sidang Terjadwal');
    const row = page.getByRole('row').filter({
        has: page.getByRole('cell', { name: /^Sidang .*#/ }),
        hasText: 'Sinta Sidang Terjadwal',
    });
    await row.getByRole('button', { name: 'Aksi lainnya' }).click();
    await page.getByRole('menuitem', { name: 'Kelola berkas sidang' }).click();
    await expect(
        page.getByRole('heading', { name: /Berkas Sidang/ }),
    ).toBeVisible();
    await expect(page.getByText(/membutuhkan dua penguji/)).toBeVisible();

    for (const width of [1280, 390]) {
        await page.setViewportSize({ width, height: 920 });
        for (const theme of ['light', 'dark'] as const) {
            await page.emulateMedia({ colorScheme: theme });
            await page.evaluate((mode) => {
                document.documentElement.classList.toggle(
                    'dark',
                    mode === 'dark',
                );
            }, theme);
            expect(
                await page.evaluate(() => document.documentElement.scrollWidth),
            ).toBeLessThanOrEqual(width);
            await testInfo.attach(`berkas-${width}-${theme}`, {
                body: await page.screenshot({ fullPage: true }),
                contentType: 'image/png',
            });
        }
    }

    const popup = page.waitForEvent('popup');
    await page.getByRole('link', { name: 'Berita Acara', exact: true }).click();
    const printPage = await popup;
    await expect(
        printPage.getByRole('heading', { name: /Berita Acara Sidang/ }),
    ).toBeVisible();
    await expect(printPage.getByText('Sinta Sidang Terjadwal')).toBeVisible();
    await expect(
        printPage.getByText('Ruang Sidang E2E', { exact: false }),
    ).toBeVisible();
    await printPage.close();

    await page.setViewportSize({ width: 1280, height: 920 });
    await page.goto('/kaprodi/sempro-sidang');
    await page.getByRole('button', { name: 'Rekap Nilai' }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Dari tanggal').fill('2026-11-01');
    await dialog.getByLabel('Sampai tanggal').fill('2026-11-30');
    const downloaded = page.waitForEvent('download');
    await dialog.getByRole('button', { name: 'Unduh Excel' }).click();
    expect((await downloaded).suggestedFilename()).toBe(
        'rekap-nilai-sidang.xlsx',
    );
});
