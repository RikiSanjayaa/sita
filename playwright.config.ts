import { fileURLToPath } from 'node:url';
import path from 'node:path';

import { defineConfig } from '@playwright/test';

const currentDirectory = path.dirname(fileURLToPath(import.meta.url));
const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:9000';
const databasePath = path.join(
    currentDirectory,
    'database',
    'playwright.sqlite',
);

const webServerEnvironment = {
    ...process.env,
    APP_ENV: 'playwright',
    APP_URL: baseURL,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: databasePath,
    CACHE_STORE: 'array',
    MAIL_MAILER: 'array',
    QUEUE_CONNECTION: 'sync',
    SESSION_DRIVER: 'file',
    BROADCAST_CONNECTION: 'log',
    PULSE_ENABLED: 'false',
    TELESCOPE_ENABLED: 'false',
    NIGHTWATCH_ENABLED: 'false',
};

export default defineConfig({
    testDir: './tests/e2e/specs',
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    timeout: 90_000,
    expect: {
        timeout: 10_000,
    },
    globalSetup: './tests/e2e/global-setup.ts',
    use: {
        baseURL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    webServer: {
        command:
            'npm run build && php artisan serve --host=127.0.0.1 --port=9000',
        url: `${baseURL}/up`,
        reuseExistingServer: !process.env.CI,
        timeout: 180_000,
        env: webServerEnvironment,
    },
});
