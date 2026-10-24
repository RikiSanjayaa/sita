import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { defineConfig } from '@playwright/test';

const currentDirectory = path.dirname(fileURLToPath(import.meta.url));
// Ports default to 9010/9011. Override them (PLAYWRIGHT_APP_PORT,
// PLAYWRIGHT_REVERB_PORT) to run several checkouts side by side without one
// run reusing another checkout's server.
const appPort = process.env.PLAYWRIGHT_APP_PORT ?? '9010';
const reverbPort = process.env.PLAYWRIGHT_REVERB_PORT ?? '9011';
const baseURL =
    process.env.PLAYWRIGHT_BASE_URL ?? `http://127.0.0.1:${appPort}`;
// Support helpers read PLAYWRIGHT_BASE_URL; workers inherit it from here.
process.env.PLAYWRIGHT_BASE_URL = baseURL;
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
    // Filament 5.10 hands notifications to the next request through this cache
    // store, so it must outlive a single request (the array store does not).
    FILAMENT_CACHE_STORE: 'database',
    MAIL_MAILER: 'array',
    QUEUE_CONNECTION: 'sync',
    SESSION_DRIVER: 'file',
    BROADCAST_CONNECTION: 'reverb',
    REVERB_APP_ID: 'local-app',
    REVERB_APP_KEY: 'local-key',
    REVERB_APP_SECRET: 'local-secret',
    REVERB_HOST: '127.0.0.1',
    REVERB_PORT: reverbPort,
    REVERB_SCHEME: 'http',
    REVERB_ALLOWED_ORIGINS: '127.0.0.1,localhost',
    REVERB_SERVER_HOST: '127.0.0.1',
    REVERB_SERVER_PORT: reverbPort,
    PULSE_ENABLED: 'false',
    TELESCOPE_ENABLED: 'false',
    NIGHTWATCH_ENABLED: 'false',
};

// Keep SQLite and other test overrides when Artisan starts its PHP subprocess.
const webServerCommand = `${process.env.CI ? '' : 'npm run build && '}php artisan serve --no-reload --host=127.0.0.1 --port=${appPort}`;

export default defineConfig({
    reporter: process.env.CI
        ? [
              ['html', { outputFolder: 'playwright-report', open: 'never' }],
              ['line'],
          ]
        : [
              ['list'],
              ['html', { outputFolder: 'playwright-report', open: 'never' }],
          ],
    testDir: './tests/e2e',
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    timeout: 90_000,
    expect: {
        timeout: 10_000,
    },
    use: {
        baseURL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        launchOptions: process.env.PLAYWRIGHT_EXECUTABLE_PATH
            ? { executablePath: process.env.PLAYWRIGHT_EXECUTABLE_PATH }
            : undefined,
    },
    projects: [
        {
            name: 'setup',
            testMatch: /setup[\\/].*\.spec\.ts/,
        },
        {
            name: 'chromium',
            testMatch: /specs[\\/].*\.spec\.ts/,
            // The screenshot catalog has its own config (npm run catalog).
            testIgnore: /screenshot-catalog\.spec\.ts/,
            dependencies: ['setup'],
        },
    ],
    webServer: [
        {
            command: webServerCommand,
            url: `${baseURL}/up`,
            reuseExistingServer: !process.env.CI,
            timeout: 180_000,
            env: webServerEnvironment,
        },
        {
            command: `php artisan reverb:start --host=127.0.0.1 --port=${reverbPort}`,
            port: Number(reverbPort),
            reuseExistingServer: !process.env.CI,
            timeout: 60_000,
            env: webServerEnvironment,
        },
    ],
});
