import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { defineConfig } from '@playwright/test';

const currentDirectory = path.dirname(fileURLToPath(import.meta.url));
const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:9010';
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
    BROADCAST_CONNECTION: 'reverb',
    REVERB_APP_ID: 'local-app',
    REVERB_APP_KEY: 'local-key',
    REVERB_APP_SECRET: 'local-secret',
    REVERB_HOST: '127.0.0.1',
    REVERB_PORT: '9011',
    REVERB_SCHEME: 'http',
    REVERB_ALLOWED_ORIGINS: '127.0.0.1,localhost',
    REVERB_SERVER_HOST: '127.0.0.1',
    REVERB_SERVER_PORT: '9011',
    FRONTEND_REVERB_HOST: '127.0.0.1',
    VITE_REVERB_APP_KEY: 'local-key',
    VITE_REVERB_HOST: '127.0.0.1',
    VITE_REVERB_PORT: '9011',
    VITE_REVERB_SCHEME: 'http',
    PULSE_ENABLED: 'false',
    TELESCOPE_ENABLED: 'false',
    NIGHTWATCH_ENABLED: 'false',
};

// Keep SQLite and other test overrides when Artisan starts its PHP subprocess.
const webServerCommand = `${process.env.CI ? '' : 'npm run build && '}php artisan serve --no-reload --host=127.0.0.1 --port=9010`;

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
            command: 'php artisan reverb:start --host=127.0.0.1 --port=9011',
            port: 9011,
            reuseExistingServer: !process.env.CI,
            timeout: 60_000,
            env: webServerEnvironment,
        },
    ],
});
