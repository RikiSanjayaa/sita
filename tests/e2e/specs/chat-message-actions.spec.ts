import { expect, test } from '@playwright/test';

import { attachDocumentStates } from '../support/document-detail';
import {
    createSidebarUnreadFixture,
    loginAs,
    openPrivateThread,
    seedIncomingMessage,
} from '../support/sidebar-unread';

for (const touch of [false, true]) {
    test(`message actions: ${touch ? 'touch tap' : 'desktop hover and keyboard'} copy exact text and preserve nested actions`, async ({
        browser,
    }, info) => {
        const fixture = createSidebarUnreadFixture();
        const text = 'Catatan penelitian\nBaris kedua: data & hasil.';
        seedIncomingMessage(fixture, 'studentLecturer', 'student', text);
        const context = await browser.newContext({
            baseURL: process.env.PLAYWRIGHT_BASE_URL,
            hasTouch: touch,
            isMobile: touch,
            viewport: { width: touch ? 390 : 1280, height: 844 },
            permissions: ['clipboard-read', 'clipboard-write'],
        });
        const page = await context.newPage();
        try {
            await loginAs(page, fixture.accounts.lecturer);
            await openPrivateThread(
                page,
                fixture,
                'lecturer',
                'studentLecturer',
                { expectRead: false },
            );
            const sourceId = await page
                .locator('[data-message-id]')
                .filter({ hasText: text })
                .getAttribute('data-message-id');
            const message = page.locator(`[data-message-id="${sourceId}"]`);
            const trigger = message.getByRole('button', {
                name: 'Aksi pesan',
                exact: true,
            });
            const content = message.getByText(text, { exact: true });
            await expect(trigger).toHaveCSS('opacity', '0');
            async function openMenu() {
                if (touch) {
                    await content.tap();
                } else {
                    await content.hover();
                    await expect(trigger).toHaveCSS('opacity', '1');
                    await trigger.click();
                }
                await expect(
                    page.getByRole('menuitem', {
                        name: 'Balas',
                        exact: true,
                    }),
                ).toBeVisible();
            }
            await openMenu();
            await expect(
                page.getByRole('menuitem', { name: 'Edit teks / caption' }),
            ).toHaveCount(0);
            await page
                .getByRole('menuitem', { name: 'Salin teks', exact: true })
                .click();
            await expect
                .poll(() => page.evaluate(() => navigator.clipboard.readText()))
                .toBe(text);
            await expect(
                page.getByText('Teks pesan disalin', { exact: true }),
            ).toBeVisible();
            await expect(page.getByRole('menu')).toHaveCount(0);

            if (!touch) {
                await page.mouse.move(0, 0);
                await page.getByPlaceholder('Tulis pesan...').focus();
                await expect(trigger).toHaveCSS('opacity', '0');
                await trigger.focus();
                await expect(trigger).toHaveCSS('opacity', '1');
                await page.keyboard.press('Enter');
                await expect(
                    page.getByRole('menuitem', { name: 'Salin teks' }),
                ).toBeVisible();
                await page.keyboard.press('Escape');
                await expect(trigger).toBeFocused();
            }

            await openMenu();
            await page
                .getByRole('menuitem', { name: 'Balas', exact: true })
                .click();
            await expect(
                page.getByRole('region', { name: 'Membalas pesan' }),
            ).toContainText(text);
            await page
                .getByPlaceholder('Tulis pesan...')
                .fill('Balasan untuk catatan');
            const sent = page.waitForResponse(
                (response) =>
                    response.request().method() === 'POST' &&
                    /\/messages$/.test(response.url()),
            );
            await page
                .getByRole('button', { name: 'Kirim pesan', exact: true })
                .click();
            const response = await sent;
            expect(response.status()).toBe(201);
            const { messages } = await response.json();
            const reply = page.locator(`[data-message-id="${messages[0].id}"]`);
            const quote = reply.getByRole('button', {
                name: 'Lihat pesan asal',
            });
            if (touch) await quote.tap();
            else await quote.click();
            await expect(page.getByRole('menu')).toHaveCount(0);
            await expect(message).toBeInViewport();

            // A denied clipboard write reports failure without a false success.
            await page.evaluate(() => {
                Object.defineProperty(navigator.clipboard, 'writeText', {
                    configurable: true,
                    value: () =>
                        Promise.reject(
                            new DOMException('Denied', 'NotAllowedError'),
                        ),
                });
            });
            await openMenu();
            await page
                .getByRole('menuitem', { name: 'Salin teks', exact: true })
                .click();
            await expect(
                page.getByText(
                    'Gagal menyalin pesan. Pilih dan salin teks secara manual.',
                    { exact: true },
                ),
            ).toBeVisible();

            await openMenu();
            await attachDocumentStates(
                page,
                info,
                `${touch ? 'touch' : 'desktop'}-message-menu`,
            );
            await page.keyboard.press('Escape');
            await attachDocumentStates(
                page,
                info,
                `${touch ? 'touch' : 'desktop'}-message-rest`,
            );
        } finally {
            await context.close();
            fixture.cleanup();
        }
    });
}
