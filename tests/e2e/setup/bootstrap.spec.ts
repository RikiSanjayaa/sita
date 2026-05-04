import { request, test } from '@playwright/test';

import { captureAuthStates } from '../support/auth';
import { ensurePlaywrightFilesystem, runArtisan } from '../support/db';

test.setTimeout(5 * 60 * 1000);

async function waitForApplication(baseURL: string): Promise<void> {
    const api = await request.newContext({ baseURL });

    try {
        for (let attempt = 0; attempt < 60; attempt += 1) {
            try {
                const response = await api.get('/up');

                if (response.ok()) {
                    return;
                }
            } catch {
                // Wait for the Laravel server started by Playwright webServer.
            }

            await new Promise((resolve) => setTimeout(resolve, 1000));
        }
    } finally {
        await api.dispose();
    }

    throw new Error(`Laravel app did not become ready at ${baseURL}/up.`);
}

test('bootstrap Playwright database and auth states', async ({ baseURL }) => {
    const resolvedBaseUrl = baseURL ?? 'http://127.0.0.1:9010';

    ensurePlaywrightFilesystem();
    runArtisan(['optimize:clear'], resolvedBaseUrl);
    runArtisan(['migrate:fresh', '--seed', '--force'], resolvedBaseUrl);

    await waitForApplication(resolvedBaseUrl);
    await captureAuthStates(resolvedBaseUrl);
    await waitForApplication(resolvedBaseUrl);
});
