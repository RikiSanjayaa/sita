import path from 'node:path';

import { expect, test } from '@playwright/test';

import {
    createSidebarUnreadFixture,
    loginAs,
    openPrivateThread,
    seedIncomingMessage,
} from '../support/sidebar-unread';

for (const role of ['student', 'lecturer'] as const) {
    for (const touch of [false, true]) {
        test(`${role} composer keeps focus and draft through reply edit cancel and attachment actions (${touch ? 'touch' : 'keyboard'})`, async ({
            browser,
        }, testInfo) => {
            const fixture = createSidebarUnreadFixture();
            const peer = role === 'student' ? 'lecturer' : 'student';
            seedIncomingMessage(
                fixture,
                'studentLecturer',
                peer,
                'Catatan untuk dibalas',
            );
            seedIncomingMessage(
                fixture,
                'studentLecturer',
                role,
                'Pesan saya untuk diedit',
            );
            const context = await browser.newContext({
                baseURL: process.env.PLAYWRIGHT_BASE_URL,
                hasTouch: touch,
                isMobile: touch,
                viewport: { width: touch ? 390 : 1280, height: 844 },
            });
            const page = await context.newPage();
            try {
                await loginAs(page, fixture.accounts[role]);
                await openPrivateThread(
                    page,
                    fixture,
                    role,
                    'studentLecturer',
                    { expectRead: false },
                );
                const input = page.getByPlaceholder('Tulis pesan...', {
                    exact: true,
                });
                const own = page
                    .locator('[data-message-id]')
                    .filter({ hasText: 'Pesan saya untuk diedit' });
                const incoming = page
                    .locator('[data-message-id]')
                    .filter({ hasText: 'Catatan untuk dibalas' });
                const sourceIds = {
                    own: await own.getAttribute('data-message-id'),
                    incoming: await incoming.getAttribute('data-message-id'),
                };
                async function action(id: string | null, name: string) {
                    const row = page.locator(`[data-message-id="${id}"]`);
                    if (touch) {
                        await row.locator('.whitespace-pre-wrap').tap();
                    } else {
                        await row
                            .getByRole('button', {
                                name: 'Aksi pesan',
                                exact: true,
                            })
                            .focus();
                        await page.keyboard.press('Enter');
                    }
                    await page
                        .getByRole('menuitem', { name, exact: true })
                        .click();
                    await expect(page.getByRole('menu')).toHaveCount(0);
                }
                await input.fill('Draft tetap');
                await action(sourceIds.incoming, 'Balas');
                await expect(input).toBeFocused();
                await page.keyboard.type(' dilanjutkan');
                await expect(input).toHaveValue('Draft tetap dilanjutkan');
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
                            `${role}-reply-focus-${touch ? 390 : 1280}-${theme}.png`,
                        ),
                    });
                }
                // Selecting the same reply again must return focus too.
                await action(sourceIds.incoming, 'Balas');
                await expect(input).toBeFocused();
                await page
                    .getByRole('button', { name: 'Batal balas', exact: true })
                    .click();
                await expect(input).toBeFocused();
                await expect(input).toHaveValue('Draft tetap dilanjutkan');

                await action(sourceIds.own, 'Edit teks / caption');
                await expect(input).toBeFocused();
                await expect(input).toHaveValue('Pesan saya untuk diedit');
                await input.fill('Edit dibatalkan');
                await page
                    .getByRole('button', { name: 'Batal edit', exact: true })
                    .click();
                await expect(input).toBeFocused();
                await expect(input).toHaveValue('Draft tetap dilanjutkan');

                // Dismiss/copy must keep normal menu focus, not summon the composer.
                const trigger = incoming.getByRole('button', {
                    name: 'Aksi pesan',
                    exact: true,
                });
                await trigger.focus();
                await page.keyboard.press('Enter');
                await page.keyboard.press('Escape');
                await expect(trigger).toBeFocused();

                await page
                    .locator('input[type=file]')
                    .setInputFiles(
                        path.join(
                            process.cwd(),
                            'tests/fixtures/chat-image.png',
                        ),
                    );
                await expect(
                    page.getByRole('button', {
                        name: 'Hapus lampiran',
                        exact: true,
                    }),
                ).toBeVisible();
                await expect(input).toBeFocused();
                await page
                    .getByRole('button', {
                        name: 'Hapus lampiran',
                        exact: true,
                    })
                    .click();
                await expect(input).toBeFocused();
                await expect(input).toHaveValue('Draft tetap dilanjutkan');

                const saved = page.waitForResponse(
                    (r) =>
                        r.request().method() === 'POST' &&
                        /\/messages$/.test(r.url()),
                );
                await page
                    .getByRole('button', { name: 'Kirim pesan', exact: true })
                    .click();
                expect((await saved).status()).toBe(201);
                await expect(input).toBeFocused();
                await page.keyboard.type('Pesan berikutnya');
                await expect(input).toHaveValue('Pesan berikutnya');
            } finally {
                await context.close();
                fixture.cleanup();
            }
        });
    }
}
