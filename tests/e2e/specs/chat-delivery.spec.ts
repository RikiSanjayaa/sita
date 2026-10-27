import path from 'node:path';

import { expect, test } from '@playwright/test';

import {
    bellCount,
    createSidebarUnreadFixture,
    fetchUnreadCount,
    openPrivateThread,
    openSession,
    seedIncomingMessage,
} from '../support/sidebar-unread';
import { thesisFixtures } from '../support/thesis-fixtures';

for (const order of ['echo-first', 'http-first'] as const) {
    test(`chat reconciles ${order} with identical messages and no page refresh`, async ({
        browser,
    }) => {
        const fixture = createSidebarUnreadFixture();
        const sender = await openSession(browser, fixture.accounts.student);
        let releaseHttp = () => {};
        let releaseEcho = () => {};
        try {
            const heldEcho: (() => void)[] = [];
            let holdEcho = order === 'http-first';
            await sender.page.routeWebSocket('**/app/**', (socket) => {
                const server = socket.connectToServer();
                server.onMessage((data) => {
                    if (
                        holdEcho &&
                        typeof data === 'string' &&
                        data.includes('chat.message.created')
                    ) {
                        heldEcho.push(() => socket.send(data));
                    } else {
                        socket.send(data);
                    }
                });
            });
            releaseEcho = () => {
                holdEcho = false;
                for (const send of heldEcho.splice(0)) send();
            };
            await openPrivateThread(
                sender.page,
                fixture,
                'student',
                'studentLecturer',
                { expectRead: false },
            );
            const threadId = fixture.threads.studentLecturer.id;
            await sender.page.waitForFunction(
                (id) =>
                    window.Echo.connector.pusher.channel(
                        `private-mentorship.thread.${id}`,
                    )?.subscribed,
                threadId,
            );
            const message = `Identik ${fixture.token}`;
            let refreshes = 0;
            sender.page.on('request', (request) => {
                if (
                    request.method() === 'GET' &&
                    new URL(request.url()).pathname === '/mahasiswa/pesan'
                )
                    refreshes++;
            });
            for (let attempt = 1; attempt <= 2; attempt++) {
                let saved = false;
                const gate = new Promise<void>((resolve) => {
                    releaseHttp = resolve;
                });
                await sender.page.route(
                    `**/pesan/${threadId}/messages`,
                    async (route) => {
                        const response = await route.fetch();
                        expect(response.status()).toBe(201);
                        saved = true;
                        if (order === 'echo-first') await gate;
                        await route.fulfill({ response });
                    },
                );
                const input = sender.page.getByPlaceholder('Tulis pesan...');
                await input.fill(message);
                await input.press('Enter');
                await expect.poll(() => saved).toBe(true);
                await expect(
                    sender.page.getByText('Mengirim...', { exact: true }),
                ).toHaveCount(0);
                // Scope to message content rather than the thread-list preview.
                await expect(
                    sender.page
                        .locator('div.whitespace-pre-wrap')
                        .filter({ hasText: message }),
                ).toHaveCount(attempt);
                releaseHttp();
                await expect(input).toHaveValue('');
                await sender.page.unroute(`**/pesan/${threadId}/messages`);
            }
            releaseEcho();
            await expect(
                sender.page
                    .locator('div.whitespace-pre-wrap')
                    .filter({ hasText: message }),
            ).toHaveCount(2);
            expect(refreshes).toBe(0);
        } finally {
            releaseHttp();
            releaseEcho();
            await sender.close();
            fixture.cleanup();
        }
    });
}

