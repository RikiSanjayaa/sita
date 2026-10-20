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
    await page.waitForURL(/\/dosen\/pesan(\?|$)/);
    await page.getByPlaceholder('Cari grup...').fill(studentName);

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
            /\/dosen\/pesan\/\d+\/messages$/.test(response.url()),
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

function expectNoStoredNotificationsForUser(email: string): void {
    runArtisan([
        'tinker',
        `--execute=$user = App\\Models\\User::query()->where("email", "${email}")->firstOrFail(); if ($user->notifications()->exists()) { fwrite(STDERR, "Unexpected stored notification for ${email}\\n"); exit(1); }`,
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
        const notificationTitle = `Playwright bimbingan notification ${Date.now()}`;

        const studentContext = await browser.newContext({
            storageState: authStatePath(
                thesisScenarios.activeResearch.studentKey,
            ),
        });
        insertNotificationForUser(
            'mahasiswa@sita.test',
            notificationTitle,
            'Dr. Budi Santoso, M.Kom. mengirim pembaruan bimbingan.',
            '/mahasiswa/pesan',
            'message-square',
        );

        try {
            const studentPage = await studentContext.newPage();

            await studentPage.goto('/mahasiswa/dashboard');

            const notificationPanel = await openNotifications(studentPage);
            const notificationCard = notificationPanel
                .getByTestId('notification-card')
                .filter({ hasText: notificationTitle });
            await expect(notificationCard).toBeVisible();

            await notificationCard.click({ position: { x: 20, y: 70 } });

            await expect(studentPage).toHaveURL(/\/mahasiswa\/pesan/);

            const clearedPanel = await openNotifications(studentPage);
            const notificationToDelete = clearedPanel
                .getByTestId('notification-card')
                .filter({ hasText: notificationTitle });
            await notificationToDelete.getByLabel('Hapus notifikasi').click();
            await expect(
                clearedPanel.getByTestId('notification-card'),
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
            await studentPage.goto('/mahasiswa/dashboard');
            await sendBimbinganMessageFromLecturer(
                lecturerPage,
                thesisScenarios.activeResearch.studentName,
                message,
            );
            expectNoStoredNotificationsForUser('mahasiswa@sita.test');

            const notificationPanel = await openNotifications(studentPage);
            await expect(
                notificationPanel.getByTestId('notification-card'),
            ).toHaveCount(0);
        } finally {
            await studentContext.close();
            await lecturerContext.close();
        }
    });
});
