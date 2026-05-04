import {
    expect,
    type Browser,
    type BrowserContext,
    type Page,
} from '@playwright/test';

import {
    playwrightAccounts,
    type PlaywrightAccountKey,
} from './thesis-fixtures';

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:9010';

export async function loginToFilament(
    page: Page,
    accountKey: 'admin' | 'superadmin' = 'admin',
): Promise<void> {
    const account = playwrightAccounts[accountKey];

    await page.goto(new URL('/admin/login', baseURL).toString());
    await page
        .getByRole('textbox', { name: /Email address\*/i })
        .fill(account.email);
    await page.locator('input[id="form.password"]').fill(account.password);
    await page.getByRole('button', { name: /Masuk ke dashboard/i }).click();
    await expect(page).toHaveURL(/\/admin$/);
}

export async function newFilamentContext(
    browser: Browser,
    accountKey: 'admin' | 'superadmin' = 'admin',
): Promise<BrowserContext> {
    const context = await browser.newContext();
    const page = await context.newPage();

    await loginToFilament(page, accountKey);
    await page.close();

    return context;
}

export function isFilamentAccount(
    accountKey: PlaywrightAccountKey,
): accountKey is 'admin' | 'superadmin' {
    return accountKey === 'admin' || accountKey === 'superadmin';
}
