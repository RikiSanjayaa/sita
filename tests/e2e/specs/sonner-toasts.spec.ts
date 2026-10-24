import { expect, test, type Page } from '@playwright/test';

import { runArtisan } from '../support/db';
import { authStatePath } from '../support/thesis-fixtures';

async function waitForNotifications(page: Page): Promise<void> {
    await page.waitForFunction(() => {
        const data = JSON.parse(document.getElementById('app')!.dataset.page!);
        return (
            window.Echo?.connector.pusher.channel(
                `private-App.Models.User.${data.props.auth.user.id}`,
            )?.subscribed === true
        );
    });
}

function notify(title: string): void {
    const port = process.env.PLAYWRIGHT_REVERB_PORT ?? '9011';
    runArtisan([
        'tinker',
        `--execute=config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'local-key', 'broadcasting.connections.reverb.secret' => 'local-secret', 'broadcasting.connections.reverb.app_id' => 'local-app', 'broadcasting.connections.reverb.options.host' => '127.0.0.1', 'broadcasting.connections.reverb.options.port' => ${port}, 'broadcasting.connections.reverb.options.scheme' => 'http', 'broadcasting.connections.reverb.options.useTLS' => false]); $user = App\\Models\\User::where('email', 'mahasiswa@sita.test')->firstOrFail(); $user->notify(new App\\Notifications\\RealtimeNotification(['title' => '${title}', 'description' => 'Dr. Budi Santoso memperbarui catatan bimbingan.', 'icon' => 'message-square', 'url' => '/mahasiswa/upload-dokumen']));`,
    ]);
}

