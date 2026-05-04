import crypto from 'node:crypto';

import { expect, test, type Page } from '@playwright/test';

import { runArtisan } from '../support/db';

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

function resetTwoFactorTestUser(email: string, password: string): void {
    runArtisan([
        'tinker',
        `--execute=$role = App\\Models\\Role::query()->where("name", "mahasiswa")->firstOrFail(); $program = App\\Models\\ProgramStudi::query()->where("slug", "ilkom")->firstOrFail(); $user = App\\Models\\User::query()->updateOrCreate(["email" => "${email}"], ["name" => "Playwright Two Factor", "phone_number" => "081234567899", "password" => Illuminate\\Support\\Facades\\Hash::make("${password}"), "last_active_role" => "mahasiswa", "two_factor_secret" => null, "two_factor_recovery_codes" => null, "two_factor_confirmed_at" => null]); $user->roles()->syncWithoutDetaching([$role->id]); App\\Models\\MahasiswaProfile::query()->updateOrCreate(["user_id" => $user->id], ["nim" => "2210519999", "angkatan" => 2022, "program_studi_id" => $program->id, "concentration" => "Jaringan"]);`,
    ]);
}

async function loginWithPassword(
    page: Page,
    email: string,
    password: string,
): Promise<void> {
    await page.goto('/login');
    await expect(page).toHaveTitle(/Masuk/i);
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Kata sandi').fill(password);
    await page.getByRole('button', { name: 'Masuk' }).click();
}

test.describe('Two-factor authentication flow', () => {
    test('user can enable 2FA from settings and sign in through the authentication-code challenge', async ({
        browser,
    }) => {
        const account = {
            email: 'playwright-2fa@sita.test',
            password: 'password',
        };

        resetTwoFactorTestUser(account.email, account.password);

        const authenticatedContext = await browser.newContext();
        const freshLoginContext = await browser.newContext();

        try {
            const settingsPage = await authenticatedContext.newPage();

            await loginWithPassword(
                settingsPage,
                account.email,
                account.password,
            );
            await expect(settingsPage).toHaveURL(/\/mahasiswa\/dashboard/);

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
            await loginWithPassword(loginPage, account.email, account.password);

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
