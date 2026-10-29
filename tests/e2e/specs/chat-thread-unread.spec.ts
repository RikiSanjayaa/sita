import { expect, test } from '@playwright/test';

import {
    createSidebarUnreadFixture,
    loginAs,
    seedIncomingMessage,
} from '../support/sidebar-unread';

for (const role of ['student', 'lecturer'] as const) {
    for (const width of [1280, 390]) {
        test(`${role} sees and clears the thread unread count at ${width}px`, async ({
            browser,
        }, testInfo) => {
            const fixture = createSidebarUnreadFixture();
            const peer = role === 'student' ? 'lecturer' : 'student';
            const otherThread =
                role === 'student' ? 'studentKaprodi' : 'otherStudentLecturer';
            const otherSender = role === 'student' ? 'kaprodi' : 'otherStudent';
            seedIncomingMessage(
                fixture,
                'studentLecturer',
                peer,
                'Pesan baru pertama',
            );
            seedIncomingMessage(
                fixture,
                'studentLecturer',
                peer,
                'Pesan baru kedua',
            );
            seedIncomingMessage(
                fixture,
                otherThread,
                otherSender,
                'Percakapan yang sedang dibuka',
            );
            const context = await browser.newContext({
                baseURL: process.env.PLAYWRIGHT_BASE_URL,
                hasTouch: width === 390,
                isMobile: width === 390,
                viewport: { width, height: 844 },
            });
            try {
                const page = await context.newPage();
                await loginAs(page, fixture.accounts[role]);
                await page.goto(fixture.accounts[role].pesanPath);
                const row = page.locator(
                    `[data-thread-id="${fixture.threads.studentLecturer.id}"]`,
                );
                const badge = row.locator(
                    '[aria-label="2 pesan belum dibaca"]',
                );
                await expect(badge).toBeVisible();
                await expect(badge).toHaveText('2');
                const box = await badge.boundingBox();
                expect(box?.width).toBeGreaterThanOrEqual(20);
                for (const theme of ['light', 'dark']) {
                    await page.evaluate((appearance) => {
                        localStorage.setItem('appearance', appearance);
                        document.documentElement.classList.toggle(
                            'dark',
                            appearance === 'dark',
                        );
                    }, theme);
                    await page.screenshot({
                        path: testInfo.outputPath(
                            `${role}-threads-${width}-${theme}.png`,
                        ),
                    });
                }
                await row.click();
                await expect(
                    page.getByRole('region', {
                        name: 'Riwayat pesan',
                        exact: true,
                    }),
                ).toContainText('Pesan baru kedua');
                if (width === 390) {
                    await page
                        .getByRole('button', {
                            name: 'Kembali ke daftar percakapan',
                            exact: true,
                        })
                        .click();
                }
                await expect(
                    row.locator('[aria-label$="pesan belum dibaca"]'),
                ).toHaveCount(0);
            } finally {
                await context.close();
                fixture.cleanup();
            }
        });
    }
}
