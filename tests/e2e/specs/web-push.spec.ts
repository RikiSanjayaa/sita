import { generateKeyPairSync, randomBytes } from 'node:crypto';

import { expect, test, type BrowserContext, type Page } from '@playwright/test';

import { runArtisan } from '../support/db';
import { authStatePath } from '../support/thesis-fixtures';

// Emulate the browser permission/provider boundary. Service worker, IndexedDB,
// authenticated API, session ownership, reload and logout remain real.
async function virtualPushDevice(
    context: BrowserContext,
    device: string,
): Promise<void> {
    const key = generateKeyPairSync('ec', {
        namedCurve: 'prime256v1',
    }).publicKey.export({ format: 'jwk' });
    const p256dh = Buffer.concat([
        Buffer.from([4]),
        Buffer.from(key.x!, 'base64url'),
        Buffer.from(key.y!, 'base64url'),
    ]).toString('base64url');

    await context.addInitScript(
        ({ device, p256dh, auth }) => {
            Object.defineProperty(Notification, 'permission', {
                configurable: true,
                get: () => 'granted',
            });
            Notification.requestPermission = async () => 'granted';
            const storageKey = 'sita-e2e-virtual-push';
            const sequenceKey = 'sita-e2e-virtual-sequence';
            type Stored = { endpoint: string; applicationServerKey: number[] };
            const subscription = (stored: Stored) => ({
                endpoint: stored.endpoint,
                options: {
                    userVisibleOnly: true,
                    applicationServerKey: new Uint8Array(
                        stored.applicationServerKey,
                    ).buffer,
                },
                toJSON: () => ({
                    endpoint: stored.endpoint,
                    keys: { p256dh, auth },
                }),
                unsubscribe: async () => {
                    localStorage.removeItem(storageKey);
                    return true;
                },
            });

            PushManager.prototype.getSubscription = async function () {
                const stored = localStorage.getItem(storageKey);
                return stored
                    ? (subscription(JSON.parse(stored)) as PushSubscription)
                    : null;
            };
            PushManager.prototype.subscribe = async function (options) {
                const sequence =
                    Number(localStorage.getItem(sequenceKey) ?? '0') + 1;
                localStorage.setItem(sequenceKey, String(sequence));
                const stored: Stored = {
                    endpoint: `https://fcm.googleapis.com/fcm/send/sita-e2e-${device}-${sequence}`,
                    applicationServerKey: Array.from(
                        new Uint8Array(
                            options!.applicationServerKey as ArrayBuffer,
                        ),
                    ),
                };
                localStorage.setItem(storageKey, JSON.stringify(stored));
                return subscription(stored) as PushSubscription;
            };
        },
        { device, p256dh, auth: randomBytes(16).toString('base64url') },
    );
}

async function openDeviceSettings(page: Page): Promise<void> {
    await page.goto('/settings/notifications');
    await expect(
        page.getByRole('heading', { name: 'Perangkat ini' }),
    ).toBeVisible();
}

function cleanupVirtualSubscriptions(): void {
    runArtisan([
        'tinker',
        '--execute=NotificationChannels\\WebPush\\PushSubscription::query()->where("endpoint", "like", "https://fcm.googleapis.com/fcm/send/sita-e2e-%")->delete();',
    ]);
}

