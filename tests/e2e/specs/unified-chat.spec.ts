import { expect, test } from '@playwright/test';

import { type ChatSendAcknowledgement } from '../../../resources/js/components/chat/types';
import { runArtisan, seedPlaywrightScenarios } from '../support/db';
import { attachDocumentStates } from '../support/document-detail';
import { authStatePath, thesisScenarios } from '../support/thesis-fixtures';

const student = thesisScenarios.twoSupervisors;

test.beforeEach(() => {
    seedPlaywrightScenarios();
    runArtisan([
        'tinker',
        `--execute=$student = App\\Models\\User::query()->where('email', 'e2e-pembimbing@sita.test')->firstOrFail(); $lecturer = App\\Models\\User::query()->where('email', 'dosen@sita.test')->firstOrFail(); $student->forceFill(['phone_number' => '081234567890', 'phone_number_confirmed_at' => now()])->save(); App\\Models\\MentorshipChatThread::query()->firstOrCreate(['student_user_id' => $student->id, 'type' => 'pembimbing']); app(App\\Services\\PrivateChatService::class)->findOrCreateThread($student, $lecturer)->messages()->delete();`,
    ]);
});

test('one list searches group and private chats, preserves deep links and mobile back (#159)', async ({
    browser,
}, testInfo) => {
    test.setTimeout(180_000);
    const context = await browser.newContext({
        storageState: authStatePath('dosen1'),
    });
    try {
        const page = await context.newPage();
        await page.goto('/dosen/pesan');
        const list = page.getByRole('complementary', {
            name: 'Daftar percakapan',
        });
        await expect(
            list.getByRole('textbox', { name: 'Cari percakapan', exact: true }),
        ).toHaveCount(0);
        await list
            .getByRole('button', { name: 'Cari percakapan', exact: true })
            .click();
        await expect(
            list.getByRole('textbox', { name: 'Cari percakapan', exact: true }),
        ).toBeFocused();
        await list
            .getByRole('textbox', { name: 'Cari percakapan', exact: true })
            .fill(student.studentName);
        const group = list
            .locator('[data-thread-id]')
            .filter({ hasText: 'Grup Bimbingan' })
            .filter({ hasText: student.studentName });
        const privateChat = list
            .locator('[data-thread-id]')
            .filter({ hasText: 'Pribadi' })
            .filter({ hasText: student.studentName });
        await expect(group).toHaveCount(1);
        await expect(privateChat).toHaveCount(1);
        const groupTitleBounds = await group
            .getByText(student.studentName, { exact: true })
            .boundingBox();
        const privateTitleBounds = await privateChat
            .getByText(student.studentName, { exact: true })
            .boundingBox();
        expect(
            Math.abs(groupTitleBounds!.x - privateTitleBounds!.x),
        ).toBeLessThanOrEqual(1);
        await attachDocumentStates(page, testInfo, 'dosen-unified-search');
        await page.setViewportSize({ width: 1280, height: 844 });
        const groupId = await group.getAttribute('data-thread-id');
        const privateId = await privateChat.getAttribute('data-thread-id');
        await group.click();
        await expect(page).toHaveURL(new RegExp(`thread=${groupId}(?:&|$)`));
        await expect(page.getByPlaceholder('Tulis pesan...')).toBeVisible();
        await privateChat.click();
        await expect(page).toHaveURL(new RegExp(`thread=${privateId}(?:&|$)`));
        await page
            .getByPlaceholder('Tulis pesan...')
            .fill('Pesan dari daftar terpadu.');
        await page.getByPlaceholder('Tulis pesan...').press('Enter');
        await expect(page.getByPlaceholder('Tulis pesan...')).toHaveValue('');
        await expect(
            page
                .locator('div.whitespace-pre-wrap')
                .filter({ hasText: 'Pesan dari daftar terpadu.' }),
        ).toHaveCount(1);
        await attachDocumentStates(
            page,
            testInfo,
            'dosen-unified-private-selected',
        );
        await page.setViewportSize({ width: 390, height: 844 });
        await page.reload();
        await expect(page.getByPlaceholder('Tulis pesan...')).toBeVisible();
        await page
            .getByRole('button', {
                name: 'Kembali ke daftar percakapan',
                exact: true,
            })
            .click();
        await expect(list).toBeVisible();
        await expect(group).toBeVisible();
        await expect(privateChat).toBeVisible();
        const mobileGroupTitleBounds = await group
            .getByText(student.studentName, { exact: true })
            .boundingBox();
        const mobilePrivateTitleBounds = await privateChat
            .getByText(student.studentName, { exact: true })
            .boundingBox();
        expect(
            Math.abs(mobileGroupTitleBounds!.x - mobilePrivateTitleBounds!.x),
        ).toBeLessThanOrEqual(1);
        const persistedSearch = list.getByRole('textbox', {
            name: 'Cari percakapan',
            exact: true,
        });
        await expect(persistedSearch).toHaveValue(student.studentName);
        await persistedSearch.press('Escape');
        await expect(persistedSearch).toHaveCount(0);
        await expect(
            list.getByRole('button', { name: 'Cari percakapan', exact: true }),
        ).toBeFocused();
        await list
            .getByRole('button', { name: 'Cari percakapan', exact: true })
            .click();
        await list
            .getByRole('textbox', { name: 'Cari percakapan', exact: true })
            .fill('tidak-ada-percakapan-159');
        await expect(list.locator('[data-thread-id]')).toHaveCount(0);
        await attachDocumentStates(
            page,
            testInfo,
            'dosen-unified-empty-search',
        );
    } finally {
        await context.close();
    }
});

