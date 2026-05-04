import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const currentDirectory = path.dirname(fileURLToPath(import.meta.url));

export const repositoryRoot = path.resolve(currentDirectory, '../../..');
export const playwrightStoragePath = path.join(
    repositoryRoot,
    'storage',
    'playwright',
);
export const playwrightAuthPath = path.join(playwrightStoragePath, '.auth');
export const playwrightDatabasePath = path.join(
    repositoryRoot,
    'database',
    'playwright.sqlite',
);

export function playwrightEnvironment(
    baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:9010',
): NodeJS.ProcessEnv {
    return {
        ...process.env,
        APP_ENV: 'playwright',
        APP_URL: baseURL,
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: playwrightDatabasePath,
        CACHE_STORE: 'array',
        MAIL_MAILER: 'array',
        QUEUE_CONNECTION: 'sync',
        SESSION_DRIVER: 'file',
        BROADCAST_CONNECTION: 'log',
        PULSE_ENABLED: 'false',
        TELESCOPE_ENABLED: 'false',
        NIGHTWATCH_ENABLED: 'false',
    };
}

export function ensurePlaywrightFilesystem(): void {
    fs.mkdirSync(playwrightStoragePath, { recursive: true });
    fs.rmSync(playwrightAuthPath, { recursive: true, force: true });
    fs.mkdirSync(playwrightAuthPath, { recursive: true });

    if (!fs.existsSync(playwrightDatabasePath)) {
        fs.closeSync(fs.openSync(playwrightDatabasePath, 'w'));
    }
}

export function runArtisan(args: string[], baseURL?: string): void {
    execFileSync('php', ['artisan', ...args], {
        cwd: repositoryRoot,
        env: playwrightEnvironment(baseURL),
        stdio: 'inherit',
    });
}
