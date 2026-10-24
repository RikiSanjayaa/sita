import { defineConfig } from '@playwright/test';

import './tests/e2e/support/catalog-env';
import base from './playwright.config';

// Screenshot catalog (`npm run catalog`). Same servers, database and bootstrap
// as the e2e suite (playwright.config.ts), but it runs only
// tests/e2e/specs/screenshot-catalog.spec.ts, which the normal e2e skips.
export default defineConfig({
    ...base,
    reporter: process.env.CI
        ? [['line']]
        : [
              ['list'],
              ['html', { outputFolder: 'playwright-report', open: 'never' }],
          ],
    // One test captures one page in four variants.
    timeout: 180_000,
    use: {
        ...base.use,
        screenshot: 'off',
        video: 'off',
        trace: 'off',
        launchOptions: {
            ...base.use?.launchOptions,
            // Software rendering with fixed font handling, so anti-aliasing
            // does not depend on the machine's GPU or load.
            args: [
                '--disable-gpu',
                '--disable-gpu-rasterization',
                '--disable-lcd-text',
                '--disable-skia-runtime-opts',
                '--font-render-hinting=none',
                '--force-color-profile=srgb',
            ],
        },
    },
    projects: [
        {
            name: 'setup',
            testMatch: /setup[\\/].*\.spec\.ts/,
        },
        {
            name: 'catalog',
            testMatch: /specs[\\/]screenshot-catalog\.spec\.ts/,
            dependencies: ['setup'],
        },
    ],
});
