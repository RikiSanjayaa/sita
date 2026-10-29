import { expect, test } from '@playwright/test';

import {
    createSidebarUnreadFixture,
    loginAs,
    openPrivateThread,
    openSidebarDrawer,
    pesanLink,
    seedIncomingMessage,
    sidebarScope,
} from '../support/sidebar-unread';

for (const role of ['student', 'lecturer'] as const) {
    for (const touch of [false, true]) {
        test(`${role} returns to the last conversation after sidebar navigation and reload (${touch ? 'touch' : 'desktop'})`, async ({
            browser,
        }) => {
            const fixture = createSidebarUnreadFixture();
            const otherThread =
                role === 'student' ? 'studentKaprodi' : 'otherStudentLecturer';
            const otherSender = role === 'student' ? 'kaprodi' : 'otherStudent';
            const peer = role === 'student' ? 'lecturer' : 'student';
            seedIncomingMessage(
                fixture,
                'studentLecturer',
                peer,
                'Percakapan terbaru pilihan saya',
            );
            seedIncomingMessage(
                fixture,
                otherThread,
                otherSender,
                'Percakapan pembanding',
            );
            const context = await browser.newContext({
                baseURL: process.env.PLAYWRIGHT_BASE_URL,
                hasTouch: touch,
                isMobile: touch,
                viewport: { width: touch ? 390 : 1280, height: 844 },
            });
            const page = await context.newPage();
            const account = fixture.accounts[role];
            try {
                await loginAs(page, account);
                await openPrivateThread(
                    page,
                    fixture,
                    role,
                    'studentLecturer',
                    { expectRead: false },
                );
                async function leaveAndReturn() {
                    if (touch) await openSidebarDrawer(page);
                    await sidebarScope(page, touch)
                        .getByRole('link', { name: 'Dashboard', exact: true })
                        .click();
                    await expect(page).toHaveURL(
                        new RegExp(`${account.homePath}$`),
                    );
                    if (touch) await openSidebarDrawer(page);
                    await pesanLink(page, account, touch).click();
                    await expect(page).toHaveURL(
                        new RegExp(`${account.pesanPath}$`),
                    );
                }
                const history = page.getByRole('region', {
                    name: 'Riwayat pesan',
                    exact: true,
                });
                await leaveAndReturn();
                await expect(history).toContainText(
                    'Percakapan terbaru pilihan saya',
                );
                await expect(history).not.toContainText(
                    'Percakapan pembanding',
                );
                await page.reload();
                await expect(history).toContainText(
                    'Percakapan terbaru pilihan saya',
                );
                // Explicit notification/profile links override remembered selection.
                await page.goto(
                    `${account.pesanPath}?thread=${fixture.threads[otherThread].id}`,
                );
                await expect(history).toContainText('Percakapan pembanding');
                await leaveAndReturn();
                await expect(history).toContainText('Percakapan pembanding');
                // An unauthorized thread ID cannot replace an authorized remembered thread.
                await page.goto(`${account.pesanPath}?thread=999999999`);
                await expect(history).toContainText('Percakapan pembanding');
            } finally {
                await context.close();
                fixture.cleanup();
            }
        });
    }
}