test.describe('Sonner feedback', () => {
    test('kaprodi quota redirects show one toast for each repeated action', async ({
        browser,
    }) => {
        const context = await browser.newContext({
            storageState: authStatePath('kaprodi'),
        });
        try {
            const page = await context.newPage();
            await page.goto('/kaprodi/dosen-prodi');
            await expect(page).toHaveURL(/\/kaprodi\/dosen-prodi$/);
            await expect(
                page.getByRole('heading', {
                    name: 'Daftar Dosen',
                    exact: true,
                }),
            ).toBeVisible();
            for (let action = 0; action < 2; action++) {
                await page
                    .getByRole('button', { name: 'Atur kuota bimbingan' })
                    .first()
                    .click();
                await page
                    .getByRole('button', { name: 'Simpan Kuota', exact: true })
                    .click();
                const toast = page
                    .locator('[data-sonner-toast][data-type="success"]')
                    .filter({
                        hasText: 'Kuota bimbingan dosen berhasil diperbarui.',
                    });
                await expect(toast).toHaveCount(1);
                await expect(toast).toBeVisible();
                await toast
                    .getByRole('button', { name: 'Tutup notifikasi' })
                    .click();
                await expect(toast).not.toBeVisible();
            }
        } finally {
            await context.close();
        }
    });

    for (const scheme of ['ubg-blue', 'indigo', 'olive', 'graphite']) {
        for (const mode of ['light', 'dark'] as const) {
            test(`settings flash follows ${scheme} ${mode} without duplicate feedback`, async ({
                browser,
            }) => {
                const context = await browser.newContext({
                    storageState: authStatePath('mahasiswa'),
                    viewport: {
                        width: mode === 'light' ? 390 : 1280,
                        height: 900,
                    },
                    colorScheme: mode,
                });
                await context.addInitScript(
                    ({ scheme, mode }) => {
                        localStorage.setItem('sita_theme_preset', scheme);
                        localStorage.setItem('appearance', mode);
                    },
                    { scheme, mode },
                );
                try {
                    const page = await context.newPage();
                    await page.goto('/settings/profile');
                    await expect(page.locator('html')).toHaveAttribute(
                        'data-theme',
                        scheme,
                    );
                    await page
                        .getByRole('button', {
                            name: 'Simpan perubahan',
                            exact: true,
                        })
                        .click();
                    const toast = page.locator(
                        '[data-sonner-toast][data-type="success"]',
                    );
                    await expect(toast).toHaveCount(1);
                    await expect(toast).toBeVisible();
                    await expect(
                        page.locator('[data-sonner-toaster]'),
                    ).toHaveAttribute('data-sonner-theme', mode);
                    await expect(
                        page.getByText('Tersimpan', { exact: true }),
                    ).toHaveCount(0);
                    const bounds = await toast.boundingBox();
                    expect(bounds!.x).toBeGreaterThanOrEqual(0);
                    expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(
                        mode === 'light' ? 390 : 1280,
                    );
                } finally {
                    await context.close();
                }
            });
        }
    }

    test('realtime toast can close without reading and Buka marks it read before navigating', async ({
        browser,
    }) => {
        const context = await browser.newContext({
            storageState: authStatePath('mahasiswa'),
        });
        try {
            const page = await context.newPage();
            await page.goto('/mahasiswa/dashboard');
            await waitForNotifications(page);
            const title = `Pembaruan bimbingan Sonner ${Date.now()}`;
            notify(title);
            const toast = page
                .getByTestId('notification-toast')
                .filter({ hasText: title });
            await expect(toast).toBeVisible();
            await toast
                .getByRole('button', { name: 'Tutup notifikasi' })
                .click();
            await expect(toast).not.toBeVisible();
            await page
                .getByRole('button', { name: 'Open notifications' })
                .click();
            const card = page
                .getByTestId('notification-card')
                .filter({ hasText: title });
            await expect(card).toBeVisible();
            await page.keyboard.press('Escape');
            notify(`${title} berikutnya`);
            const next = page
                .getByTestId('notification-toast')
                .filter({ hasText: `${title} berikutnya` });
            await expect(next).toBeVisible();
            await next
                .getByRole('button', { name: 'Buka', exact: true })
                .click();
            await expect(page).toHaveURL(/\/mahasiswa\/upload-dokumen$/);
            await page
                .getByRole('button', { name: 'Open notifications' })
                .click();
            const readCard = page
                .getByTestId('notification-card')
                .filter({ hasText: `${title} berikutnya` });
            await expect(
                readCard.getByRole('button', { name: 'Hapus notifikasi' }),
            ).toBeVisible();
        } finally {
            await context.close();
        }
    });
});
test('Sonner error redirects survive a full-page visit and dismiss cleanly', async ({
    browser,
}) => {
    const context = await browser.newContext({
        storageState: authStatePath('mahasiswa'),
    });
    try {
        const page = await context.newPage();
        await page.goto('/mahasiswa/tugas-akhir');
        await expect(
            page.getByRole('heading', { name: 'Skripsi', exact: true }),
        ).toBeVisible();
        await Promise.all([
            page.waitForNavigation(),
            page.evaluate(() => {
                const { props } = JSON.parse(
                    document.getElementById('app')!.dataset.page!,
                );
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `/mahasiswa/tugas-akhir/${props.submission.id}`;
                const fields = {
                    _method: 'PATCH',
                    _token: document
                        .querySelector('meta[name="csrf-token"]')!
                        .getAttribute('content')!,
                    title_id: props.submission.title_id,
                    proposal_summary:
                        props.submission.proposal_summary ||
                        'Ringkasan penelitian.',
                    discussion_lecturer_user_id: String(
                        props.submission.discussion_lecturer_user_id ||
                            props.advisorProfiles[0].id,
                    ),
                };
                for (const [name, value] of Object.entries(fields)) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    form.append(input);
                }
                document.body.append(form);
                form.submit();
            }),
        ]);
        const error = page.locator('[data-sonner-toast][data-type="error"]');
        await expect(error).toHaveCount(1);
        await expect(error).toContainText(
            'Pengajuan hanya dapat diedit saat masih ditinjau admin',
        );
        await error.getByRole('button', { name: 'Tutup notifikasi' }).click();
        await expect(error).not.toBeVisible();
        await page.reload();
        await expect(
            page.getByRole('heading', { name: 'Skripsi', exact: true }),
        ).toBeVisible();
        await expect(page.locator('[data-sonner-toast]')).toHaveCount(0);
    } finally {
        await context.close();
    }
});