for (const attention of ['unfocused', 'hidden', 'reopened'] as const) {
    test(`${attention} chat stays unread until attention returns, without reading another thread`, async ({
        browser,
    }) => {
        const fixture = createSidebarUnreadFixture();
        const recipient = await openSession(browser, fixture.accounts.lecturer);
        const sender = await openSession(browser, fixture.accounts.student);
        try {
            seedIncomingMessage(
                fixture,
                'otherStudentLecturer',
                'otherStudent',
                'Jangan baca thread lain',
            );
            await recipient.page.addInitScript((attention) => {
                const returned =
                    sessionStorage.getItem('chat154-attention-returned') ===
                    'true';
                Object.defineProperty(document, 'hasFocus', {
                    configurable: true,
                    value: () => returned || attention === 'hidden',
                });
                Object.defineProperty(document, 'visibilityState', {
                    configurable: true,
                    get: () =>
                        attention === 'hidden' && !returned
                            ? 'hidden'
                            : 'visible',
                });
            }, attention);
            await openPrivateThread(
                recipient.page,
                fixture,
                'lecturer',
                'studentLecturer',
                { expectRead: false },
            );
            await openPrivateThread(
                sender.page,
                fixture,
                'student',
                'studentLecturer',
                { expectRead: false },
            );
            const threadId = fixture.threads.studentLecturer.id;
            await recipient.page.waitForFunction(
                (id) =>
                    window.Echo.connector.pusher.channel(
                        `private-mentorship.thread.${id}`,
                    )?.subscribed,
                threadId,
            );
            let reads = 0;
            recipient.page.on('request', (request) => {
                if (
                    request.method() === 'POST' &&
                    /\/(read|read-all)$/.test(new URL(request.url()).pathname)
                )
                    reads++;
            });
            const text = `Tetap belum dibaca ${fixture.token}`;
            await sender.page.getByPlaceholder('Tulis pesan...').fill(text);
            await sender.page.getByPlaceholder('Tulis pesan...').press('Enter');
            await expect(recipient.page.getByText(text).last()).toBeVisible();
            await expect.poll(() => bellCount(recipient.page)).toBe(1);
            expect(await fetchUnreadCount(recipient.page)).toEqual({
                unreadMessageCount: 2,
            });
            expect(reads).toBe(0);
            if (attention === 'reopened') {
                await recipient.page.evaluate(() =>
                    sessionStorage.setItem(
                        'chat154-attention-returned',
                        'true',
                    ),
                );
                await recipient.page.reload();
            } else {
                await recipient.page.evaluate(() => {
                    Object.defineProperty(document, 'hasFocus', {
                        configurable: true,
                        value: () => true,
                    });
                    Object.defineProperty(document, 'visibilityState', {
                        configurable: true,
                        get: () => 'visible',
                    });
                    window.dispatchEvent(new Event('focus'));
                });
            }
            await expect
                .poll(() => fetchUnreadCount(recipient.page))
                .toEqual({ unreadMessageCount: 1 });
            await expect.poll(() => bellCount(recipient.page)).toBe(0);
        } finally {
            await sender.close();
            await recipient.close();
            fixture.cleanup();
        }
    });
}

for (const layout of ['desktop-switch', 'mobile-list'] as const) {
    test(`${layout} reads notifications only when the actual chat panel is opened`, async ({
        browser,
    }) => {
        const fixture = createSidebarUnreadFixture();
        const recipient = await openSession(browser, fixture.accounts.lecturer);
        const sender = await openSession(browser, fixture.accounts.student);
        try {
            await recipient.page.setViewportSize({
                width: layout === 'mobile-list' ? 390 : 1280,
                height: 900,
            });
            await recipient.page.addInitScript(() => {
                Object.defineProperty(document, 'hasFocus', {
                    configurable: true,
                    value: () => true,
                });
            });
            await openPrivateThread(
                recipient.page,
                fixture,
                'lecturer',
                layout === 'mobile-list'
                    ? 'studentLecturer'
                    : 'otherStudentLecturer',
                { expectRead: false },
            );
            if (layout === 'mobile-list') {
                await recipient.page
                    .getByRole('button', {
                        name: 'Kembali ke daftar percakapan',
                        exact: true,
                    })
                    .click();
                await expect(
                    recipient.page.getByPlaceholder('Tulis pesan...'),
                ).not.toBeVisible();
            }
            await recipient.page
                .getByPlaceholder('Cari chat pribadi...')
                .fill('');
            await openPrivateThread(
                sender.page,
                fixture,
                'student',
                'studentLecturer',
                { expectRead: false },
            );
            const text = `Pilih percakapan ${fixture.token}`;
            await sender.page.getByPlaceholder('Tulis pesan...').fill(text);
            await sender.page.getByPlaceholder('Tulis pesan...').press('Enter');
            await expect.poll(() => bellCount(recipient.page)).toBe(1);
            await recipient.page.evaluate(() =>
                window.dispatchEvent(new Event('focus')),
            );
            // Acknowledging the list or another panel must not consume this message.
            expect(await fetchUnreadCount(recipient.page)).toEqual({
                unreadMessageCount: 1,
            });
            expect(await bellCount(recipient.page)).toBe(1);
            let navigations = 0;
            recipient.page.on('request', (request) => {
                if (
                    request.method() === 'GET' &&
                    new URL(request.url()).pathname === '/dosen/pesan'
                )
                    navigations++;
            });
            await recipient.page
                .locator('button')
                .filter({ hasText: fixture.accounts.student.name })
                .first()
                .click();
            await expect(
                recipient.page.getByPlaceholder('Tulis pesan...'),
            ).toBeVisible();
            await expect(
                recipient.page
                    .locator('div.whitespace-pre-wrap')
                    .filter({ hasText: text }),
            ).toBeVisible();
            await expect
                .poll(() => fetchUnreadCount(recipient.page))
                .toEqual({ unreadMessageCount: 0 });
            await expect.poll(() => bellCount(recipient.page)).toBe(0);
            expect(navigations).toBe(0);
        } finally {
            await sender.close();
            await recipient.close();
            fixture.cleanup();
        }
    });
}

