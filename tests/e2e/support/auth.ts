import { chromium, expect, type Page } from '@playwright/test';

import {
    playwrightAccounts,
    type PlaywrightAccountKey,
    authStatePath,
} from './thesis-fixtures';

async function login(
    page: Page,
    accountKey: PlaywrightAccountKey,
    baseURL: string,
): Promise<void> {
    const account = playwrightAccounts[accountKey];
    const passwordInput = page
        .locator(
            'input[autocomplete="current-password"], input[name="password"], input[id$="password"]',
        )
        .first();

    await page.goto(new URL(account.loginPath, baseURL).toString());
    await page.getByLabel(/email/i).fill(account.email);
    await passwordInput.fill(account.password);
    await page.getByRole('button', { name: /masuk|sign in|login/i }).click();
    await page.waitForURL(`**${account.landingPath}`);
}

export async function captureAuthStates(baseURL: string): Promise<void> {
    const browser = await chromium.launch();

    try {
        for (const accountKey of Object.keys(
            playwrightAccounts,
        ) as PlaywrightAccountKey[]) {
            const context = await browser.newContext();
            const page = await context.newPage();

            await login(page, accountKey, baseURL);
            await context.storageState({ path: authStatePath(accountKey) });
            await context.close();
        }
    } finally {
        await browser.close();
    }
}

export async function expectLandingPage(
    page: Page,
    accountKey: PlaywrightAccountKey,
): Promise<void> {
    await expect(page).toHaveURL(
        new RegExp(
            `${playwrightAccounts[accountKey].landingPath.replace('/', '\\/')}$`,
        ),
    );
}