test('student sees group and private together and own WhatsApp is a badge, not self-chat (#159)', async ({
    browser,
}, testInfo) => {
    test.setTimeout(180_000);
    const context = await browser.newContext({
        storageState: authStatePath(student.studentKey),
    });
    try {
        const page = await context.newPage();
        await page.goto('/mahasiswa/pesan');
        const list = page.getByRole('complementary', {
            name: 'Daftar percakapan',
        });
        await expect(
            list
                .locator('[data-thread-id]')
                .filter({ hasText: 'Grup Bimbingan' }),
        ).toBeVisible();
        const privateChat = list
            .locator('[data-thread-id]')
            .filter({ hasText: 'Pribadi' })
            .filter({ hasText: student.supervisorNames[0] });
        await expect(privateChat).toHaveCount(1);
        await attachDocumentStates(page, testInfo, 'mahasiswa-unified-list');
        await page.setViewportSize({ width: 1280, height: 844 });
        await privateChat.click();
        await page
            .getByPlaceholder('Tulis pesan...')
            .fill('Pesan pribadi mahasiswa dari workspace terpadu.');
        await page.getByPlaceholder('Tulis pesan...').press('Enter');
        await expect(page.getByPlaceholder('Tulis pesan...')).toHaveValue('');
        await expect(
            page.locator('div.whitespace-pre-wrap').filter({
                hasText: 'Pesan pribadi mahasiswa dari workspace terpadu.',
            }),
        ).toHaveCount(1);
        await attachDocumentStates(
            page,
            testInfo,
            'mahasiswa-unified-private-selected',
        );
        await page.goto('/settings/profile');
        await expect(page.getByText('WhatsApp', { exact: true })).toBeVisible();
        await expect(page.locator('a[href*="wa.me"]')).toHaveCount(0);
        await expect(
            page.getByRole('textbox', { name: 'Nomor HP', exact: true }),
        ).toHaveValue('081234567890');
        await expect(
            page.getByRole('button', { name: /Simpan/i }).first(),
        ).toBeVisible();
        await attachDocumentStates(
            page,
            testInfo,
            'own-profile-whatsapp-badge',
        );
    } finally {
        await context.close();
    }
});

