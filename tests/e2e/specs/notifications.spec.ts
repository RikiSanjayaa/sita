import { expect, test, type Locator, type Page } from '@playwright/test';

import { runArtisan } from '../support/db';
import { authStatePath, thesisScenarios } from '../support/thesis-fixtures';

function resetNotificationState(
    email: string,
    browserNotifications = false,
): void {
    runArtisan([
        'tinker',
        `--execute=$user = App\\Models\\User::query()->where("email", "${email}")->firstOrFail(); $user->notifications()->delete(); $user->forceFill(["browser_notifications_enabled" => ${browserNotifications ? 'true' : 'false'}, "notification_preferences" => ["pesanBaru" => true, "statusTugasAkhir" => true, "jadwalBimbingan" => true, "feedbackDokumen" => true, "reminderDeadline" => true, "pengumumanSistem" => true, "konfirmasiBimbingan" => true]])->save();`,
    ]);
}

async function openDosenBimbinganThread(
    page: Page,
    studentName: string,
): Promise<void> {
    await page.goto('/dosen/pesan-bimbingan');
    await expect(page).toHaveTitle(/Pesan Bimbingan Dosen/i);
    await page.getByPlaceholder('Cari mahasiswa...').fill(studentName);

    const threadButton = page
        .locator('button')
        .filter({ hasText: studentName })
        .filter({ hasText: 'Bimbingan' })
        .first();

    await expect(threadButton).toBeVisible();
    await threadButton.click();
    await expect(page.getByPlaceholder('Tulis pesan...')).toBeEnabled();
}

async function sendBimbinganMessageFromLecturer(
    page: Page,
    studentName: string,
    message: string,
): Promise<void> {
    await openDosenBimbinganThread(page, studentName);

    const submitRequest = page.waitForResponse(
        (response) =>
            response.request().method() === 'POST' &&
            /\/dosen\/pesan-bimbingan\/\d+\/messages$/.test(response.url()),
    );

    await page.getByPlaceholder('Tulis pesan...').fill(message);
    await page.getByPlaceholder('Tulis pesan...').press('Enter');
    await submitRequest;
    await expect(page.getByText(message).last()).toBeVisible();
}

async function openNotifications(page: Page): Promise<Locator> {
    await page.getByRole('button', { name: 'Open notifications' }).click();

    const panel = page.getByRole('dialog').last();

    await expect(panel.getByText('Notifikasi').first()).toBeVisible();

    return panel;
}

function setPesanBaruPreference(email: string, enabled: boolean): void {
    runArtisan([
        'tinker',
        `--execute=$user = App\\Models\\User::query()->where("email", "${email}")->firstOrFail(); $preferences = $user->resolvedNotificationPreferences(); $preferences["pesanBaru"] = ${enabled ? 'true' : 'false'}; $user->forceFill(["browser_notifications_enabled" => true, "notification_preferences" => $preferences])->save();`,
    ]);
}

function insertNotificationForUser(
    email: string,
    title: string,
    description: string,
    url: string,
    icon: string,
): void {
    runArtisan([
        'tinker',
        `--execute=$user = App\\Models\\User::query()->where("email", "${email}")->firstOrFail(); $user->notifications()->create(["id" => (string) Illuminate\\Support\\Str::uuid(), "type" => App\\Notifications\\RealtimeNotification::class, "data" => ["title" => "${title}", "description" => "${description}", "url" => "${url}", "icon" => "${icon}"], "read_at" => null, "created_at" => now(), "updated_at" => now()]);`,
    ]);
}

test.describe('Notifications', () => {
    test('mahasiswa receives a new message notification and can open then clear it', async ({
        browser,
    }) => {
        resetNotificationState('mahasiswa@sita.test');

        const studentContext = await browser.newContext({
            storageState: authStatePath(
                thesisScenarios.activeResearch.studentKey,
            ),
        });
        insertNotificationForUser(
            'mahasiswa@sita.test',
            'Pesan bimbingan baru',
            'Dr. Budi Santoso, M.Kom. mengirim pembaruan bimbingan.',
            '/mahasiswa/pesan',
            'message-square',
        );

        try {
            const studentPage = await studentContext.newPage();

            await studentPage.goto('/mahasiswa/dashboard');
            await expect(studentPage).toHaveTitle(/Dashboard/i);

            const notificationPanel = await openNotifications(studentPage);
            await expect(
                notificationPanel.getByText('Pesan bimbingan baru').first(),
            ).toBeVisible();
            await expect(
                notificationPanel
                    .getByText(
                        'Dr. Budi Santoso, M.Kom. mengirim pembaruan bimbingan.',
                    )
                    .first(),
            ).toBeVisible();

            await studentPage
                .locator('button')
                .filter({ hasText: 'Pesan bimbingan baru' })
                .first()
                .click();

            await expect(studentPage).toHaveURL(/\/mahasiswa\/pesan/);

            const clearedPanel = await openNotifications(studentPage);
            await clearedPanel
                .locator('div')
                .filter({ hasText: 'Pesan bimbingan baru' })
                .getByLabel('Hapus notifikasi')
                .click();
            await expect(
                clearedPanel.getByText('Pesan bimbingan baru'),
            ).toHaveCount(0);
        } finally {
            await studentContext.close();
        }
    });

    test('notification settings can disable pesan baru notifications', async ({
        browser,
    }) => {
        resetNotificationState('mahasiswa@sita.test');

        const studentContext = await browser.newContext({
            storageState: authStatePath(
                thesisScenarios.activeResearch.studentKey,
            ),
        });
        const lecturerContext = await browser.newContext({
            storageState: authStatePath('dosen1'),
        });

        const message = `Pesan dibisukan Playwright ${Date.now()}`;

        try {
            const studentPage = await studentContext.newPage();
            const lecturerPage = await lecturerContext.newPage();

            setPesanBaruPreference('mahasiswa@sita.test', false);

            await studentPage.goto('/settings/notifications');
            await expect(studentPage).toHaveTitle(/Pengaturan notifikasi/i);

            await expect(
                studentPage.getByRole('switch', {
                    name: 'Aktifkan notifikasi browser',
                }),
            ).toHaveAttribute('data-state', 'checked');
            await expect(
                studentPage.getByRole('switch', {
                    name: 'Aktifkan Pesan baru',
                }),
            ).toHaveAttribute('data-state', 'unchecked');

            await sendBimbinganMessageFromLecturer(
                lecturerPage,
                thesisScenarios.activeResearch.studentName,
                message,
            );

            await studentPage.goto('/mahasiswa/dashboard');
            await expect(studentPage).toHaveTitle(/Dashboard/i);

            const notificationPanel = await openNotifications(studentPage);
            await expect(
                notificationPanel.getByText('Pesan bimbingan baru'),
            ).toHaveCount(0);
        } finally {
            await studentContext.close();
            await lecturerContext.close();
        }
    });
});
