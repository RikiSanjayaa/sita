import { expect, test, type Page } from '@playwright/test';

import {
    bellCount,
    closeSidebarDrawer,
    createSidebarUnreadFixture,
    expectBadgeBesideLabel,
    expectBadgeMatchesBell,
    expectPesanRead,
    expectPesanUnread,
    fetchUnreadCount,
    insertNotification,
    loginAs,
    mobileViewport,
    openPrivateThread,
    openSession,
    openSidebarDrawer,
    pesanBadge,
    pesanGeometry,
    pesanLink,
    readRequestPattern,
    resetNotifications,
    seedIncomingMessage,
    seedIncomingMessages,
    sendMessageFromUi,
    setSidebarState,
    sidebarScope,
    unreadPesanLabel,
    waitForUnreadChannel,
    withDrawerPesan,
    type SidebarUnreadSession,
} from '../support/sidebar-unread';

// Sidebar "Pesan" numeric unread badge (issue #138). Every test builds its own fixture
// (support/sidebar-unread.ts): accounts, private threads and notifications are
// created and removed per test, the shared seed is never touched. Realtime
// behaviour goes through the real chat UI and the real Reverb server; nothing
// dispatches or fakes a socket event.
test.use({ storageState: { cookies: [], origins: [] } });

const noUnread = { unreadMessageCount: 0 };
const oneUnread = { unreadMessageCount: 1 };

async function closeAll(sessions: SidebarUnreadSession[]): Promise<void> {
    for (const session of sessions) {
        await session.close();
    }
}

async function openNotificationsPanel(page: Page): Promise<void> {
    await page.getByRole('button', { name: 'Open notifications' }).click();
    await expect(
        page.getByRole('dialog').last().getByText('Notifikasi').first(),
    ).toBeVisible();
}