test('lecturer Chat contains group, private and WhatsApp outside the other-actions menu (#159)', async ({
    browser,
}, testInfo) => {
    test.setTimeout(120_000);
    const context = await browser.newContext({
        storageState: authStatePath('dosen1'),
    });
    try {
        const page = await context.newPage();
        await page.goto('/dosen/mahasiswa-bimbingan');
        await page
            .getByRole('region', { name: 'Mahasiswa Aktif', exact: true })
            .getByRole('searchbox', { name: 'Cari nama atau NIM', exact: true })
            .fill(student.studentName);
        const row = page
            .getByRole('row')
            .filter({ hasText: student.studentName })
            .first();
        await expect(row).toContainText(/\d+ bimbingan/);
        await row.getByRole('button', { name: 'Chat', exact: true }).click();
        await expect(
            page.getByRole('menuitem', { name: 'Chat grup', exact: true }),
        ).toBeVisible();
        await expect(
            page.getByRole('menuitem', { name: 'Chat pribadi', exact: true }),
        ).toBeVisible();
        await expect(
            page.getByRole('menuitem', { name: 'WhatsApp', exact: true }),
        ).toHaveAttribute('href', 'https://wa.me/6281234567890');
        await attachDocumentStates(page, testInfo, 'dosen-student-chat-menu');
        await page.keyboard.press('Escape');
        await page.setViewportSize({ width: 1280, height: 844 });
        await row
            .getByRole('button', { name: 'Aksi lainnya', exact: true })
            .click();
        await expect(
            page.getByRole('menuitem', { name: 'WhatsApp', exact: true }),
        ).toHaveCount(0);
    } finally {
        await context.close();
    }
});

for (const role of ['dosen', 'mahasiswa'] as const) {
    for (const width of [1280, 390]) {
        test(`${role} opens authorized members by hover, touch or keyboard at ${width}px`, async ({
            browser,
        }) => {
            const context = await browser.newContext({
                storageState: authStatePath(
                    role === 'dosen' ? 'dosen1' : student.studentKey,
                ),
                viewport: { width, height: 844 },
                hasTouch: width === 390,
            });
            try {
                const page = await context.newPage();
                await page.goto(`/${role}/pesan`);
                const list = page.getByRole('complementary', {
                    name: 'Daftar percakapan',
                });
                const group = list
                    .locator('[data-thread-id]')
                    .filter({ hasText: 'Grup Bimbingan' })
                    .filter({
                        hasText:
                            role === 'dosen'
                                ? student.studentName
                                : 'Bimbingan',
                    });
                await group.click();
                const trigger = page.getByRole('button', {
                    name: 'Lihat 3 anggota percakapan',
                    exact: true,
                });
                await expect(trigger).toContainText('+1');
                if (width === 1280) {
                    await trigger.hover();
                } else {
                    await trigger.tap();
                }
                const members = page.getByRole('dialog', {
                    name: 'Anggota percakapan',
                    exact: true,
                });
                await expect(members).toBeVisible();
                await expect
                    .poll(async () => {
                        const pillBounds = await trigger
                            .locator('..')
                            .boundingBox();
                        const memberBounds = await members.boundingBox();
                        return Math.abs(
                            pillBounds!.x +
                                pillBounds!.width / 2 -
                                (memberBounds!.x + memberBounds!.width / 2),
                        );
                    })
                    .toBeLessThanOrEqual(1);
                for (const name of [
                    student.studentName,
                    ...student.supervisorNames,
                ]) {
                    await expect(
                        members.getByRole('link').filter({ hasText: name }),
                    ).toBeVisible();
                }
                await expect(members.getByRole('link')).toHaveCount(3);
                await members.getByRole('link').first().hover();
                await expect(members).toBeVisible();
                await page.keyboard.press('Escape');
                await expect(members).not.toBeVisible();
                await trigger.focus();
                await page.keyboard.press('Enter');
                await expect(members).toBeVisible();
                await page.keyboard.press('Tab');
                await expect(members.getByRole('link').first()).toBeFocused();
                await page.keyboard.press('Tab');
                await expect(members.getByRole('link').nth(1)).toBeFocused();
                await page.keyboard.press('Shift+Tab');
                await expect(members.getByRole('link').first()).toBeFocused();
                const ownProfile = members
                    .getByRole('link')
                    .filter({ hasText: 'Anda' });
                const ownUrl = await ownProfile.getAttribute('href');
                await ownProfile.click();
                await expect(page).toHaveURL(new URL(ownUrl!, page.url()).href);
                await expect(
                    page.getByRole('heading', { level: 1 }),
                ).toContainText(
                    role === 'dosen'
                        ? student.supervisorNames[0]
                        : student.studentName,
                );
            } finally {
                await context.close();
            }
        });
    }
}

