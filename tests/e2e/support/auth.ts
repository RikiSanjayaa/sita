import fs from 'node:fs';

import { chromium, expect, request, type Page } from '@playwright/test';

import { isFilamentAccount, newFilamentContext } from './filament-auth';
import {
    authStatePath,
    playwrightAccounts,
    type PlaywrightAccountKey,
} from './thesis-fixtures';

async function login(
    accountKey: PlaywrightAccountKey,
    baseURL: string,
): Promise<void> {
    const account = playwrightAccounts[accountKey];

    if (isFilamentAccount(accountKey)) {
        const browser = await chromium.launch();

        try {
            const context = await newFilamentContext(browser, accountKey);
            await context.storageState({ path: authStatePath(accountKey) });
            await context.close();

            return;
        } finally {
            await browser.close();
        }
    }

    const api = await request.newContext({ baseURL });

    try {
        for (let attempt = 1; attempt <= 3; attempt += 1) {
            try {
                const loginPage = await api.get('/login');
                const loginHtml = await loginPage.text();
                const csrfToken =
                    loginHtml.match(
                        /<meta name="csrf-token" content="([^"]+)"/i,
                    )?.[1] ??
                    loginHtml.match(
                        /<input[^>]+name="_token"[^>]+value="([^"]+)"/i,
                    )?.[1] ??
                    '';

                const loginResponse = await api.post('/login', {
                    form: {
                        _token: csrfToken,
                        email: account.email,
                        password: account.password,
                    },
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        Referer: new URL('/login', baseURL).toString(),
                    },
                });

                if (!loginResponse.ok()) {
                    throw new Error(
                        `login failed with status ${loginResponse.status()}`,
                    );
                }

                const landingResponse = await api.get(account.landingPath);

                if (!landingResponse.ok()) {
                    throw new Error(
                        `landing failed with status ${landingResponse.status()}`,
                    );
                }

                await api.storageState({ path: authStatePath(accountKey) });

                return;
            } catch (error) {
                if (attempt === 3) {
                    if (fs.existsSync(authStatePath(accountKey))) {
                        return;
                    }

                    throw new Error(
                        `Failed to capture auth state for ${accountKey} after ${attempt} attempts: ${String(error)}`,
                    );
                }
            }
        }
    } finally {
        await api.dispose();
    }
}

export async function captureAuthStates(baseURL: string): Promise<void> {
    for (const accountKey of Object.keys(
        playwrightAccounts,
    ) as PlaywrightAccountKey[]) {
        console.log(`[playwright-auth] capturing ${accountKey}`);
        await login(accountKey, baseURL);
        console.log(`[playwright-auth] captured ${accountKey}`);
    }
}

export async function expectLandingPage(
    page: Page,
    accountKey: PlaywrightAccountKey,
): Promise<void> {
    await expect(page).toHaveURL(
        new RegExp(
            `${playwrightAccounts[accountKey].landingPath.replace('/', '/')}$`,
        ),
    );
}