test.describe('Sidebar unread indicator on Pesan', () => {
    test('a message from the UI raises the Pesan count outside Pesan in realtime, survives navigation and reload, and clears only after the thread is read', async ({
        browser,
    }) => {
        const fixture = createSidebarUnreadFixture();
        const { student, lecturer } = fixture.accounts;
        const sessions: SidebarUnreadSession[] = [];

        try {
            const sender = await openSession(browser, student);
            const recipient = await openSession(browser, lecturer);
            sessions.push(sender, recipient);

            await recipient.page.goto(lecturer.otherPagePath);
            // The invalidation channel is the real private Reverb channel of this user.
            await waitForUnreadChannel(recipient.page, lecturer);
            const link = pesanLink(recipient.page, lecturer);
            await expectPesanRead(link);
            expect(await fetchUnreadCount(recipient.page)).toEqual(noUnread);

            await sendMessageFromUi(
                sender.page,
                fixture,
                'student',
                'studentLecturer',
                `Halo dosen ${fixture.token.slice(-6)}`,
            );

            // No reload and no navigation on the recipient: the socket invalidation alone does it.
            await expectPesanUnread(link, 1);
            await expectBadgeBesideLabel(link);
            expect(recipient.unreadEvents()).toBeGreaterThan(0);
            expect(await fetchUnreadCount(recipient.page)).toEqual(oneUnread);

            // The sender's own message never increases their unread count.
            await sender.page.goto(student.otherPagePath);
            await expectPesanRead(pesanLink(sender.page, student));
            expect(await fetchUnreadCount(sender.page)).toEqual(noUnread);

            // Inertia navigation to another page without chat keeps the count.
            await sidebarScope(recipient.page)
                .getByRole('link', { name: 'Mahasiswa Dosen', exact: true })
                .click();
            await expect(recipient.page).toHaveURL(
                /\/dosen\/mahasiswa-bimbingan/,
            );
            await expectPesanUnread(pesanLink(recipient.page, lecturer));

            // So does a full reload.
            await recipient.page.reload();
            await expectPesanUnread(pesanLink(recipient.page, lecturer));

            // Opening the actual thread saves the receipt and clears its unread count.
            await openPrivateThread(
                recipient.page,
                fixture,
                'lecturer',
                'studentLecturer',
            );
            await expectPesanRead(pesanLink(recipient.page, lecturer));
            expect(await fetchUnreadCount(recipient.page)).toEqual(noUnread);

            await recipient.page.reload();
            await expectPesanRead(pesanLink(recipient.page, lecturer));
        } finally {
            await closeAll(sessions);
            fixture.cleanup();
        }
    });

    test('a lecturer message reaches the student sidebar and clears when the student opens the thread', async ({
        browser,
    }) => {
        const fixture = createSidebarUnreadFixture();
        const { student, lecturer } = fixture.accounts;
        const sessions: SidebarUnreadSession[] = [];

        try {
            const recipient = await openSession(browser, student);
            const sender = await openSession(browser, lecturer);
            sessions.push(recipient, sender);

            await recipient.page.goto(student.otherPagePath);
            await waitForUnreadChannel(recipient.page, student);
            await expectPesanRead(pesanLink(recipient.page, student));

            await sendMessageFromUi(
                sender.page,
                fixture,
                'lecturer',
                'studentLecturer',
                `Silakan revisi bab dua ${fixture.token.slice(-6)}`,
            );

            await expectPesanUnread(pesanLink(recipient.page, student));
            expect(recipient.unreadEvents()).toBeGreaterThan(0);

            await recipient.page.reload();
            await expectPesanUnread(pesanLink(recipient.page, student));

            await openPrivateThread(
                recipient.page,
                fixture,
                'student',
                'studentLecturer',
            );
            await expectPesanRead(pesanLink(recipient.page, student));

            await recipient.page.reload();
            await expectPesanRead(pesanLink(recipient.page, student));
            expect(await fetchUnreadCount(recipient.page)).toEqual(noUnread);
        } finally {
            await closeAll(sessions);
            fixture.cleanup();
        }
    });

    test('notifications and chat receipts are separate: the count appears with message notifications off, and neither read action clears the other', async ({
        browser,
    }) => {
        const fixture = createSidebarUnreadFixture();
        const { lecturer } = fixture.accounts;
        const sessions: SidebarUnreadSession[] = [];

        try {
            // "Pesan baru" notifications off: chat unread must not depend on them.
            resetNotifications(fixture, 'lecturer', false);
            seedIncomingMessage(
                fixture,
                'studentLecturer',
                'student',
                'Pesan tanpa notifikasi',
            );
            insertNotification(fixture, 'lecturer', 'Pengumuman uji pertama');

            const session = await openSession(browser, lecturer);
            sessions.push(session);
            const { page } = session;

            await page.goto(lecturer.otherPagePath);
            await expectPesanUnread(pesanLink(page, lecturer), 1);
            await expect.poll(() => bellCount(page)).toBe(1);

            // The Pesan badge and the bell badge share the destructive colours,
            // and the badge sits beside the label.
            await expectBadgeMatchesBell(page, pesanLink(page, lecturer));
            await setSidebarState(page, 'expanded');
            await expectBadgeBesideLabel(pesanLink(page, lecturer));

            // Reading every global notification leaves the chat count alone.
            await openNotificationsPanel(page);
            await page.getByRole('button', { name: 'Baca semua' }).click();
            await expect(
                page.getByRole('button', { name: 'Baca semua' }),
            ).toBeDisabled();
            await page.keyboard.press('Escape');
            await expect.poll(() => bellCount(page)).toBe(0);
            await expectPesanUnread(pesanLink(page, lecturer));
            await page.reload();
            await expect.poll(() => bellCount(page)).toBe(0);
            await expectPesanUnread(pesanLink(page, lecturer));

            // Reading the chat leaves an unrelated unread notification alone.
            insertNotification(fixture, 'lecturer', 'Pengumuman uji kedua');
            await page.reload();
            await expect.poll(() => bellCount(page)).toBe(1);
            await openPrivateThread(
                page,
                fixture,
                'lecturer',
                'studentLecturer',
            );
            await expectPesanRead(pesanLink(page, lecturer));
            await page.goto(lecturer.otherPagePath);
            await expectPesanRead(pesanLink(page, lecturer));
            await expect.poll(() => bellCount(page)).toBe(1);
        } finally {
            await closeAll(sessions);
            fixture.cleanup();
        }
    });

    test('a receipt saved in another browser tab clears the count in the tab outside Pesan', async ({
        browser,
    }) => {
        const fixture = createSidebarUnreadFixture();
        const { lecturer } = fixture.accounts;
        const sessions: SidebarUnreadSession[] = [];

        try {
            seedIncomingMessage(
                fixture,
                'studentLecturer',
                'student',
                'Menunggu dibaca di tab lain',
            );

            const session = await openSession(browser, lecturer);
            sessions.push(session);
            const idleTab = session.page;

            await idleTab.goto(lecturer.otherPagePath);
            await waitForUnreadChannel(idleTab, lecturer);
            await expectPesanUnread(pesanLink(idleTab, lecturer));
            const eventsBefore = session.unreadEvents();

            const readingTab = await session.context.newPage();
            await openPrivateThread(
                readingTab,
                fixture,
                'lecturer',
                'studentLecturer',
            );

            // The idle tab never reloads or navigates; the receipt invalidation reaches it.
            await expectPesanRead(pesanLink(idleTab, lecturer));
            expect(session.unreadEvents()).toBeGreaterThan(eventsBefore);
        } finally {
            await closeAll(sessions);
            fixture.cleanup();
        }
    });

    test('on a 390px phone the thread list does not read, opening a thread reads only that thread (2, then 1, then 0), and the drawer badge sits beside the label', async ({
        browser,
    }) => {
        const fixture = createSidebarUnreadFixture();
        const { lecturer } = fixture.accounts;
        const sessions: SidebarUnreadSession[] = [];

        try {
            seedIncomingMessage(
                fixture,
                'studentLecturer',
                'student',
                'Pesan pertama belum dibaca',
            );
            seedIncomingMessage(
                fixture,
                'otherStudentLecturer',
                'otherStudent',
                'Pesan kedua belum dibaca',
            );

            const session = await openSession(
                browser,
                lecturer,
                mobileViewport,
            );
            sessions.push(session);
            const { page } = session;
            const receipts: string[] = [];

            page.on('request', (request) => {
                if (
                    request.method() === 'POST' &&
                    /\/pesan\/\d+\/read$/.test(request.url())
                ) {
                    receipts.push(request.url());
                }
            });

            // Visiting the Pesan list, even in the private mode, is not reading.
            await page.goto(lecturer.pesanPath);
            await page
                .getByRole('button', { name: 'Pribadi', exact: true })
                .click();
            await expect(
                page.getByPlaceholder('Cari chat pribadi...'),
            ).toBeVisible();
            await page.waitForTimeout(750);
            expect(receipts).toEqual([]);
            await withDrawerPesan(page, lecturer, async (link) => {
                await expectPesanUnread(link, 2);
                await expectBadgeBesideLabel(link);
                await expect(
                    sidebarScope(page, true).getByRole('link', {
                        name: unreadPesanLabel(2),
                    }),
                ).toBeVisible();
                const box = await link.boundingBox();
                expect(box?.height).toBeGreaterThanOrEqual(44);
            });
            expect(await fetchUnreadCount(page)).toEqual({
                unreadMessageCount: 2,
            });
            await page.reload();
            await withDrawerPesan(page, lecturer, (link) =>
                expectPesanUnread(link, 2),
            );
            expect(receipts).toEqual([]);

            // Opening the first thread reads it; the second one keeps one unread message.
            await openPrivateThread(
                page,
                fixture,
                'lecturer',
                'studentLecturer',
            );
            await withDrawerPesan(page, lecturer, (link) =>
                expectPesanUnread(link, 1),
            );
            expect(await fetchUnreadCount(page)).toEqual(oneUnread);
            expect(
                receipts.some((url) =>
                    readRequestPattern(
                        fixture.threads.otherStudentLecturer.id,
                    ).test(url),
                ),
            ).toBe(false);

            // Opening the second one clears the last unread thread.
            await openPrivateThread(
                page,
                fixture,
                'lecturer',
                'otherStudentLecturer',
            );
            await withDrawerPesan(page, lecturer, expectPesanRead);
            expect(await fetchUnreadCount(page)).toEqual(noUnread);
        } finally {
            await closeAll(sessions);
            fixture.cleanup();
        }
    });

    test('kaprodi sees the count badge in its lecturer navigation, expanded and collapsed, without moving the label, and other accounts stay clean', async ({
        browser,
    }) => {
        const fixture = createSidebarUnreadFixture();
        const { student, lecturer, kaprodi } = fixture.accounts;
        const sessions: SidebarUnreadSession[] = [];

        try {
            const kaprodiSession = await openSession(browser, kaprodi);
            sessions.push(kaprodiSession);
            const { page } = kaprodiSession;

            await page.goto(kaprodi.otherPagePath);
            const link = pesanLink(page, kaprodi);
            await setSidebarState(page, 'expanded');
            await expectPesanRead(link);
            const readExpanded = await pesanGeometry(link);
            expect(readExpanded.link).not.toBeNull();
            await setSidebarState(page, 'collapsed');
            const readCollapsed = await pesanGeometry(link);
            await setSidebarState(page, 'expanded');

            seedIncomingMessage(
                fixture,
                'studentKaprodi',
                'student',
                'Konsultasi untuk kaprodi',
            );
            await page.reload();
            await setSidebarState(page, 'expanded');
            await expectPesanUnread(link, 1);
            await expectBadgeBesideLabel(link);

            // The badge is an overlay: link target, label and icon keep their geometry.
            expect(await pesanGeometry(link)).toEqual(readExpanded);

            // Collapsed: icon only, numeric badge over it, still announced with the count.
            await setSidebarState(page, 'collapsed');
            await expectPesanUnread(link, 1);
            await expect(pesanBadge(link)).toBeVisible();
            await expect(
                sidebarScope(page).getByRole('link', {
                    name: unreadPesanLabel(1),
                }),
            ).toBeVisible();
            const collapsedBox = await link.boundingBox();
            expect(collapsedBox?.width).toBeGreaterThanOrEqual(32);
            expect(collapsedBox?.height).toBeGreaterThanOrEqual(32);
            expect(await pesanGeometry(link)).toEqual(readCollapsed);

            // The thread only belongs to the student and the kaprodi.
            const student1 = await openSession(browser, student);
            const lecturer1 = await openSession(browser, lecturer);
            sessions.push(student1, lecturer1);
            await student1.page.goto(student.otherPagePath);
            await lecturer1.page.goto(lecturer.otherPagePath);
            await expectPesanRead(pesanLink(student1.page, student));
            await expectPesanRead(pesanLink(lecturer1.page, lecturer));
            expect(await fetchUnreadCount(lecturer1.page)).toEqual(noUnread);

            // Reading it as the kaprodi clears the badge.
            await setSidebarState(page, 'expanded');
            await openPrivateThread(page, fixture, 'kaprodi', 'studentKaprodi');
            await expectPesanRead(pesanLink(page, kaprodi));
            expect(await fetchUnreadCount(page)).toEqual(noUnread);
        } finally {
            await closeAll(sessions);
            fixture.cleanup();
        }
    });

    test('many unread messages in one thread count every message, cap the visible badge at 99+ with the exact accessible count, and clear when the thread is read', async ({
        browser,
    }) => {
        const fixture = createSidebarUnreadFixture();
        const { lecturer } = fixture.accounts;
        const sessions: SidebarUnreadSession[] = [];

        try {
            seedIncomingMessages(
                fixture,
                'studentLecturer',
                'student',
                3,
                'Pesan awal',
            );

            const session = await openSession(browser, lecturer);
            sessions.push(session);
            const { page } = session;

            await page.goto(lecturer.otherPagePath);
            await setSidebarState(page, 'expanded');
            const link = pesanLink(page, lecturer);

            // Messages are counted, not threads: one thread with three messages is 3.
            await expectPesanUnread(link, 3);
            expect(await fetchUnreadCount(page)).toEqual({
                unreadMessageCount: 3,
            });
            const expandedWithThree = await pesanGeometry(link);
            await setSidebarState(page, 'collapsed');
            const collapsedWithThree = await pesanGeometry(link);
            await setSidebarState(page, 'expanded');

            // 97 more makes exactly 100: the badge reads 99+, the label keeps 100.
            seedIncomingMessages(
                fixture,
                'studentLecturer',
                'student',
                97,
                'Pesan susulan',
            );
            await page.reload();
            await setSidebarState(page, 'expanded');
            await expectPesanUnread(link, 100);
            await expect(pesanBadge(link)).toHaveText('99+');
            await expect(link).toHaveAttribute(
                'aria-label',
                'Pesan, 100 pesan belum dibaca',
            );
            expect(await fetchUnreadCount(page)).toEqual({
                unreadMessageCount: 100,
            });
            await expectBadgeBesideLabel(link);
            expect(await pesanGeometry(link)).toEqual(expandedWithThree);

            // Collapsed: the wider badge is an overlay, the target and icon stay put.
            await setSidebarState(page, 'collapsed');
            await expectPesanUnread(link, 100);
            await expect(pesanBadge(link)).toBeVisible();
            expect(await pesanGeometry(link)).toEqual(collapsedWithThree);
            await setSidebarState(page, 'expanded');

            // Exactly 99 would show the plain number; one read thread clears everything.
            await openPrivateThread(
                page,
                fixture,
                'lecturer',
                'studentLecturer',
            );
            await expectPesanRead(link);
            expect(await fetchUnreadCount(page)).toEqual(noUnread);
            await page.reload();
            await expectPesanRead(link);
        } finally {
            await closeAll(sessions);
            fixture.cleanup();
        }
    });

    test('unread state stays per account: parallel sessions, switching accounts in one browser, and the anonymous endpoint', async ({
        browser,
    }) => {
        const fixture = createSidebarUnreadFixture();
        const { student, otherStudent, lecturer } = fixture.accounts;
        const sessions: SidebarUnreadSession[] = [];

        try {
            seedIncomingMessage(
                fixture,
                'studentLecturer',
                'student',
                'Hanya untuk dosen ini',
            );

            const lecturerSession = await openSession(browser, lecturer);
            const studentSession = await openSession(browser, student);
            const otherSession = await openSession(browser, otherStudent);
            sessions.push(lecturerSession, studentSession, otherSession);

            await Promise.all([
                lecturerSession.page.goto(lecturer.otherPagePath),
                studentSession.page.goto(student.otherPagePath),
                otherSession.page.goto(otherStudent.otherPagePath),
            ]);

            await expectPesanUnread(pesanLink(lecturerSession.page, lecturer));
            await expectPesanRead(pesanLink(studentSession.page, student));
            await expectPesanRead(pesanLink(otherSession.page, otherStudent));
            expect(await fetchUnreadCount(otherSession.page)).toEqual(noUnread);

            // The same browser signs in as someone else: nothing carries over.
            await loginAs(lecturerSession.page, otherStudent);
            await lecturerSession.page.goto(otherStudent.otherPagePath);
            await expectPesanRead(
                pesanLink(lecturerSession.page, otherStudent),
            );
            expect(await fetchUnreadCount(lecturerSession.page)).toEqual(
                noUnread,
            );

            // And back: the lecturer's own state is still there.
            await loginAs(lecturerSession.page, lecturer);
            await lecturerSession.page.goto(lecturer.otherPagePath);
            await expectPesanUnread(pesanLink(lecturerSession.page, lecturer));

            // Anonymous callers never get the count.
            const anonymous = await browser.newContext({
                storageState: { cookies: [], origins: [] },
            });

            try {
                const response = await anonymous.request.get('/chat/unread', {
                    headers: { Accept: 'application/json' },
                    maxRedirects: 0,
                });

                expect([302, 401]).toContain(response.status());
            } finally {
                await anonymous.close();
            }
        } finally {
            await closeAll(sessions);
            fixture.cleanup();
        }
    });

    test('the student drawer shows the count badge beside the label, keeps it after reopening, and opening the drawer does not read', async ({
        browser,
    }) => {
        const fixture = createSidebarUnreadFixture();
        const { student } = fixture.accounts;
        const sessions: SidebarUnreadSession[] = [];

        try {
            seedIncomingMessage(
                fixture,
                'studentLecturer',
                'lecturer',
                'Cek drawer mahasiswa',
            );

            const session = await openSession(browser, student, mobileViewport);
            sessions.push(session);
            await session.page.goto(student.otherPagePath);

            await openSidebarDrawer(session.page);
            const link = pesanLink(session.page, student, true);
            await expectPesanUnread(link, 1);
            await expectBadgeBesideLabel(link);
            await closeSidebarDrawer(session.page);
            await withDrawerPesan(session.page, student, (drawerLink) =>
                expectPesanUnread(drawerLink, 1),
            );
            expect(await fetchUnreadCount(session.page)).toEqual(oneUnread);
        } finally {
            await closeAll(sessions);
            fixture.cleanup();
        }
    });
});