for (const width of [320, 390]) {
    test(`mobile ${width}px keeps both bubble widths out of the sender-avatar lane`, async ({
        browser,
    }) => {
        const studentContext = await browser.newContext({
            storageState: authStatePath(student.studentKey),
            viewport: { width, height: 844 },
        });
        const lecturerContext = await browser.newContext({
            storageState: authStatePath('dosen1'),
            viewport: { width, height: 844 },
        });
        const ownedMessageIds: number[] = [];
        const incoming =
            'Bagian metode penelitian perlu menjelaskan rancangan pengujian dan hasil yang diharapkan. '
                .repeat(4)
                .trim();
        const outgoing =
            'Saya akan melengkapi rancangan pengujian beserta data pendukung untuk bimbingan berikutnya. '
                .repeat(4)
                .trim();
        try {
            const lecturerPage = await lecturerContext.newPage();
            await lecturerPage.goto('/dosen/pesan');
            const group = lecturerPage
                .getByRole('complementary', { name: 'Daftar percakapan' })
                .locator('[data-thread-id]')
                .filter({ hasText: 'Grup Bimbingan' })
                .filter({ hasText: student.studentName });
            const threadId = await group.getAttribute('data-thread-id');
            await group.click();
            await lecturerPage
                .getByPlaceholder('Tulis pesan...')
                .fill(incoming);
            const incomingResponse = lecturerPage.waitForResponse(
                (response) =>
                    response.request().method() === 'POST' &&
                    /\/messages$/.test(response.url()),
            );
            await lecturerPage
                .getByPlaceholder('Tulis pesan...')
                .press('Enter');
            const incomingAcknowledgement = (await (
                await incomingResponse
            ).json()) as ChatSendAcknowledgement;
            const incomingId = incomingAcknowledgement.messages[0].id;
            ownedMessageIds.push(incomingId);
            await expect(
                lecturerPage.getByPlaceholder('Tulis pesan...'),
            ).toHaveValue('');
            const page = await studentContext.newPage();
            await page.goto(`/mahasiswa/pesan?thread=${threadId}`);
            const received = page
                .locator(`[data-message-id="${incomingId}"]`)
                .getByText(incoming, { exact: true });
            await expect(received).toBeVisible();
            await page.getByPlaceholder('Tulis pesan...').fill(outgoing);
            const outgoingResponse = page.waitForResponse(
                (response) =>
                    response.request().method() === 'POST' &&
                    /\/messages$/.test(response.url()),
            );
            await page.getByPlaceholder('Tulis pesan...').press('Enter');
            const outgoingAcknowledgement = (await (
                await outgoingResponse
            ).json()) as ChatSendAcknowledgement;
            const outgoingId = outgoingAcknowledgement.messages[0].id;
            ownedMessageIds.push(outgoingId);
            await expect(page.getByPlaceholder('Tulis pesan...')).toHaveValue(
                '',
            );
            await page.reload();
            const sent = page
                .locator(`[data-message-id="${outgoingId}"]`)
                .getByText(outgoing, { exact: true });
            await expect(sent).toBeVisible();
            const receivedBubble = await received.locator('..').boundingBox();
            const sentBubble = await sent.locator('..').boundingBox();
            const avatar = await received
                .locator('xpath=ancestor::*[@data-message-id]')
                .locator('[data-slot="avatar"]')
                .boundingBox();
            expect(sentBubble!.x).toBeGreaterThanOrEqual(
                avatar!.x + avatar!.width + 8,
            );
            expect(receivedBubble!.x).toBeGreaterThanOrEqual(
                avatar!.x + avatar!.width + 8,
            );
            expect(
                Math.abs(sentBubble!.width - receivedBubble!.width),
            ).toBeLessThanOrEqual(1);
            expect(sentBubble!.x + sentBubble!.width).toBeLessThanOrEqual(
                width,
            );
            expect(
                receivedBubble!.x + receivedBubble!.width,
            ).toBeLessThanOrEqual(width);
        } finally {
            await studentContext.close();
            await lecturerContext.close();
            runArtisan([
                'tinker',
                `--execute=App\\Models\\MentorshipChatMessage::query()->whereIn('id', [${ownedMessageIds.join(',')}])->delete();`,
            ]);
        }
    });
}
