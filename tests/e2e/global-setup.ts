import { request, type FullConfig } from '@playwright/test';

import { captureAuthStates } from './support/auth';
import { ensurePlaywrightFilesystem, runArtisan } from './support/db';

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

export default async function globalSetup(config: FullConfig): Promise<void> {
    const baseURL =
        (config.projects[0]?.use.baseURL as string | undefined) ??
        'http://127.0.0.1:9000';

    ensurePlaywrightFilesystem();
    runArtisan(['optimize:clear'], baseURL);
    runArtisan(['migrate:fresh', '--seed', '--force'], baseURL);

    await waitForApplication(baseURL);
    await captureAuthStates(baseURL);
}
