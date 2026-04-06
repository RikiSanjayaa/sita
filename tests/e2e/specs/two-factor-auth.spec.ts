import crypto from 'node:crypto';

import { expect, test, type Page } from '@playwright/test';

import {
    authStatePath,
    playwrightAccounts,
    thesisScenarios,
} from '../support/thesis-fixtures';

function decodeBase32(secret: string): Buffer {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    const normalized = secret
        .replace(/\s+/g, '')
        .replace(/=+$/g, '')
        .toUpperCase();
    let bits = '';

    for (const char of normalized) {
        const value = alphabet.indexOf(char);

        if (value === -1) {
            continue;
        }

        bits += value.toString(2).padStart(5, '0');
    }

    const bytes: number[] = [];

    for (let index = 0; index + 8 <= bits.length; index += 8) {
        bytes.push(Number.parseInt(bits.slice(index, index + 8), 2));
    }

    return Buffer.from(bytes);
}

function generateTotp(secret: string, timestamp = Date.now()): string {
    const key = decodeBase32(secret);
    const counter = Math.floor(timestamp / 1000 / 30);
    const counterBuffer = Buffer.alloc(8);

    counterBuffer.writeBigUInt64BE(BigInt(counter));

    const digest = crypto
        .createHmac('sha1', key)
        .update(counterBuffer)
        .digest();
    const offset = digest[digest.length - 1] & 0x0f;
    const binary =
        ((digest[offset] & 0x7f) << 24) |
        ((digest[offset + 1] & 0xff) << 16) |
        ((digest[offset + 2] & 0xff) << 8) |
        (digest[offset + 3] & 0xff);

    return String(binary % 1_000_000).padStart(6, '0');
}

async function confirmPasswordIfNeeded(
    page: Page,
    password: string,
): Promise<void> {
    if (!page.url().includes('/confirm-password')) {
        return;
    }

    await expect(page).toHaveTitle(/Confirm password/i);
    await page.getByLabel('Password').fill(password);
    await page.locator('[data-test="confirm-password-button"]').click();
    await expect(page).toHaveURL(/\/settings\/two-factor/);
}

async function logoutFromUserMenu(page: Page): Promise<void> {
    await page.locator('[data-test="header-user-menu-trigger"]').click();
    await page.locator('[data-test="logout-button"]').click();
    await expect(page).toHaveURL(/(?:\/$|\/login$)/);
}

test.describe('Two-factor authentication flow', () => {
    test('user can enable 2FA from settings and sign in through the authentication-code challenge', async ({
        browser,
    }) => {
        const studentKey = thesisScenarios.firstSubmission.studentKey;
        const account = playwrightAccounts[studentKey];
        const authenticatedContext = await browser.newContext({
            storageState: authStatePath(studentKey),
        });
        const freshLoginContext = await browser.newContext();

        try {
            const settingsPage = await authenticatedContext.newPage();

            await settingsPage.goto('/settings/two-factor');
            await confirmPasswordIfNeeded(settingsPage, account.password);
            await expect(settingsPage).toHaveTitle(
                /Two-Factor Authentication/i,
            );
            await expect(
                settingsPage.getByText('Autentikasi dua faktor').first(),
            ).toBeVisible();

            await settingsPage
                .getByRole('button', { name: 'Enable 2FA' })
                .click();

            const setupModal = settingsPage.getByRole('dialog', {
                name: 'Enable Two-Factor Authentication',
            });

            await expect(setupModal).toBeVisible();

            const setupKeyInput = setupModal.locator('input[readonly]');
            await expect(setupKeyInput).toBeVisible();

            const setupKey = (await setupKeyInput.inputValue()).trim();
            expect(setupKey).not.toBe('');

            await setupModal.getByRole('button', { name: 'Continue' }).click();
            await expect(
                settingsPage.getByRole('dialog', {
                    name: 'Verify Authentication Code',
                }),
            ).toBeVisible();

            await settingsPage
                .locator('input[name="code"]')
                .fill(generateTotp(setupKey));
            await settingsPage.getByRole('button', { name: 'Confirm' }).click();

            await expect(
                settingsPage.getByRole('button', { name: 'Disable 2FA' }),
            ).toBeVisible();
            await expect(
                settingsPage.getByRole('button', {
                    name: 'View Recovery Codes',
                }),
            ).toBeVisible();

            await settingsPage
                .getByRole('button', { name: 'View Recovery Codes' })
                .click();
            await expect(
                settingsPage.getByRole('list', { name: 'Recovery codes' }),
            ).toBeVisible();

            await logoutFromUserMenu(settingsPage);

            const loginPage = await freshLoginContext.newPage();
            await loginPage.goto('/login');
            await expect(loginPage).toHaveTitle(/Masuk/i);
            await loginPage.getByLabel('Email').fill(account.email);
            await loginPage.getByLabel('Kata sandi').fill(account.password);
            await loginPage.getByRole('button', { name: 'Masuk' }).click();

            await expect(loginPage).toHaveURL(/two-factor-challenge/);
            await expect(loginPage).toHaveTitle(/Two-Factor Authentication/i);
            await expect(
                loginPage.getByText('Authentication Code').first(),
            ).toBeVisible();

            await loginPage
                .locator('input[name="code"]')
                .fill(generateTotp(setupKey));
            await loginPage.getByRole('button', { name: 'Continue' }).click();

            await expect(loginPage).toHaveURL(/\/mahasiswa\/dashboard/);
        } finally {
            await authenticatedContext.close();
            await freshLoginContext.close();
        }
    });
});