for (const role of ['student', 'lecturer'] as const) {
    test(`${role} retains a failed attachment draft and retries without a false success`, async ({
        browser,
    }) => {
        const fixture = createSidebarUnreadFixture();
        const session = await openSession(browser, fixture.accounts[role]);
        try {
            await openPrivateThread(
                session.page,
                fixture,
                role,
                'studentLecturer',
                { expectRead: false },
            );
            const url = `**/pesan/${fixture.threads.studentLecturer.id}/messages`;
            const input = session.page.getByPlaceholder('Tulis pesan...');
            const text = `Coba lagi ${fixture.token}`;
            await input.fill(text);
            await session.page
                .locator('input[type="file"]')
                .setInputFiles(
                    path.join(
                        process.cwd(),
                        thesisFixtures.thesisDocumentUploadPath,
                    ),
                );
            await session.page.route(url, (route) => route.abort('failed'));
            await session.page
                .getByRole('button', { name: 'Kirim pesan' })
                .click();
            await expect(
                session.page.getByText(
                    'Pesan belum terkirim. Periksa koneksi dan coba lagi.',
                ),
            ).toBeVisible();
            await expect(input).toHaveValue(text);
            await expect(
                session.page.getByRole('button', { name: 'Hapus lampiran' }),
            ).toBeVisible();
            await expect(
                session.page.getByText('Mengirim...', { exact: true }),
            ).toHaveCount(0);
            await expect(
                session.page
                    .locator('div.whitespace-pre-wrap')
                    .filter({ hasText: text }),
            ).toHaveCount(0);
            await session.page.unroute(url);
            await session.page.route(url, (route) =>
                route.fulfill({
                    status: 422,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        errors: { attachment: ['Lampiran ditolak.'] },
                    }),
                }),
            );
            await session.page
                .getByRole('button', { name: 'Kirim pesan' })
                .click();
            await expect(
                session.page.getByText('Lampiran ditolak.'),
            ).toBeVisible();
            await expect(input).toHaveValue(text);
            await session.page.unroute(url);
            const saved = session.page.waitForResponse(
                (response) =>
                    response.request().method() === 'POST' &&
                    /\/messages$/.test(response.url()),
            );
            await session.page
                .getByRole('button', { name: 'Kirim pesan' })
                .click();
            expect((await saved).status()).toBe(201);
            await expect(input).toHaveValue('');
            await expect(
                session.page
                    .locator('div.whitespace-pre-wrap')
                    .filter({ hasText: text }),
            ).toHaveCount(1);
            await expect(
                session.page.getByRole('button', { name: 'Hapus lampiran' }),
            ).toHaveCount(0);
        } finally {
            await session.close();
            fixture.cleanup();
        }
    });
}
