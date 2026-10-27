import { expect, test } from '@playwright/test';

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
