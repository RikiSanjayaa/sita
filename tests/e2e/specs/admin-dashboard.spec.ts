import { expect, test } from '@playwright/test';

import { lapseMentorship, seedDocuments } from '../support/admin-dashboard';
import { seedPlaywrightScenarios } from '../support/db';
import { authStatePath } from '../support/thesis-fixtures';

test.use({ storageState: authStatePath('admin') });

const lapsedStudent = 'Bagas Saputra';
const documentStudent = 'Mahasiswa SiTA';

test.describe('admin dashboard and thesis project detail', () => {
    test.beforeAll(() => {
        seedPlaywrightScenarios();
        lapseMentorship('bagas@sita.test');
        seedDocuments('mahasiswa@sita.test');
    });

    test('the dashboard leads with the action queue, then the agenda, then statistics', async ({
        page,
    }) => {
        await page.goto('/admin');

        const queue = page.getByRole('heading', { name: 'Perlu tindakan' });
        const agenda = page.getByRole('heading', {
            name: /Agenda ujian 7 hari ke depan/,
        });

        await expect(queue).toBeVisible();
        await expect(agenda).toBeVisible();

        const queueBox = await queue.boundingBox();
        const agendaBox = await agenda.boundingBox();
        const statsBox = await page
            .getByText('Sebaran Fase Tugas Akhir')
            .first()
            .boundingBox();

        expect(queueBox!.y).toBeLessThan(agendaBox!.y);
        expect(agendaBox!.y).toBeLessThan(statsBox!.y);

        await expect(page.getByText('Judul menunggu review')).toBeVisible();
        await expect(
            page.getByText('Masa aktif bimbingan habis'),
        ).toBeVisible();
    });

    test('a queue row opens the thesis project list filtered, and the lapsed masa aktif can be renewed', async ({
        page,
    }) => {
        await page.goto('/admin');

        await page
            .locator('.sita-action-queue-row')
            .filter({ hasText: 'Masa aktif bimbingan habis' })
            .getByRole('link', { name: /Perpanjang/ })
            .click();

        await expect(page).toHaveURL(/action_queue/);

        const row = page.locator('tr').filter({ hasText: lapsedStudent });

        await expect(row).toBeVisible();
        await expect(
            page.locator('tr').filter({ hasText: documentStudent }),
        ).toHaveCount(0);

        await row
            .getByRole('button', { name: 'Perpanjang masa aktif' })
            .click();
        await page.getByRole('button', { name: /confirm|konfirmasi/i }).click();

        // The renewed project drops out of the filtered list.
        await expect(row).toHaveCount(0);

        await page.goto('/admin');
        await expect(
            page
                .locator('.sita-action-queue-row')
                .filter({ hasText: 'Masa aktif bimbingan habis' }),
        ).not.toContainText(lapsedStudent);
    });

    test('the documents table scrolls sideways and keeps the download action in view', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 1366, height: 768 });
        await page.goto(
            `/admin/thesis-projects?search=${encodeURIComponent(documentStudent)}`,
        );
        await page
            .locator('tr')
            .filter({ hasText: documentStudent })
            .first()
            .getByRole('link', { name: /view/i })
            .click();

        await expect(page.getByRole('tab', { name: 'Ujian' })).toBeVisible();
        await expect(page.getByRole('tab', { name: 'Workflow' })).toHaveCount(
            0,
        );

        await page.getByRole('tab', { name: 'Dokumen' }).click();

        const container = page.locator('.sita-detail-table-sticky-end').first();

        await expect(container).toBeVisible();

        const overflow = await container.evaluate((element) => ({
            clientWidth: element.clientWidth,
            scrollWidth: element.scrollWidth,
        }));

        expect(overflow.scrollWidth).toBeGreaterThan(overflow.clientWidth);

        // Headers never wrap per letter: every header cell stays on one line.
        const headerHeights = await container
            .locator('thead th')
            .evaluateAll((cells) =>
                cells.map((cell) => cell.getBoundingClientRect().height),
            );

        expect(Math.max(...headerHeights)).toBeLessThan(48);

        const download = container.getByRole('link', { name: 'Unduh' }).first();

        await expect(download).toBeVisible();

        const containerBox = await container.boundingBox();
        const downloadBox = await download.boundingBox();

        expect(downloadBox!.x + downloadBox!.width).toBeLessThanOrEqual(
            containerBox!.x + containerBox!.width + 1,
        );

        // Scrolled fully left, the sticky action column is still at the right edge.
        await container.evaluate((element) => {
            element.scrollLeft = 0;
        });

        await expect(download).toBeInViewport();
    });
});