test.describe('Web Push device settings', () => {
    test.afterEach(() => cleanupVirtualSubscriptions());

    test('permission alone is inactive; explicit enrollment survives reload and can be removed', async ({
        browser,
    }) => {
        const context = await browser.newContext({
            storageState: authStatePath('mahasiswa'),
            permissions: ['notifications'],
        });
        await virtualPushDevice(context, 'enrollment');

        try {
            const page = await context.newPage();
            await openDeviceSettings(page);
            await page
                .getByRole('button', {
                    name: 'Aktifkan di perangkat ini',
                    exact: true,
                })
                .click();
            await expect(
                page.getByRole('button', {
                    name: 'Matikan di perangkat ini',
                    exact: true,
                }),
            ).toBeVisible();

            await page.reload();
            await expect(
                page.getByRole('button', {
                    name: 'Matikan di perangkat ini',
                    exact: true,
                }),
            ).toBeVisible();
            await page
                .getByRole('button', {
                    name: 'Matikan di perangkat ini',
                    exact: true,
                })
                .click();
            await expect(
                page.getByRole('button', {
                    name: 'Aktifkan di perangkat ini',
                    exact: true,
                }),
            ).toBeVisible();
            await expect(
                page.getByRole('button', {
                    name: 'Matikan di perangkat ini',
                    exact: true,
                }),
            ).toHaveCount(0);
        } finally {
            await context.close();
        }
    });

    test('logout and another account cannot inherit an enrolled browser', async ({
        browser,
    }) => {
        const context = await browser.newContext({
            permissions: ['notifications'],
        });
        await virtualPushDevice(context, 'account-switch');

        try {
            const page = await context.newPage();
            await page.goto('/login');
            await page
                .getByLabel('Email', { exact: true })
                .fill('mahasiswa@sita.test');
            await page
                .getByLabel('Kata sandi', { exact: true })
                .fill('password');
            await page
                .getByRole('button', { name: 'Masuk', exact: true })
                .click();
            await expect(page).toHaveURL(/\/mahasiswa\/dashboard/);
            await openDeviceSettings(page);
            await page
                .getByRole('button', {
                    name: 'Aktifkan di perangkat ini',
                    exact: true,
                })
                .click();
            await expect(
                page.getByRole('button', {
                    name: 'Matikan di perangkat ini',
                    exact: true,
                }),
            ).toBeVisible();

            await page.getByRole('button', { name: /Mahasiswa SiTA/ }).click();
            await page
                .getByRole('menuitem', { name: /Keluar|Log out/ })
                .click();
            await expect(page).toHaveURL(/\/(?:login)?$/);
            await page.goto('/login');
            await page
                .getByLabel('Email', { exact: true })
                .fill('dosen@sita.test');
            await page
                .getByLabel('Kata sandi', { exact: true })
                .fill('password');
            await page
                .getByRole('button', { name: 'Masuk', exact: true })
                .click();
            await expect(page).toHaveURL(/\/dosen\/dashboard/);
            await openDeviceSettings(page);
            await expect(
                page.getByRole('button', {
                    name: 'Matikan di perangkat ini',
                    exact: true,
                }),
            ).toHaveCount(0);
            await page
                .getByRole('button', {
                    name: 'Aktifkan di perangkat ini',
                    exact: true,
                })
                .click();
            await expect(
                page.getByRole('button', {
                    name: 'Matikan di perangkat ini',
                    exact: true,
                }),
            ).toBeVisible();
        } finally {
            await context.close();
        }
    });

    test('a rejected registration remains inactive and the user can retry explicitly', async ({
        browser,
    }) => {
        const context = await browser.newContext({
            storageState: authStatePath('mahasiswa'),
            permissions: ['notifications'],
        });
        await virtualPushDevice(context, 'recovery');

        try {
            const page = await context.newPage();
            await openDeviceSettings(page);
            await page.route(
                '**/settings/push-subscriptions',
                async (route) => {
                    if (route.request().method() === 'POST') {
                        await route.fulfill({
                            status: 503,
                            json: { message: 'Unavailable' },
                        });
                    } else {
                        await route.continue();
                    }
                },
            );
            await page
                .getByRole('button', {
                    name: 'Aktifkan di perangkat ini',
                    exact: true,
                })
                .click();
            await expect(page.getByRole('alert')).toContainText(
                'Server menolak permintaan notifikasi. Coba lagi nanti.',
            );
            await expect(
                page.getByRole('button', {
                    name: 'Matikan di perangkat ini',
                    exact: true,
                }),
            ).toHaveCount(0);

            await page.unroute('**/settings/push-subscriptions');
            await page
                .getByRole('button', {
                    name: 'Aktifkan di perangkat ini',
                    exact: true,
                })
                .click();
            await expect(
                page.getByRole('button', {
                    name: 'Matikan di perangkat ini',
                    exact: true,
                }),
            ).toBeVisible();
            await expect(page.getByRole('alert')).toHaveCount(0);
        } finally {
            await context.close();
        }
    });
});
